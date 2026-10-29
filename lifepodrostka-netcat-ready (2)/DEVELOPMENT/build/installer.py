from pathlib import Path
R=Path('native-redo');s=(R/'build/web-installer-shell.txt').read_text()
s+='\n'+(R/'build/installer-functions.txt').read_text()+'\n'+(R/'build/installer-driver.txt').read_text();(R/'installer/zhp-install.php').write_text(s);(R/'files/zhp-install.php').write_text(s)

import hashlib,json
D=R/'files/netcat_template/template/zhizn_podrostka'
hashes={str(p.relative_to(D)):hashlib.sha256(p.read_bytes()).hexdigest() for p in sorted(D.rglob('*')) if p.is_file() and p.name!='hashes.json'}
(D/'.install/hashes.json').write_text(json.dumps(hashes,ensure_ascii=False,indent=2))
