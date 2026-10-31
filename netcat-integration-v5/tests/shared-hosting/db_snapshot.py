import hashlib
import json
import os
from pathlib import Path
import re
import sys

import pymysql

root = Path(os.environ['NETCAT_QA_OUTPUT']).resolve()
connection = pymysql.connect(host=os.environ['NETCAT_REPRO_DB_ADDRESS'], user='root',
                             database=os.environ.get('NETCAT_REPRO_DB_NAME','lifepodr'), charset='utf8mb4', autocommit=True,
                             ssl_disabled=True)
cursor = connection.cursor()
cursor.execute('SHOW TABLES')
tables = [row[0] for row in cursor.fetchall()]
result = {}
for table in tables:
    cursor.execute('SELECT * FROM `' + table.replace('`', '``') + '`')
    rows = [[None if value is None else str(value) for value in row] for row in cursor.fetchall()]
    rows.sort(key=lambda row: json.dumps(row, ensure_ascii=False))
    result[table] = hashlib.sha256(json.dumps(rows, ensure_ascii=False, separators=(',', ':')).encode()).hexdigest()

mode = sys.argv[1] if len(sys.argv) > 1 else 'before'
if mode == 'before':
    (root / 'database-before.json').write_text(json.dumps(result, indent=2))
    print('Database baseline recorded:', len(result), 'tables; no data values printed.')
else:
    expected = json.loads((root / 'database-before.json').read_text())
    changed = [name for name, digest in expected.items() if result.get(name) != digest]
    relevant = [name for name in expected if name in ['Class', 'Field', 'Template', 'Sub_Class', 'Subdivision', 'Catalogue'] or re.fullmatch(r'Message\d+', name)]
    failed = [name for name in changed if name in relevant]
    report = {'total_original_tables': len(expected), 'content_tables_compared': len(relevant),
              'content_mismatches': failed, 'all_changed_table_names': changed,
              'new_table_names': sorted(set(result) - set(expected))}
    (root / 'database-restore-comparison.json').write_text(json.dumps(report, indent=2))
    print(json.dumps(report))
