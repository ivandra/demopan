from pathlib import Path
import subprocess,os,json,re,shutil,argparse,hashlib
from zipfile import ZipFile
import pymysql

R=Path(__file__).resolve().parents[1];REPO=R.parent
SITE=Path('/workspace/netcat-runtime/site')
DOCKER=['docker','--host=unix:///var/run/docker.sock']
p=argparse.ArgumentParser();p.add_argument('--previous',choices=['v3','v4']);p.add_argument('--rolled-back',action='store_true');args=p.parse_args()
env=os.environ.copy()
for key in ['DOCKER_HOST','DOCKER_CONTEXT','DOCKER_TLS','DOCKER_TLS_VERIFY','DOCKER_CERT_PATH']:env.pop(key,None)
def run(cmd,**kw):return subprocess.run(cmd,env=env,check=True,**kw)
def php(*args):return run(DOCKER+['exec','netcat-qa-web','php',*map(str,args)],capture_output=True,text=True)
# Only the existing, task-owned QA container and database are reset.
containers=subprocess.check_output(DOCKER+['inspect','--format','{{.Name}}','netcat-qa-db'],text=True).strip()
assert containers=='/netcat-qa-db' and str(SITE)=='/workspace/netcat-runtime/site'
c=pymysql.connect(host='127.0.0.1',port=23307,user='root',autocommit=True);q=c.cursor()
q.execute('DROP DATABASE IF EXISTS lifepodr');q.execute('CREATE DATABASE lifepodr CHARACTER SET utf8mb4')
with open('/workspace/netcat-study/postroisvo_zhizn.sql/postroisvo_zhizn.sql','rb') as f:run(DOCKER+['exec','-i','netcat-qa-db','mariadb','-uroot','lifepodr'],stdin=f,stdout=subprocess.DEVNULL)
q.execute('USE lifepodr')
if SITE.exists():
 php('-r','$p='+json.dumps(str(SITE))+';$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($p,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach($it as $f){$f->isDir()?rmdir($f->getPathname()):unlink($f->getPathname());}')
shutil.copytree(REPO/'lifepodr',SITE,dirs_exist_ok=True)
p=SITE/'vars.inc.php';s=p.read_text()
for key,value in {'MYSQL_HOST':'netcat-qa-db','MYSQL_USER':'root','MYSQL_PASSWORD':'','MYSQL_DB_NAME':'lifepodr','MYSQL_PORT':'3306','MYSQL_SOCKET':''}.items():
 s=re.sub(r'(\$'+key+r'\s*=\s*)[^;]*;',lambda m:m[1]+repr(value)+';',s)
p.write_text(s.replace('getenv("DOCUMENT_ROOT")','(getenv("DOCUMENT_ROOT") ?: __DIR__)'))
if args.previous:
 with ZipFile(REPO/'deliverables'/('lifepodrostka-netcat-'+args.previous+'.zip')) as z:
  for name in z.namelist():
   if name.startswith('UPLOAD_TO_ROOT/') and not name.endswith('/'):
    target=SITE/name.removeprefix('UPLOAD_TO_ROOT/');target.parent.mkdir(parents=True,exist_ok=True);target.write_bytes(z.read(name))
 for mode in ['install','activate']+(['rollback'] if args.rolled_back else []):
  r=php(SITE/'zhp-install.php',mode);assert 'ОШИБКА:' not in r.stdout,r.stdout
 print('PASS Prepared previous '+args.previous+(' rolled back' if args.rolled_back else ' activated'),flush=True)

def normalize(rows):return [[None if x is None else str(x) for x in row] for row in rows]
snapshot={};q.execute('SHOW TABLES');tables=[r[0] for r in q.fetchall()]
for table in tables:
 if table in ['Class','Field','Template','Sub_Class','Subdivision','Catalogue'] or re.fullmatch('Message[0-9]+',table):
  q.execute('SELECT * FROM `'+table+'`');rows=normalize(q.fetchall());rows.sort(key=lambda row:json.dumps(row,ensure_ascii=False))
  snapshot[table]=hashlib.sha256(json.dumps(rows,ensure_ascii=False,separators=(',',':')).encode()).hexdigest()
Path('/workspace/netcat-runtime/v5-before.json').write_text(json.dumps(snapshot))
run(['python3',str(R/'tests/prepare.py')],stdout=subprocess.DEVNULL)
scenario=args.previous or 'original'
if args.rolled_back:scenario+='-rollback'
env['LIFE_SCENARIO']=scenario
run(['node',str(R/'tests/browser-v5.mjs')],cwd=REPO)
print('PASS Clean v5 scenario '+scenario,flush=True)
