from pathlib import Path
import shutil
import subprocess
import os

ROOT = Path(__file__).resolve().parents[1]
shutil.copy2(ROOT/'build/input-native.js',ROOT/'files/netcat_template/template/zhizn_podrostka/js/native.js')
subprocess.run(['python3', str(ROOT/'build/native.py')], check=True)
php = ['php'] if shutil.which('php') else ['docker','--host=unix:///var/run/docker.sock','exec','netcat-qa-web','php']
subprocess.run(php+[str(ROOT/'build/strip-comments.php'),str(ROOT)],check=True)
subprocess.run(['node',str(ROOT/'build/format.mjs'),str(ROOT)],check=True)
subprocess.run(['python3',str(ROOT/'build/installer.py')],check=True)
subprocess.run(php+[str(ROOT/'build/strip-comments.php'),str(ROOT),str(ROOT/'files/zhp-install.php')],check=True)
shutil.copy2(ROOT/'files/zhp-install.php',ROOT/'installer/zhp-install.php')
