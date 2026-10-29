import json,pymysql,hashlib
from pathlib import Path
c=pymysql.connect(host='127.0.0.1',port=23307,user='root',database='lifepodr');q=c.cursor()
expected=json.loads(Path('/workspace/netcat-runtime/v5-before.json').read_text())
for table,digest in expected.items():
 q.execute('SELECT * FROM `'+table+'`');rows=[[None if x is None else str(x) for x in row] for row in q.fetchall()];rows.sort(key=lambda row:json.dumps(row,ensure_ascii=False))
 assert hashlib.sha256(json.dumps(rows,ensure_ascii=False,separators=(',',':')).encode()).hexdigest()==digest,'Restore mismatch '+table
print('PASS Exact database restoration:',len(expected),'tables')

import subprocess
code="""$root='/workspace/netcat-runtime/site';$folder=$root.'/netcat_files/life_teen_v5/snapshot';$backup=include $folder.'/manifest.php';$bad=array();foreach($backup['files'] as $relative){$a=$root.'/'.$relative;$b=$folder.'/files/'.$relative;if(!is_file($a)||hash_file('sha256',$a)!==hash_file('sha256',$b)){$bad[]=$relative;}}echo json_encode(array('files'=>count($backup['files']),'failed'=>$bad));"""
result=json.loads(subprocess.check_output(['docker','--host=unix:///var/run/docker.sock','exec','netcat-qa-web','php','-r',code],text=True))
assert not result['failed'],result['failed'][:8]
print('PASS Exact restored file contents:',result['files'],'files')
