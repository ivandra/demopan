"""Preservation and rollback checks; disposable Netcat fixture only."""
import base64, hashlib, json, os, subprocess
from pathlib import Path
import pymysql
site=Path(os.environ['NETCAT_TEST_SITE']);baseline=Path(os.environ['NETCAT_QA_BASELINE']);out=Path(os.environ['NETCAT_QA_OUTPUT']);web=os.environ.get('NETCAT_REPRO_WEB_CONTAINER','demopan-repro-web')
assert all(p.parent==Path('/tmp') and p.name.startswith('demopan-') for p in (site,baseline,out))
assert web in ('demopan-repro-web','demopan-repro-web83')
rollback=os.environ.get('NETCAT_CHECK_ROLLBACK')=='1'
code='$s=file_get_contents("/site/netcat_files/life_teen_v54/database.php");$m="__halt_compiler();\\n";echo substr($s,strpos($s,$m)+strlen($m));'
backup=json.loads(subprocess.check_output(['docker','exec',web,'php','-r',code]))
def decode(rows):return [{k:None if v is None else base64.b64decode(v).decode() for k,v in r.items()} for r in rows]
classes={r['Keyword']:int(r['Class_ID']) for r in decode(backup['classes'])}
c=pymysql.connect(host=os.environ['NETCAT_REPRO_DB_ADDRESS'],user='root',database='lifepodr',charset='utf8mb4',ssl_disabled=True,cursorclass=pymysql.cursors.DictCursor)
def text_value(v):return None if v is None else v.decode('utf-8') if isinstance(v,bytes) else str(v)
errors=[];count=0
with c.cursor() as q:
 for key,encoded in backup['records'].items():
  before=decode(encoded);q.execute('SELECT * FROM Message'+str(classes[key]));after={int(r['Message_ID']):r for r in q.fetchall()}
  if rollback and set(after)!={int(r['Message_ID']) for r in before}:errors.append(key+': IDs')
  for row in before:
   count+=1;now=after.get(int(row['Message_ID']),{})
   allowed=set() if rollback else {'Subdivision_ID'}
   if not rollback:
    if key=='life_specialists':allowed|={'education'}
    if key in ('life_gallery','life_specialist_methods'):allowed|={'Sub_Class_ID'}
    if key=='life_specialist_methods':allowed|={'specialist'}
   for field,value in row.items():
    if field in allowed:continue
    v=now.get(field);v=text_value(v)
    if v!=value:errors.append(key+'.'+field+'#'+row['Message_ID'])
 if rollback:
  for k,table,idname in [('classes','Class','Class_ID'),('fields','Field','Field_ID'),('templates','Template','Template_ID'),('blocks','Sub_Class','Sub_Class_ID'),('sections','Subdivision','Subdivision_ID'),('catalogue','Catalogue','Catalogue_ID')]:
   for row in decode(backup[k]):
    q.execute('SELECT * FROM '+table+' WHERE '+idname+'=%s',(row[idname],));now=q.fetchone()
    if not now or any(text_value(now[f])!=v for f,v in row.items()):errors.append(table+'#'+row[idname])
 else:
  q.execute("SELECT Class_ID FROM Class WHERE Keyword='life_gallery_bulk'");gallery=q.fetchone()['Class_ID']
  q.execute('SELECT COUNT(*) n FROM Message'+str(gallery));groups=q.fetchone()['n'];assert groups==4
  q.execute('SELECT COUNT(*) n FROM Multifield m JOIN Field f USING(Field_ID) WHERE f.Class_ID=%s',(gallery,));photos=q.fetchone()['n'];assert photos==12
  q.execute("SELECT COUNT(*) n FROM Field WHERE Class_ID=%s AND Field_Name='img' AND TypeOfData_ID=11",(gallery,));assert q.fetchone()['n']==1
 q.execute('SELECT Checked FROM Catalogue WHERE Catalogue_ID=1');assert q.fetchone()['Checked']==0
 # Compare every column of the existing region table, not just its city labels.
with c.cursor(pymysql.cursors.Cursor) as q:
 q.execute('SELECT * FROM Message223');region_rows=[[None if v is None else str(v) for v in row] for row in q.fetchall()]
region_rows.sort(key=lambda row:json.dumps(row,ensure_ascii=False))
region_hash=hashlib.sha256(json.dumps(region_rows,ensure_ascii=False,separators=(',',':')).encode()).hexdigest()
assert region_hash==json.loads((baseline/'database-before.json').read_text())['Message223'],'original region records changed'
files=json.loads((baseline/'files-before.json').read_text());selected={k:v for k,v in files.items() if k.startswith(('netcat_files/','netcat/system/','netcat/require/','netcat/admin/','netcat_template/widget/regions/'))}
# Hash inside the container: native backups have deliberately private permissions.
actual=json.loads(subprocess.check_output(['docker','exec',web,'php','/site/_qa_files.php']))
file_errors=[p for p,h in selected.items() if actual.get(p)!=h]
if rollback:file_errors=[p for p,h in files.items() if actual.get(p)!=h]
report={'mode':'rollback' if rollback else 'installed','original_records_compared':count,'immutable_column_mismatches':errors,'original_files_compared':len(files) if rollback else len(selected),'file_mismatches':file_errors,'site_checked':0}
if not rollback:report.update(gallery_groups=groups,gallery_photos=photos)
out.mkdir(exist_ok=True);(out/('rollback-data.json' if rollback else 'data-results.json')).write_text(json.dumps(report,indent=2));print(json.dumps(report));assert not errors and not file_errors
