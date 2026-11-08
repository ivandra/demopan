"""Preservation/rollback checks against an isolated pre-5.6 snapshot."""
import base64,hashlib,json,os,subprocess
from pathlib import Path
import pymysql
site=Path(os.environ['NETCAT_TEST_SITE']);base=Path(os.environ['NETCAT_QA_BASELINE']);out=Path(os.environ['NETCAT_QA_OUTPUT']);web=os.environ.get('NETCAT_REPRO_WEB_CONTAINER','demopan-repro-web')
assert all(p.parent==Path('/tmp') and p.name.startswith('demopan-') for p in (site,base,out))
assert web in ('demopan-repro-web','demopan-repro-web83')
rollback=os.environ.get('NETCAT_CHECK_ROLLBACK')=='1'
c=pymysql.connect(host=os.environ['NETCAT_REPRO_DB_ADDRESS'],user='root',database='lifepodr',charset='utf8mb4',ssl_disabled=True,cursorclass=pymysql.cursors.DictCursor)
def protected(name):
 code='$s=file_get_contents("/site/netcat_files/life_teen_v56/'+name+'.php");$m="__halt_compiler();\\n";echo substr($s,strpos($s,$m)+strlen($m));'
 return json.loads(subprocess.check_output(['docker','exec',web,'php','-r',code]))
def decode(rows):return [{k:None if v is None else base64.b64decode(v).decode() for k,v in r.items()} for r in rows]
def value(v):return None if v is None else v.decode() if isinstance(v,bytes) else str(v)
backup=protected('database');state=protected('state');classes={r['Keyword']:int(r['Class_ID']) for r in decode(backup['classes'])};archive=set(map(int,state['plan']['archive'].get('records',[])))
errors=[];count=0;removed=0
with c.cursor() as q:
 for key,encoded in backup['records'].items():
  before=decode(encoded);q.execute('SELECT * FROM Message'+str(classes[key]));after={int(r['Message_ID']):r for r in q.fetchall()}
  if rollback and set(after)!={int(r['Message_ID']) for r in before}:errors.append(key+': IDs')
  for row in before:
   if not rollback and key=='life_gallery' and int(row['Message_ID']) in archive:
    removed+=1
    if int(row['Message_ID']) in after:errors.append('old archive record remains')
    continue
   count+=1;now=after.get(int(row['Message_ID']),{})
   if any(value(now.get(f))!=v for f,v in row.items()):errors.append(key+'#'+row['Message_ID'])
 if rollback:
  for k,table,idname in [('classes','Class','Class_ID'),('fields','Field','Field_ID'),('templates','Template','Template_ID'),('blocks','Sub_Class','Sub_Class_ID'),('sections','Subdivision','Subdivision_ID'),('catalogue','Catalogue','Catalogue_ID')]:
   for row in decode(backup[k]):
    q.execute('SELECT * FROM '+table+' WHERE '+idname+'=%s',(row[idname],));now=q.fetchone()
    if not now or any(value(now[f])!=v for f,v in row.items()):errors.append(table+'#'+row[idname])
 q.execute('SELECT Checked FROM Catalogue WHERE Catalogue_ID=1');assert q.fetchone()['Checked']==state['original_site_checked']==0
 q.execute('SELECT COUNT(*) n FROM Multifield m JOIN Field f USING(Field_ID) WHERE f.Class_ID=%s',(classes['life_gallery_bulk'],));assert q.fetchone()['n']==12
 # Region and multifile rows are unchanged across the historical 5.5 transition too.
checks={}
with c.cursor(pymysql.cursors.Cursor) as q:
 for table in ['Message223','Multifield','Filetable']:
  q.execute('SELECT * FROM `'+table+'`');rows=[[None if v is None else str(v) for v in row] for row in q.fetchall()];rows.sort(key=lambda row:json.dumps(row,ensure_ascii=False))
  h=hashlib.sha256(json.dumps(rows,ensure_ascii=False,separators=(',',':')).encode()).hexdigest()
  expected=json.loads((base/'database-before.json').read_text()).get(table)
  checks[table]=h==expected
  # Copies add their own file metadata; exact Filetable restoration is checked after rollback.
  if table!='Filetable' or rollback:
   if not checks[table]:errors.append(table+' hash')
# Package guides are extracted before the updater starts; they are not site data.
package_docs={'START_HERE_RU.md','EDITOR_GUIDE_RU.md','REPORT_RU.md','results.json'}
files={k:v for k,v in json.loads((base/'files-before.json').read_text()).items() if k not in package_docs};selected=files if rollback else {k:v for k,v in files.items() if k.startswith(('netcat_files/','netcat/system/','netcat/require/','netcat/admin/','netcat/vendor/','netcat_template/widget/regions/'))}
actual=json.loads(subprocess.check_output(['docker','exec',web,'php','/site/_qa_files56.php']))
file_errors=[p for p,h in selected.items() if actual.get(p)!=h]
report={'mode':'rollback' if rollback else 'installed','original_records_compared':count,'retired_archive_records':removed,'record_mismatches':errors,'original_files_compared':len(selected),'file_mismatches':file_errors,'table_checks':checks,'site_checked':0,'gallery_photos':12}
out.mkdir(exist_ok=True);(out/('rollback-data.json' if rollback else 'data-results.json')).write_text(json.dumps(report,indent=2));print(json.dumps(report));assert not errors and not file_errors
