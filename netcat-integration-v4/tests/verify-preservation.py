"""Called immediately after activation, before intentional native editor tests."""
import os
import json
import hashlib
import pymysql

snapshot=json.load(open(os.environ['NETCAT_PRESERVATION_SNAPSHOT']))
connection=pymysql.connect(host='127.0.0.1',port=23307,user='root',database='lifepodr')
cursor=connection.cursor()
for table,original in snapshot.items():
    columns=[column for column in original['columns'] if column!='Subdivision_ID']
    ids=original.get('ids')
    condition='' if ids is None else (' WHERE Message_ID IN ('+','.join(map(str,ids))+')' if ids else ' WHERE 0')
    cursor.execute('SELECT '+','.join('`'+column+'`' for column in columns)+' FROM `'+table+'`'+condition+' ORDER BY Message_ID')
    digest=hashlib.sha256(json.dumps(cursor.fetchall(),default=str,ensure_ascii=False).encode()).hexdigest()
    assert digest==original['hash'], 'Installation changed existing values in '+table
print('PASS Preserved original records before native editor tests: '+str(len(snapshot))+' tables')
