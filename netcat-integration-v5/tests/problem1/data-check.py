"""Read-only checks for an isolated, updated QA database and source snapshot."""
import hashlib
import json
import os
from pathlib import Path
import re
import pymysql

output = Path(os.environ['NETCAT_QA_OUTPUT'])
site = Path(os.environ['NETCAT_TEST_SITE'])
source = Path(os.environ['NETCAT_TEST_SOURCE'])
assert output.parent == Path('/tmp') and output.name.startswith('demopan-')
assert site.parent == Path('/tmp') and site.name.startswith('demopan-')
assert os.environ['NETCAT_REPRO_DB_NAME'] in ['lifepodr', 'lifepodr83']
connection = pymysql.connect(host=os.environ['NETCAT_REPRO_DB_ADDRESS'], user='root', database=os.environ['NETCAT_REPRO_DB_NAME'], charset='utf8mb4', ssl_disabled=True)
cursor = connection.cursor()
baseline = json.loads((output/'database-before.json').read_text())
changed = []
for table, expected in baseline.items():
    cursor.execute('SELECT * FROM `' + table + '`')
    rows = [[None if v is None else str(v) for v in row] for row in cursor.fetchall()]
    rows.sort(key=lambda row: json.dumps(row, ensure_ascii=False))
    digest = hashlib.sha256(json.dumps(rows, ensure_ascii=False, separators=(',', ':')).encode()).hexdigest()
    if digest != expected:
        changed.append(table)
allowed = {'Class', 'Field', 'Template', 'Subdivision', 'Classificator', 'Message289', 'Class_Multipurpose_Template_Cache', 'Class_StyleCache', 'Logging', 'Session', 'Version', 'Version_Changeset', 'Version_ContentHistory', 'Version_File'}
unexpected = sorted(set(changed)-allowed)
assert not unexpected, unexpected
files = json.loads((output/'files-before.json').read_text())
# Native templates/runtime change intentionally; uploaded media and region widget must not.
preserved = {p:h for p,h in files.items() if p.startswith(('netcat_files/', 'netcat_template/widget/regions/'))}
file_failures = [p for p,h in preserved.items() if not (site/p).is_file() or hashlib.sha256((site/p).read_bytes()).hexdigest()!=h]
assert not file_failures, file_failures[:5]
old_snapshot = source/'netcat_files/life_teen_v5'
snapshot_files = [p for p in old_snapshot.rglob('*') if p.is_file()]
snapshot_failures = [str(p.relative_to(source)) for p in snapshot_files if not (site/p.relative_to(source)).is_file() or hashlib.sha256(p.read_bytes()).digest()!=hashlib.sha256((site/p.relative_to(source)).read_bytes()).digest()]
assert not snapshot_failures, snapshot_failures[:5]
cursor.execute('SELECT Checked FROM Catalogue WHERE Catalogue_ID=1')
assert cursor.fetchone()[0] == 0
report = {'unexpected_changed_tables': unexpected, 'preserved_media_and_region_files': len(preserved), 'media_mismatches': file_failures, 'original_installation_backup_files': len(snapshot_files), 'original_installation_backup_mismatches': snapshot_failures, 'site_checked': 0}
(output/'updated-data-comparison.json').write_text(json.dumps(report, indent=2))
print(json.dumps(report))
