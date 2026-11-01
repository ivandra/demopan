from pathlib import Path
import hashlib
import json
import shutil
import sys
import zipfile

root = Path(__file__).resolve().parents[1]
theme = root / 'files/netcat_template/template/life_teen'
update = root / 'updates/v5.2'
configuration = json.loads((update / 'changes.json').read_text())
output = Path(sys.argv[1]).resolve()
output.mkdir(parents=True, exist_ok=True)
stage = output / 'stage'
if stage.exists():
    shutil.rmtree(stage)
stage.mkdir()

hashes = {
    p.relative_to(theme).as_posix(): hashlib.sha256(p.read_bytes()).hexdigest()
    for p in sorted(theme.rglob('*'))
    if p.is_file() and p.relative_to(theme).as_posix() != '.install/hashes.json'
}
(theme / '.install/hashes.json').write_text(json.dumps(hashes, ensure_ascii=False, indent=2) + '\n')
manifest = {'version': configuration['version'], 'theme': {}, 'components': {},
            'empty_pages': configuration['empty_pages'], 'hashes': {}}


def copy(source, relative):
    target = stage / relative
    target.parent.mkdir(parents=True, exist_ok=True)
    shutil.copyfile(source, target)
    manifest['hashes'][relative] = hashlib.sha256(target.read_bytes()).hexdigest()
    return relative


for relative in configuration['theme'] + ['.install/hashes.json']:
    manifest['theme'][relative] = copy(theme / relative, 'theme/' + relative)
manifest['runtime'] = 'theme/function.inc.php.txt'
for key, parts in configuration['components'].items():
    manifest['components'][key] = {}
    for part in parts:
        source = theme / '.install/components' / key / (part + '.html')
        relative = 'components/' + key + '/' + part + '.html'
        manifest['components'][key][part] = copy(source, relative)
        manifest['theme']['.install/components/' + key + '/' + part + '.html'] = relative
(stage / 'manifest.json').write_text(json.dumps(manifest, ensure_ascii=False, indent=2) + '\n')
(stage / '.htaccess').write_text('Require all denied\nDeny from all\n')
archive = output / 'lifepodrostka-netcat-v5.2-update.zip'
with zipfile.ZipFile(archive, 'w', zipfile.ZIP_DEFLATED, compresslevel=9) as z:
    z.write(update / 'zhp-update.php', 'zhp-update.php')
    for path in sorted(stage.rglob('*')):
        if path.is_file():
            z.write(path, 'netcat_template/template/life_teen/.updates/v5.2/' + path.relative_to(stage).as_posix())
    for name in ['START_HERE_RU.md', 'ANSWERS_RU.md', 'QA_RU.md', 'results.json']:
        guide = update / name
        if guide.is_file():
            z.write(guide, name)
with zipfile.ZipFile(archive) as z:
    assert z.testzip() is None
    assert 'zhp-update.php' in z.namelist()
    assert all(not p.startswith('/') and '..' not in Path(p).parts for p in z.namelist())
print(json.dumps({'archive': str(archive), 'bytes': archive.stat().st_size,
                  'sha256': hashlib.sha256(archive.read_bytes()).hexdigest(),
                  'payload_files': len(manifest['hashes']),
                  'component_count': len(manifest['components'])}, ensure_ascii=False))
