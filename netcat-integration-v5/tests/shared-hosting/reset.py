from pathlib import Path
import os
import re
import shutil
import subprocess

docker = ['docker', '--host=unix:///var/run/docker.sock']
site = Path(os.environ.get('NETCAT_TEST_SITE','/tmp/demopan-diagnosis-site'))
web = os.environ.get('NETCAT_REPRO_WEB_CONTAINER','demopan-repro-web')
database = os.environ.get('NETCAT_REPRO_DB_NAME','lifepodr')
assert web in ['demopan-repro-web','demopan-repro-web83'] and database in ['lifepodr','lifepodr83']
assert site.parent==Path('/tmp') and site.name.startswith('demopan-')
package = Path(__file__).resolve().parents[2]
source = Path(os.environ['NETCAT_TEST_SOURCE']).resolve()
sql = Path(os.environ['NETCAT_TEST_SQL']).resolve()
output = Path(os.environ['NETCAT_QA_OUTPUT']).resolve()
assert output.parent==Path('/tmp') and output.name.startswith('demopan-')
assert source.is_dir() and sql.is_file() and not site.is_symlink()
assert not source.is_relative_to(site.resolve()) and not site.resolve().is_relative_to(source)
assert not sql.is_relative_to(site.resolve())
output.mkdir(mode=0o700,parents=True,exist_ok=True)
(output/'tests').mkdir(exist_ok=True)
for relative in ['db_snapshot.py','replay.py','tests/verify-restore.py']:
    shutil.copyfile(Path(__file__).resolve().parent/relative,output/relative)
mount = subprocess.check_output(docker + ['inspect', '--format', '{{range .Mounts}}{{if eq .Destination "/site"}}{{.Source}}{{end}}{{end}}', web], text=True).strip()
assert mount == str(site)
code = '''$root='/site';$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach($it as $f){$f->isDir()&&!$f->isLink()?rmdir($f->getPathname()):unlink($f->getPathname());}'''
subprocess.run(docker + ['exec', web, 'php', '-r', code], check=True)
shutil.copytree(source, site, dirs_exist_ok=True)
config = site / 'vars.inc.php'
value = config.read_text()
for key, replacement in {'MYSQL_HOST': 'demopan-repro-db', 'MYSQL_USER': 'root', 'MYSQL_PASSWORD': '', 'MYSQL_DB_NAME': database, 'MYSQL_PORT': '3306', 'MYSQL_SOCKET': ''}.items():
    value = re.sub(r'(\$' + key + r'\s*=\s*)[^;]*;', lambda m: m[1] + repr(replacement) + ';', value)
config.write_text(value.replace('getenv("DOCUMENT_ROOT")', '(getenv("DOCUMENT_ROOT") ?: __DIR__)'))
if os.environ.get('NETCAT_TEST_SOURCE_ONLY') != '1':
    shutil.copytree(package / 'files', site, dirs_exist_ok=True)
prepare = (package / 'tests/prepare.py').read_text().replace("shutil.copytree(PACKAGE / 'files', SITE, dirs_exist_ok=True)", '')
os.environ['NETCAT_TEST_SITE'] = str(site)
exec(compile(prepare, str(package / 'tests/prepare.py'), 'exec'), {'__file__': str(package / 'tests/prepare.py')})
shutil.copyfile(site / '_life_auth.php', site / '_qa_auth.php')
(site / '_qa_trace.php').write_text('<?php\n')
subprocess.run(docker + ['exec', 'demopan-repro-db', 'mysql', '-S', '/tmp/mysql.sock', '-uroot', '-e', 'DROP DATABASE IF EXISTS '+database+'; CREATE DATABASE '+database+' CHARACTER SET utf8mb4;'], check=True)
with sql.open('rb') as stream:
    subprocess.run(docker + ['exec', '-i', 'demopan-repro-db', 'mysql', '-S', '/tmp/mysql.sock', '-uroot', database], stdin=stream, check=True)
print('Fresh pair reset on guarded test containers only; patched payload applied.')

import hashlib,json
paths=set()
for base in ['netcat_template/class','netcat_template/template','netcat_files','netcat_template/widget/regions']:
    for path in (source/base).rglob('*'):
        if not path.is_file(): continue
        relative=path.relative_to(source).as_posix()
        if relative.startswith(('netcat_files/life_teen_v5/','netcat_template/template/life_teen/')): continue
        assert path.resolve().is_relative_to(source)
        paths.add(relative)
for relative in ['netcat/modules/default/function.inc.php','lp-install.php','lp-request.php']:
    if (source/relative).is_file(): paths.add(relative)
files={name:hashlib.sha256((source/name).read_bytes()).hexdigest() for name in sorted(paths)}
(output/'files-before.json').write_text(json.dumps(files,indent=2))
print('Original file hashes recorded privately:',len(files))
