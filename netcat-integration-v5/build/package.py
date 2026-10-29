from pathlib import Path
from zipfile import ZipFile,ZipInfo,ZIP_DEFLATED
import hashlib,copy

R=Path(__file__).resolve().parents[1];out=R.parent/'deliverables/lifepodrostka-netcat-v5.zip'
entries=[]
for file in sorted((R/'files').rglob('*')):
 if file.is_file():entries.append((file,'UPLOAD_TO_ROOT/'+file.relative_to(R/'files').as_posix()))
for name in ['START_HERE_RU.md','TEST_REPORT_RU.md']:entries.append((R/name,name))
for file in sorted((R/'docs').rglob('*')):
 if file.is_file():entries.append((file,'docs/'+file.relative_to(R/'docs').as_posix()))
for file in sorted((R/'tests').glob('*.json')):entries.append((file,'TEST_RESULTS/'+file.name))
def info(name):
 value=ZipInfo(name,(2026,10,30,0,0,0));value.compress_type=ZIP_DEFLATED;value.external_attr=0o644<<16;return value
checksums=''.join(hashlib.sha256(file.read_bytes()).hexdigest()+'  '+name+'\n' for file,name in entries)
with ZipFile(out,'w',ZIP_DEFLATED,compresslevel=9) as z:
 for file,name in entries:z.writestr(info(name),file.read_bytes())
 z.writestr(info('SHA256SUMS.txt'),checksums)
with ZipFile(out) as full:
 assert full.testzip() is None
 names=full.namelist();assert len(names)==len(set(names))
 assert not any('vars.inc.php' in n or n.endswith('.sql') or '_life_auth.php' in n or '/snapshot/' in n or n.endswith('/state.php') for n in names)
 for line in checksums.splitlines():
  digest,name=line.split('  ',1);assert hashlib.sha256(full.read(name)).hexdigest()==digest
 buckets=[[],[]];sizes=[0,0]
 for item in sorted(full.infolist(),key=lambda x:x.compress_size,reverse=True):
  index=min(range(2),key=lambda i:sizes[i]);buckets[index].append(item);sizes[index]+=item.compress_size
 merged={}
 for index,items in enumerate(buckets,1):
  part=out.with_name(out.stem+'-part'+str(index)+'.zip')
  with ZipFile(part,'w',ZIP_DEFLATED,compresslevel=9) as z:
   for item in sorted(items,key=lambda x:x.filename):z.writestr(copy.copy(item),full.read(item))
  with ZipFile(part) as z:
   assert z.testzip() is None
   for name in z.namelist():assert name not in merged;merged[name]=hashlib.sha256(z.read(name)).hexdigest()
  print(part.name,round(part.stat().st_size/1024**2,2),'MiB')
 assert merged=={name:hashlib.sha256(full.read(name)).hexdigest() for name in full.namelist()}
 print('PASS',len(names),'ZIP entries; two parts match complete package')
archives=sorted(out.parent.glob('lifepodrostka-netcat-v*.zip'))
(out.parent/'SHA256SUMS.txt').write_text(''.join(hashlib.sha256(file.read_bytes()).hexdigest()+'  '+file.name+'\n' for file in archives))
print(out.name,round(out.stat().st_size/1024**2,2),'MiB',hashlib.sha256(out.read_bytes()).hexdigest())
