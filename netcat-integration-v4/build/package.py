from pathlib import Path
from zipfile import ZipFile, ZipInfo, ZIP_DEFLATED
import hashlib
import copy

ROOT = Path(__file__).resolve().parents[1]
OUTPUT = ROOT.parent / 'deliverables/lifepodrostka-netcat-v4.zip'
OUTPUT.parent.mkdir(exist_ok=True)
entries = []
for path in sorted((ROOT / 'files').rglob('*')):
    if path.is_file():
        entries.append((path, 'UPLOAD_TO_ROOT/' + path.relative_to(ROOT / 'files').as_posix()))
for name in ['START_HERE_RU.md', 'TEST_REPORT_RU.md']:
    entries.append((ROOT / name, name))
for folder, prefix in [('docs', 'docs'), ('build', 'DEVELOPMENT/build'), ('tests', 'DEVELOPMENT/tests')]:
    for path in sorted((ROOT / folder).rglob('*')):
        if path.is_file() and '__pycache__' not in path.parts and path.suffix not in ['.log']:
            if folder == 'tests' and path.suffix == '.html':
                continue
            entries.append((path, prefix + '/' + path.relative_to(ROOT / folder).as_posix()))
checksums = ''.join(hashlib.sha256(path.read_bytes()).hexdigest() + '  ' + name + '\n' for path, name in entries)
with ZipFile(OUTPUT, 'w', ZIP_DEFLATED, compresslevel=9) as archive:
    for path, name in entries:
        info=ZipInfo(name,(2026,10,29,0,0,0))
        info.compress_type=ZIP_DEFLATED
        info.external_attr=0o644<<16
        archive.writestr(info,path.read_bytes())
    info=ZipInfo('SHA256SUMS.txt',(2026,10,29,0,0,0))
    info.compress_type=ZIP_DEFLATED
    info.external_attr=0o644<<16
    archive.writestr(info,checksums)
with ZipFile(OUTPUT) as archive:
    assert archive.testzip() is None
    assert len(archive.namelist()) == len(set(archive.namelist()))
    assert not any('_zhp_auth' in name or 'vars.inc.php' in name or name.endswith('.sql') for name in archive.namelist())
print(OUTPUT, round(OUTPUT.stat().st_size / 1024**2, 2), 'MiB', hashlib.sha256(OUTPUT.read_bytes()).hexdigest())

with ZipFile(OUTPUT) as full:
    buckets=[[],[]];sizes=[0,0]
    for info in sorted(full.infolist(),key=lambda info:info.compress_size,reverse=True):
        index=min(range(2),key=lambda i:sizes[i])
        buckets[index].append(info)
        sizes[index]+=info.compress_size
    merged={}
    for index,items in enumerate(buckets,1):
        part=OUTPUT.with_name(OUTPUT.stem+'-part'+str(index)+'.zip')
        with ZipFile(part,'w',ZIP_DEFLATED,compresslevel=9) as archive:
            for info in sorted(items,key=lambda info:info.filename):archive.writestr(copy.copy(info),full.read(info))
        with ZipFile(part) as archive:
            assert archive.testzip() is None
            for name in archive.namelist():
                assert name not in merged
                merged[name]=hashlib.sha256(archive.read(name)).hexdigest()
        print(part,round(part.stat().st_size/1024**2,2),'MiB')
    assert merged=={name:hashlib.sha256(full.read(name)).hexdigest() for name in full.namelist()}

archives=sorted(OUTPUT.parent.glob('lifepodrostka-netcat-v*.zip'))
(OUTPUT.parent/'SHA256SUMS.txt').write_text(''.join(hashlib.sha256(path.read_bytes()).hexdigest()+'  '+path.name+'\n' for path in archives))
