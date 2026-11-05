"""Read-only preservation checks for the isolated v5.3 fixture."""
import base64,hashlib,json,os,re,subprocess
from pathlib import Path
import pymysql
site=Path(os.environ['NETCAT_TEST_SITE']);baseline=Path(os.environ['NETCAT_QA_BASELINE']);output=Path(os.environ['NETCAT_QA_OUTPUT']);container=os.environ.get('NETCAT_REPRO_WEB_CONTAINER','demopan-repro-web')
assert site.parent==baseline.parent==output.parent==Path('/tmp')
assert all(p.name.startswith('demopan-') for p in (site,baseline,output))
assert container in ('demopan-repro-web','demopan-repro-web83')
code='$s=file_get_contents("/site/netcat_files/life_teen_v53/database.php");$m="__halt_compiler();\\n";echo substr($s,strpos($s,$m)+strlen($m));'
backup=json.loads(subprocess.check_output(['docker','exec',container,'php','-r',code]))
def rows(key):return [{k:None if v is None else base64.b64decode(v).decode() for k,v in r.items()} for r in backup[key]]
classes={r['Keyword']:int(r['Class_ID']) for r in rows('classes')}
c=pymysql.connect(host=os.environ['NETCAT_REPRO_DB_ADDRESS'],user='root',database='lifepodr',charset='utf8mb4',ssl_disabled=True,cursorclass=pymysql.cursors.DictCursor)
modified={'life_specialists':{'bio','methods'},'life_articles':{'text','category'},'life_gallery':{'category'},'life_cards':{'text'},'life_founder':{'achievements'},'life_program':{'directions','support'},'life_program_service':{'directions','support'},'life_text':set()}
errors=[];records=0
with c.cursor() as q:
 for key,encoded in backup['records'].items():
  original=[{k:None if v is None else base64.b64decode(v).decode() for k,v in r.items()} for r in encoded]
  q.execute('SELECT * FROM Message'+str(classes[key]));actual={int(r['Message_ID']):r for r in q.fetchall()}
  if set(actual)!={int(r['Message_ID']) for r in original}:errors.append(key+': record IDs')
  for before in original:
   records+=1;now=actual[int(before['Message_ID'])]
   for field,value in before.items():
    if field in modified[key]:continue
    current=now[field];current=None if current is None else str(current)
    if current!=value:errors.append(key+'.'+field+'#'+before['Message_ID'])
 q.execute('SELECT Checked FROM Catalogue WHERE Catalogue_ID=1');assert q.fetchone()['Checked']==0
 q.execute("SELECT s.Subdivision_ID FROM Subdivision s JOIN Template t USING(Template_ID) WHERE t.File_Path='/life_teen/service/'");sections=[r['Subdivision_ID'] for r in q.fetchall()]
 for sub in sections:
  q.execute('SELECT COUNT(*) AS n FROM Sub_Class WHERE Subdivision_ID=%s AND Class_ID=%s',(sub,classes['life_text']));assert q.fetchone()['n']>=1
 counts={}
 for key,n in [('life_specialist_methods',4),('life_specialist_education',3),('life_article_tips',4)]:
  q.execute('SELECT Class_ID FROM Class WHERE Keyword=%s AND ClassTemplate=0',(key,));ident=q.fetchone()['Class_ID'];q.execute('SELECT COUNT(*) AS n FROM Message'+str(ident));counts[key]=q.fetchone()['n'];assert counts[key]==n
files=json.loads((baseline/'files-before.json').read_text());selected={k:v for k,v in files.items() if k.startswith(('netcat_files/','netcat/system/','netcat/require/','netcat/admin/','netcat_template/widget/regions/'))}
file_errors=[name for name,digest in selected.items() if not (site/name).is_file() or hashlib.sha256((site/name).read_bytes()).hexdigest()!=digest]
report={'original_records_compared':records,'immutable_column_mismatches':errors,'original_media_core_region_files':len(selected),'file_mismatches':file_errors,'service_text_blocks':len(sections),'imported_items':counts,'site_checked':0}
output.mkdir(exist_ok=True);(output/'data-results.json').write_text(json.dumps(report,indent=2));print(json.dumps(report));assert not errors and not file_errors
