from pathlib import Path
import json
import os
import subprocess
import sys

root = Path(os.environ['NETCAT_QA_OUTPUT']).resolve()
subprocess.run([sys.executable, str(root / 'db_snapshot.py'), 'compare'], check=True)
report = json.loads((root / 'database-restore-comparison.json').read_text())
assert not report['content_mismatches'], report['content_mismatches']
assert set(report['all_changed_table_names']) <= {'Logging', 'Session'}, report['all_changed_table_names']
assert not report['new_table_names'], report['new_table_names']
baseline = (root / 'files-before.json').read_bytes()
code = '''$baseline=json_decode(stream_get_contents(STDIN),true);$bad=[];$snapshot_bad=[];
foreach($baseline as $relative=>$hash){
  $current='/site/'.$relative;$backup='/site/netcat_files/life_teen_v5/snapshot/files/'.$relative;
  if(!is_file($current)||hash_file('sha256',$current)!==$hash)$bad[]=$relative;
  if(!is_file($backup)||hash_file('sha256',$backup)!==$hash)$snapshot_bad[]=$relative;
}
echo json_encode(['files'=>count($baseline),'restore_mismatches'=>$bad,'snapshot_mismatches'=>$snapshot_bad]);'''
raw = subprocess.check_output(['docker', '--host=unix:///var/run/docker.sock', 'exec', '-i',
                              os.environ.get('NETCAT_REPRO_WEB_CONTAINER','demopan-repro-web'), 'php', '-r', code], input=baseline)
files = json.loads(raw)
(root / 'files-restore-comparison.json').write_text(json.dumps(files, indent=2))
assert not files['restore_mismatches'], files['restore_mismatches'][:10]
assert not files['snapshot_mismatches'], files['snapshot_mismatches'][:10]
print('PASS Exact restored content:', report['content_tables_compared'], 'tables and', files['files'], 'files')
