from pathlib import Path
import json
import os

package = Path(__file__).resolve().parents[2]
site = Path(os.environ['NETCAT_TEST_SITE'])
output = Path(os.environ['NETCAT_QA_OUTPUT'])
assert site.parent == Path('/tmp') and site.name.startswith('demopan-') and not site.is_symlink()
assert output.parent == Path('/tmp') and output.name.startswith('demopan-')
text_files = 0
changed_files = 0
for original in (package / 'files').rglob('*'):
    if not original.is_file():
        continue
    target = site / original.relative_to(package / 'files')
    assert target.is_file() and target.resolve().is_relative_to(site.resolve())
    if original.name == '.htaccess' or original.suffix.lower() in ['.html', '.php', '.txt', '.json', '.js', '.css', '.svg']:
        raw = target.read_bytes()
        converted = raw.replace(b'\r\n', b'\n').replace(b'\n', b'\r\n')
        text_files += 1
        if converted != raw:
            target.write_bytes(converted)
            changed_files += 1
report = {'variant': 'CRLF upload', 'text_files': text_files, 'changed_files': changed_files,
          'binary_files_modified': False}
(output / 'transfer-variant.json').write_text(json.dumps(report, indent=2))
print(json.dumps(report))
