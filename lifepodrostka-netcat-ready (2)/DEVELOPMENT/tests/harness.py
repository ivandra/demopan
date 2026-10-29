from pathlib import Path
import subprocess,os,time,pymysql,shutil,re,json,uuid,sys,hashlib
ROOT=Path.cwd();PKG=ROOT/'native-redo';runid=uuid.uuid4().hex[:10];site=ROOT/('native-test-'+runid);dbdir=Path('/dev/shm/zhp-'+runid)
env=os.environ.copy();env['LP_TEST_ROOT']=str(site);env['DOCUMENT_ROOT']=str(site);env['LD_LIBRARY_PATH']=str(ROOT/'runtime/root/usr/lib/x86_64-linux-gnu');dbdir.mkdir()
subprocess.run([str(ROOT/'runtime/root/usr/sbin/mariadbd'),'--no-defaults','--basedir='+str(ROOT/'runtime/root/usr'),'--datadir='+str(dbdir),'--bootstrap','--user=root'],input='CREATE DATABASE mysql;\n',text=True,env=env,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL,check=True)
dbproc=subprocess.Popen([str(ROOT/'runtime/root/usr/sbin/mariadbd'),'--no-defaults','--basedir='+str(ROOT/'runtime/root/usr'),'--datadir='+str(dbdir),'--socket=','--pid-file=/tmp/zhp-test.pid','--port=3307','--bind-address=127.0.0.1','--skip-grant-tables','--user=root','--log-error='+str(PKG/'tests/db.log')],env=env,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
server=None
try:
 for _ in range(100):
  try:c=pymysql.connect(host='127.0.0.1',port=3307,user='root',autocommit=True,client_flag=65536);break
  except Exception:time.sleep(.15)
 else:raise Exception('DB not ready')
 q=c.cursor();q.execute('CREATE DATABASE lifepodr CHARACTER SET utf8mb4');q.execute('USE lifepodr');q.execute("SET SESSION sql_mode=''");q.execute((ROOT/'study/postroisvo_zhizn.sql/postroisvo_zhizn.sql').read_text())
 while q.nextset():pass
 shutil.copytree(ROOT/'study/lifepodr',site)
 p=site/'vars.inc.php';s=p.read_text()
 for k,v in {'MYSQL_HOST':'127.0.0.1','MYSQL_USER':'root','MYSQL_PASSWORD':'','MYSQL_DB_NAME':'lifepodr','MYSQL_PORT':'3307','MYSQL_SOCKET':''}.items():s=re.sub(r'(\$'+k+r'\s*=\s*)[^;]*;',lambda m:m[1]+repr(v)+';',s)
 s=s.replace('getenv("DOCUMENT_ROOT")','(getenv("DOCUMENT_ROOT") ?: __DIR__)');p.write_text(s)
 if '--upgrade' in sys.argv:
  old=ROOT/'study/lifepodrostka-netcat-ready/UPLOAD_TO_ROOT';shutil.copytree(old,site,dirs_exist_ok=True)
  for mode in ['install','activate']:
   r=subprocess.run(['runtime/php',str(site/'lp-install.php'),mode],env=env,capture_output=True,text=True);assert r.returncode==0,r.stdout+r.stderr

  oldstate=json.loads(subprocess.check_output(['runtime/php','-r',"echo json_encode(include '"+str(site/'netcat_files/lifepodrostka/state.php')+"');"],env=env,text=True));lead=oldstate['collections']['leads'];q.execute('INSERT INTO Message'+str(lead['class_id'])+' (Subdivision_ID,Sub_Class_ID,Checked,name,request_key) VALUES (%s,%s,1,%s,%s)',(lead['sub_id'],lead['cc_id'],'Заявка предыдущего переноса','f'*32))
 shutil.copytree(PKG/'files',site,dirs_exist_ok=True)

 # Package corruption must fail before any catalogue change.
 q.execute('SELECT * FROM Catalogue');before_catalogue=q.fetchall();logo=site/'netcat_template/template/zhizn_podrostka/img/logo.svg';original_logo=logo.read_bytes();logo.write_bytes(original_logo+b' ')
 r=subprocess.run(['runtime/php',str(site/'zhp-install.php'),'check'],env=env,capture_output=True,text=True);assert r.returncode!=0 and 'Файл отсутствует или изменён' in r.stdout,r.stdout;logo.write_bytes(original_logo);q.execute('SELECT * FROM Catalogue');assert q.fetchall()==before_catalogue
 if '--upgrade' in sys.argv:
  legacy_backup=site/'netcat_files/lifepodrostka/backup.php';temporary_backup=legacy_backup.with_suffix('.php-test');legacy_backup.rename(temporary_backup)
  r=subprocess.run(['runtime/php',str(site/'zhp-install.php'),'reset'],env=env,capture_output=True,text=True);assert r.returncode!=0 and 'Откат остановлен' in r.stdout,r.stdout;temporary_backup.rename(legacy_backup);q.execute('SELECT * FROM Catalogue');assert q.fetchall()==before_catalogue
 def legacy_rows():
  q.execute("SHOW TABLES LIKE 'Message%'");names=[row[0] for row in q.fetchall() if re.fullmatch('Message[0-9]+',row[0])];result={}
  for name in names:
   q.execute('SELECT * FROM `'+name+'` ORDER BY Message_ID');columns=[x[0] for x in q.description];rows=q.fetchall();keep=[i for i,k in enumerate(columns) if k!='Subdivision_ID'];result[name]=hashlib.sha256(json.dumps([[row[i] for i in keep] for row in rows],default=str,ensure_ascii=False).encode()).hexdigest()
  return result
 legacy_before=legacy_rows()
 (site/'_zhp_auth.php').write_text('''<?php require __DIR__.'/vars.inc.php';require $ROOT_FOLDER.'connect_io.php';$nc_core=nc_core();$nc_core->modules->load_env();require $nc_core->ADMIN_FOLDER.'lang/'.$nc_core->lang->detect_lang().'.php';echo $nc_core->user->authorize_by_id(1);''')
 (site/'_zhp_state.php').write_text('''<?php require __DIR__.'/vars.inc.php';require $ROOT_FOLDER.'connect_io.php';$nc=nc_core();$nc->user->attempt_to_authorize();if(!$nc->InsideAdminAccess()){http_response_code(403);exit;}header('Content-Type: application/json');$s=include __DIR__.'/netcat_files/zhizn_podrostka/state.php';if(isset($_GET['seed_region'])){$x=$s['collections']['regions'];$nc->message->create($x['cc_id'],array('main_domain'=>'spb.lifepodrostka.ru','city'=>'Санкт-Петербург','phone'=>'+7 (812) 000-00-00','address'=>'Региональный адрес','email'=>'spb@example.test','Checked'=>1));}echo json_encode($s);''')
 server=subprocess.Popen(['runtime/php','-d','max_execution_time=10','-S','127.0.0.1:8080','-t',str(site),'native-redo/tests/router.php'],env=env,stdout=open(PKG/'tests/php-http.log','w'),stderr=subprocess.STDOUT);time.sleep(.4)
 env['ZHP_UPGRADE']='1' if '--upgrade' in sys.argv else '0'
 r=subprocess.run(['node','native-redo/tests/browser.mjs'],env=env);assert r.returncode==0,'Browser tests failed'
 state=json.loads(subprocess.check_output(['runtime/php','-r',"echo json_encode(include '"+str(site/'netcat_files/zhizn_podrostka/state.php')+"');"],text=True,env=env))
 q.execute('SELECT s.Sub_Class_ID,c.Keyword FROM Sub_Class s JOIN Class c USING(Class_ID) WHERE s.Subdivision_ID=12');main=q.fetchall();assert all(k.startswith('zhp_') for _,k in main),main
 q.execute('SELECT COUNT(*) FROM Message'+str(state['classes']['zhp_leads']));count=q.fetchone()[0];assert count==4,count
 legacy_after=legacy_rows();assert all(legacy_after[k]==v for k,v in legacy_before.items()),'Original or v1 records changed';print('PASS Original and previous package records preserved',flush=True)
 print('PASS DB: clean main admin and persisted leads',count,flush=True)
 (PKG/('tests/db-results'+('-upgrade' if '--upgrade' in sys.argv else '')+'.json')).write_text(json.dumps({'pass':True,'main_blocks':main,'leads':count,'upgrade':'--upgrade' in sys.argv},ensure_ascii=False,indent=2))
except Exception:
 (PKG/'tests/last-run.json').write_text(json.dumps({'root':str(site),'dbdir':str(dbdir)}));raise
finally:
 if server:server.terminate();server.wait()
 dbproc.terminate();dbproc.wait(20)
