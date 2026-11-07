from pathlib import Path
import hashlib,json,shutil,subprocess,sys,zipfile
root=Path(__file__).resolve().parents[1]
theme=root/'files/netcat_template/template/life_teen';update=root/'updates/v5.5';source=root/'build/source-components'
for p in source.rglob('*.html'):
    target=theme/'.install/components'/p.relative_to(source);target.parent.mkdir(parents=True,exist_ok=True);shutil.copyfile(p,target)
for name in ['Header.html','Footer.html']:
    shutil.copyfile(root/'build/source-layout'/name,theme/name)
parts=['source-layout/function.inc.php.txt','problem1-runtime.php.txt','editorial-runtime.php.txt','schema-runtime.php.txt','native-runtime.php.txt']
(theme/'function.inc.php.txt').write_text('\n'.join((root/'build'/p).read_text() for p in parts))
changed=subprocess.check_output(['git','diff','--name-only','f8e43faf','--',str(source)],cwd=root.parent,text=True).splitlines()
changed+=subprocess.check_output(['git','ls-files','--others','--exclude-standard','--',str(source)],cwd=root.parent,text=True).splitlines()
components={}
for name in changed:
    p=root.parent/name
    if p.suffix=='.html' and p.exists():components.setdefault(p.parent.name,[]).append(p.stem)
components={key:sorted(set(names)) for key,names in sorted(components.items())}
config={'version':'5.5.0','components':components,'theme':['Header.html','Footer.html','function.inc.php.txt','css/content-editor.css','js/content-editor.js']}
(update/'changes.json').write_text(json.dumps(config,ensure_ascii=False,indent=2)+'\n')
hashes={p.relative_to(theme).as_posix():hashlib.sha256(p.read_bytes()).hexdigest() for p in sorted(theme.rglob('*')) if p.is_file() and p.relative_to(theme).as_posix()!='.install/hashes.json'}
(theme/'.install/hashes.json').write_text(json.dumps(hashes,ensure_ascii=False,indent=2)+'\n')
output=Path(sys.argv[1]).resolve();output.mkdir(parents=True,exist_ok=True);stage=output/'stage'
if stage.exists():shutil.rmtree(stage)
stage.mkdir()
manifest={'version':'5.5.0','theme':{},'components':{},'hashes':{},'runtime':'theme/function.inc.php.txt'}
def copy(p,relative):
    target=stage/relative;target.parent.mkdir(parents=True,exist_ok=True);shutil.copyfile(p,target);manifest['hashes'][relative]=hashlib.sha256(target.read_bytes()).hexdigest();return relative
for name in config['theme']+['.install/hashes.json']:
    manifest['theme'][name]=copy(theme/name,'theme/'+name)
for key,names in components.items():
    manifest['components'][key]={}
    for name in names:
        path=copy(source/key/(name+'.html'),'components/'+key+'/'+name+'.html');manifest['components'][key][name]=path;manifest['theme']['.install/components/'+key+'/'+name+'.html']=path
for name in ['migration.php','updater-core.php']:copy(update/name,name)
(stage/'manifest.json').write_text(json.dumps(manifest,ensure_ascii=False,indent=2)+'\n');(stage/'.htaccess').write_text('Require all denied\nDeny from all\n')
archive=output/'lifepodrostka-netcat-v5.5-update.zip'
with zipfile.ZipFile(archive,'w',zipfile.ZIP_DEFLATED,compresslevel=9) as z:
    z.write(update/'zhp-update.php','zhp-update.php')
    for p in sorted(stage.rglob('*')):
        if p.is_file():z.write(p,'netcat_template/template/life_teen/.updates/v5.5/'+p.relative_to(stage).as_posix())
    for name in ['START_HERE_RU.md','EDITOR_GUIDE_RU.md','REPORT_RU.md','results.json']:
        if (update/name).is_file():z.write(update/name,name)
with zipfile.ZipFile(archive) as z:assert z.testzip() is None
print(json.dumps({'archive':str(archive),'bytes':archive.stat().st_size,'sha256':hashlib.sha256(archive.read_bytes()).hexdigest(),'components':len(components),'payload_files':len(manifest['hashes'])}))
