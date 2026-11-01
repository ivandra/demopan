from pathlib import Path
import hashlib
import json
import os
import shutil
import subprocess
import sys
import tempfile
import zipfile

package = Path(__file__).resolve().parents[2]
repo = package.parent
prefix = package.relative_to(repo).as_posix() + '/files/'


def digest(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


expected = {p.relative_to(package / 'files').as_posix(): digest(p)
            for p in (package / 'files').rglob('*') if p.is_file()}
with tempfile.TemporaryDirectory(prefix='demopan-git-upload-') as scratch:
    scratch = Path(scratch)
    index = subprocess.check_output(['git', '-C', str(repo), 'rev-parse', '--git-path', 'index'], text=True).strip()
    index = Path(index) if Path(index).is_absolute() else repo / index
    shutil.copyfile(index, scratch / 'index')
    env = os.environ.copy()
    env['GIT_INDEX_FILE'] = str(scratch / 'index')
    paths = subprocess.check_output(['git', '-C', str(repo), 'ls-files', '-z', prefix], env=env)
    export = scratch / 'export'
    export.mkdir()
    subprocess.run(['git', '-C', str(repo), '-c', 'core.autocrlf=true', '-c', 'core.eol=crlf',
                    'checkout-index', '--force', '--stdin', '-z', '--prefix=' + str(export) + '/'],
                   input=paths, env=env, check=True)
    actual_root = export / prefix
    actual = {p.relative_to(actual_root).as_posix(): digest(p)
              for p in actual_root.rglob('*') if p.is_file()}
    assert actual == expected, 'Git payload differs from complete files directory'
    design = actual_root / 'netcat_template/template/life_teen'
    hashes = json.loads((design / '.install/hashes.json').read_text())
    assert all((design / name).is_file() and digest(design / name) == value
               for name, value in hashes.items()), 'Windows checkout changed payload bytes'

report = {'windows_autocrlf_checkout': True, 'payload_files': len(expected),
          'manifest_files': len(hashes), 'all_raw_hashes_match': True,
          'git_files_complete': True}
if len(sys.argv) > 1:
    with zipfile.ZipFile(sys.argv[1]) as archive:
        assert archive.testzip() is None
        contents = {n: hashlib.sha256(archive.read(n)).hexdigest() for n in archive.namelist()}
        assert contents == expected, 'Root upload ZIP differs from complete Git files'
    report['root_upload_zip_matches_git'] = True
print(json.dumps(report, ensure_ascii=False, indent=2))
