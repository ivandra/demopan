"""Reproduce integration checks on an isolated site, optionally upgrading v2/v3.

Requires the local Docker daemon, netcat-qa-php:8.3, mariadb:10.11,
PyMySQL, and Node/Playwright/Chromium (see README). No production credentials.
"""
from pathlib import Path
import argparse
import hashlib
import json
import os
import re
import shutil
import subprocess
import time
from zipfile import ZipFile
import pymysql

PACKAGE = Path(__file__).resolve().parents[1]
REPO = PACKAGE.parent
RUNTIME = Path(os.environ.get('NETCAT_TEST_RUNTIME', '/workspace/netcat-runtime'))
SITE = RUNTIME / 'site'
DOCKER = ['docker', '--host=unix:///var/run/docker.sock']
args = argparse.ArgumentParser()
args.add_argument('--upgrade', choices=['v2','v3'], help='Install and activate a previous package first')
args.add_argument('--after-rollback', action='store_true', help='Roll back the previous installation before applying v4')
args.add_argument('--sql', type=Path, required=True)
options = args.parse_args()
env = os.environ.copy()
for selector in ['DOCKER_HOST','DOCKER_CONTEXT','DOCKER_TLS','DOCKER_TLS_VERIFY','DOCKER_CERT_PATH']:
    env.pop(selector, None)

def run(command, **kwargs):
    return subprocess.run(command, check=True, env=env, **kwargs)

def php(*arguments):
    return run(DOCKER + ['exec', 'netcat-qa-web', 'php', *map(str, arguments)], capture_output=True, text=True)

def record_hash(cursor, table, columns, ids=None):
    # sub_class->move intentionally changes Subdivision_ID; preserve every other original column.
    columns = [c for c in columns if c != 'Subdivision_ID']
    condition='' if ids is None else (' WHERE Message_ID IN ('+','.join(map(str,ids))+')' if ids else ' WHERE 0')
    cursor.execute('SELECT '+','.join('`'+c+'`' for c in columns)+' FROM `'+table+'`'+condition+' ORDER BY Message_ID')
    return hashlib.sha256(json.dumps(cursor.fetchall(), default=str, ensure_ascii=False).encode()).hexdigest()

connection = pymysql.connect(host='127.0.0.1', port=23307, user='root', autocommit=True)
cursor = connection.cursor()
cursor.execute('DROP DATABASE IF EXISTS lifepodr')
cursor.execute('CREATE DATABASE lifepodr CHARACTER SET utf8mb4')
with options.sql.open('rb') as source:
    run(DOCKER + ['exec','-i','netcat-qa-db','mariadb','-uroot','lifepodr'], stdin=source, stdout=subprocess.DEVNULL)
cursor.execute('USE lifepodr')

# This directory is owned by this harness, outside all source checkouts.
if SITE.exists():
    run(DOCKER + ['exec','netcat-qa-web','php','-r',
        '$p='+json.dumps(str(SITE))+'; $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($p,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST); foreach($it as $f){$f->isDir()?rmdir($f->getPathname()):unlink($f->getPathname());}'])
shutil.copytree(REPO / 'lifepodr', SITE, dirs_exist_ok=True)
variables = SITE / 'vars.inc.php'
contents = variables.read_text()
for key, value in {'MYSQL_HOST':'netcat-qa-db','MYSQL_USER':'root','MYSQL_PASSWORD':'',
                   'MYSQL_DB_NAME':'lifepodr','MYSQL_PORT':'3306','MYSQL_SOCKET':''}.items():
    contents = re.sub(r'(\$'+key+r'\s*=\s*)[^;]*;', lambda m:m[1]+repr(value)+';', contents)
variables.write_text(contents.replace('getenv("DOCUMENT_ROOT")','(getenv("DOCUMENT_ROOT") ?: __DIR__)'))

if options.upgrade:
    if options.upgrade=='v2':
        previous = REPO / 'lifepodrostka-netcat-ready (2)/UPLOAD_TO_ROOT'
        shutil.copytree(previous, SITE, dirs_exist_ok=True)
    else:
        with ZipFile(REPO/'deliverables/lifepodrostka-netcat-v3.zip') as archive:
            for name in archive.namelist():
                if name.startswith('UPLOAD_TO_ROOT/') and not name.endswith('/'):
                    target=SITE/name.removeprefix('UPLOAD_TO_ROOT/')
                    target.parent.mkdir(parents=True,exist_ok=True)
                    target.write_bytes(archive.read(name))
    for mode in ['install', 'activate']+(['rollback'] if options.after_rollback else []):
        result = php(SITE / 'zhp-install.php', mode)
        assert 'ОШИБКА:' not in result.stdout, result.stdout
    print('PASS Previous '+options.upgrade+' installed'+(' and rolled back' if options.after_rollback else ' and activated'), flush=True)
    # A real editor value must survive the migration.
    cursor.execute("SELECT Class_ID FROM Class WHERE Keyword='zhp_articles' AND ClassTemplate=0")
    table = 'Message'+str(cursor.fetchone()[0])
    cursor.execute('UPDATE `'+table+"` SET intro='Редакторское изменение до обновления' WHERE Message_ID=1")

cursor.execute("SHOW TABLES LIKE 'Message%'")
tables = [r[0] for r in cursor.fetchall() if re.fullmatch(r'Message\d+',r[0])]
originals = {}
editor_tables = {'Message223'}
if options.upgrade:
    cursor.execute("SELECT Class_ID FROM Class WHERE Keyword='zhp_articles' AND ClassTemplate=0")
    editor_tables.add('Message'+str(cursor.fetchone()[0]))
for table in tables:
    cursor.execute('SHOW COLUMNS FROM `'+table+'`')
    columns = [r[0] for r in cursor.fetchall()]
    if table in editor_tables:
        columns = [c for c in columns if c not in ['LastUpdated','LastUser_ID','LastIP','LastUserAgent']]
    cursor.execute('SELECT Message_ID FROM `'+table+'` ORDER BY Message_ID')
    ids=[row[0] for row in cursor.fetchall()]
    originals[table] = {'columns':columns, 'ids':ids, 'hash':record_hash(cursor,table,columns,ids)}
    if table=='Message223':
        cursor.execute('SELECT '+','.join('`'+name+'`' for name in columns)+' FROM `'+table+'` ORDER BY Message_ID')
        originals[table]['records']=json.loads(json.dumps(cursor.fetchall(),default=str))
snapshot=RUNTIME/'preservation.json'
snapshot.write_text(json.dumps(originals))
env['NETCAT_PRESERVATION_SNAPSHOT']=str(snapshot)
cursor.execute('SELECT * FROM Catalogue')
catalogue_before = cursor.fetchall()
run(['python3', str(PACKAGE / 'tests/prepare.py')], stdout=subprocess.DEVNULL)

# A changed payload must fail before changing the site's settings.
logo = SITE / 'netcat_template/template/zhizn_podrostka/img/logo.svg'
original_logo = logo.read_bytes()
logo.write_bytes(original_logo+b' ')
try:
    result = subprocess.run(DOCKER+['exec','netcat-qa-web','php',str(SITE/'zhp-install.php'),'check'],
                            capture_output=True,text=True,env=env)
    assert result.returncode != 0 and 'Файл отсутствует или изменён' in result.stdout, result.stdout
finally:
    logo.write_bytes(original_logo)
cursor.execute('SELECT * FROM Catalogue')
assert cursor.fetchall() == catalogue_before
print('PASS Corrupted payload rejected before catalogue mutation', flush=True)

env['ZHP_UPGRADE'] = '1' if options.upgrade else '0'
env['ZHP_SCENARIO'] = ('upgrade-'+options.upgrade+('-after-rollback' if options.after_rollback else '')) if options.upgrade else 'clean'
env['NETCAT_NATIVE_V2_UPGRADE'] = '1' if options.upgrade else '0'
run(['node', str(PACKAGE/'tests/browser-v4.mjs')], cwd=REPO)

preserved = []
normalized_region_fields=[]
for table, original in originals.items():
    # Browser tests deliberately edit/add specialists and add one test region.
    # Compare only the original objects; the uploaded test portrait is restored in the UI test.
    if table == 'Message223':
        cursor.execute('DELETE FROM Message223 WHERE main_domain=%s', ('spb.lifepodrostka.ru',))
    if options.upgrade:
        cursor.execute("SELECT Class_ID FROM Class WHERE Keyword IN ('zhp_specialists','zhp_articles','zhp_leads') AND ClassTemplate=0")
        if table in ['Message'+str(row[0]) for row in cursor.fetchall()]:
            continue
    if table=='Message223':
        names=original['columns']
        cursor.execute('SELECT '+','.join('`'+name+'`' for name in names)+' FROM Message223 ORDER BY Message_ID')
        actual=json.loads(json.dumps(cursor.fetchall(),default=str))
        assert len(actual)==len(original['records'])
        for before,after in zip(original['records'],actual):
            for name,old,new in zip(names,before,after):
                if name=='Subdivision_ID' or old==new:continue
                # The native editor normalizes previously NULL optional fields.
                assert old is None and new in ['',0], 'Unexpected native region edit: '+name
                normalized_region_fields.append(name)
        preserved.append(table)
        continue
    assert record_hash(cursor,table,original['columns'],original['ids']) == original['hash'], 'Changed original rows: '+table
    preserved.append(table)

cursor.execute("SELECT COUNT(*) FROM Class WHERE Class_ID<>223 AND ClassTemplate=0 AND Keyword='zhp_regions'")
extra_regions = cursor.fetchone()[0]
assert extra_regions == (1 if options.upgrade=='v2' else 0)
cursor.execute('SELECT Class_ID,Subdivision_ID FROM Sub_Class WHERE Sub_Class_ID=57')
assert cursor.fetchone() == (223,8)
cursor.execute("SELECT COUNT(*) FROM Template WHERE File_Path LIKE '/zhizn_podrostka/%'")
assert cursor.fetchone()[0] == (11 if options.upgrade else 5)
cursor.execute("SELECT s.Sub_Class_ID FROM Sub_Class s JOIN Class c USING(Class_ID) WHERE s.Subdivision_ID=12 AND c.Keyword NOT LIKE 'zhp\\_%'")
assert not cursor.fetchall()
cursor.execute("SELECT Class_ID FROM Class WHERE Keyword='zhp_leads' AND ClassTemplate=0")
cursor.execute('SELECT COUNT(*) FROM Message'+str(cursor.fetchone()[0]))
assert cursor.fetchone()[0] == 3

result = {'passed':True,'scenario':('upgrade-'+options.upgrade+('-after-rollback' if options.after_rollback else '')) if options.upgrade else 'clean',
          'preservation_before_editor':True,
          'preserved_original_tables':preserved,'region_component':223,'region_infoblock':57,
          'native_editor_normalized_empty_fields':normalized_region_fields,
          'layouts':5,'persisted_form_submissions':3}
output = PACKAGE/'tests'/('db-results-'+result['scenario']+'.json')
output.write_text(json.dumps(result,ensure_ascii=False,indent=2))
print('PASS Database preservation, existing regions, layouts and submissions', flush=True)
