from pathlib import Path
from zipfile import ZipFile,ZIP_DEFLATED
import hashlib,json
R=Path('native-redo');out=Path('deliverable/lifepodrostka-netcat-ready.zip');out.parent.mkdir(exist_ok=True)
entries=[]
for p in sorted((R/'files').rglob('*')):
 if p.is_file():entries.append((p,'UPLOAD_TO_ROOT/'+str(p.relative_to(R/'files'))))
for p in [R/'START_HERE_RU.md',R/'TEST_REPORT_RU.md']:entries.append((p,p.name))
for p in sorted((R/'docs').glob('*.md')):entries.append((p,'docs/'+p.name))
for p in sorted((R/'build').rglob('*')):
 if p.is_file() and '__pycache__' not in p.parts:entries.append((p,'DEVELOPMENT/build/'+str(p.relative_to(R/'build'))))
for p in sorted((R/'tests').iterdir()):
 if p.suffix in ['.py','.mjs'] or p.name=='router.php' or p.name in ['browser-results.json','browser-results-upgrade.json','db-results.json','db-results-upgrade.json','lint.json']:entries.append((p,'DEVELOPMENT/tests/'+p.name))
sums=''.join(hashlib.sha256(p.read_bytes()).hexdigest()+'  '+name+'\n' for p,name in entries)
with ZipFile(out,'w',ZIP_DEFLATED,compresslevel=9) as z:
 for p,name in entries:z.write(p,name)
 z.writestr('SHA256SUMS.txt',sums)
with ZipFile(out) as z:
 assert z.testzip() is None
 assert not any('internal-qa' in n or n.endswith('bootstrap.php') or '_zhp_auth' in n for n in z.namelist())
 assert 'UPLOAD_TO_ROOT/zhp-install.php' in z.namelist()
 print('Archive:',out,'MiB:',round(out.stat().st_size/1024**2,2),'entries:',len(z.namelist()),'sha256:',hashlib.sha256(out.read_bytes()).hexdigest())
