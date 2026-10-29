from pathlib import Path
import json,shutil,subprocess,re,copy,html
from bs4 import BeautifulSoup

R=Path(__file__).resolve().parents[1]
D=R/'files/netcat_template/template/life_teen'
B=R/'build'
OLD='/netcat_template/template/zhizn_podrostka/'
ASSET='/netcat_template/template/life_teen/'
def convert(value):
 if isinstance(value,str):return value.replace(OLD,ASSET).replace('zhp_','life_')
 if isinstance(value,list):return [convert(x) for x in value]
 if isinstance(value,dict):return {convert(k):convert(v) for k,v in value.items()}
 return value
m=convert(json.loads((B/'source-manifest.json').read_text()));m['version']=5
C=R/'components'
if C.exists():shutil.rmtree(C)
shutil.copytree(B/'source-components',C)
for folder in list(C.iterdir()):
 if folder.name!=convert(folder.name):folder.rename(C/convert(folder.name))
for p in C.rglob('*.html'):p.write_text(convert(p.read_text()))
source=B/'source-layout'
for name in ['Header.html','Footer.html','function.inc.php.txt','regions-widget.html']:
 (D/name).write_text(convert((source/name).read_text()))
(D/'Header.html').write_text((D/'Header.html').read_text().replace('$life_mobile','$mobile_menu').replace('$life_menu','$h_menu'))
(D/'js/forms.js').write_text((source/'forms.js').read_text())
settings="<?php\n\n$template_settings = $nc_core->subdivision->get_template_settings($sub);\n$mobile_menu = [];\n$h_menu = [];\n\n"
for variable,prefix in [('mobile_menu','mobile-menu'),('h_menu','header')]:
 for level,list_part,item_part,link_part in [(0,'list','item','link'),(1,'sublist','subitem','sublink'),(2,'subsublist','subsubitem','subsublink')]:
  base=f'${variable}[{level}]'
  settings+=f'''{base}['prefix'] = "<ul class='{prefix}__{list_part}'>";
{base}['suffix'] = "</ul>";
{base}['active'] = "<li class='{prefix}__{item_part}'><a href='%URL' class='{prefix}__{link_part}'>%NAME</a>%NEXT_LEVEL</li>";
{base}['unactive'] = {base}['active'];

'''
settings+='''if (empty($_SESSION['life_form_nonce'])) {
    $_SESSION['life_form_nonce'] = bin2hex(random_bytes(24));
}
if ((int)$sub === (int)$current_catalogue['E404_Sub_ID']) {
    nc_set_http_response_code(404);
}
?>
'''
(D/'Settings.html').write_text(settings)
layouts={'home':'Главная','about':'О проекте','service':'Услуга','blog':'Блог и статья','specialists':'Специалисты и карточка','gallery':'Фотогалерея','contacts':'Контакты'}
for p in D.iterdir():
 if p.is_dir() and p.name not in ['.install','css','js','img','fonts']:shutil.rmtree(p)
for key in layouts:
 (D/key).mkdir(exist_ok=True)
 for name,value in [('Header','%Header\n'),('Footer','%Footer\n'),('Settings','')]: (D/key/(name+'.html')).write_text(value)
m['layouts']=layouts
pages={p['url']:p for p in m['pages']}
service=copy.deepcopy(pages['/podrostkam/trevoga-i-perezhivaniya/']['blocks'])
whom=BeautifulSoup((B/'reference-layout/index.html').read_text(),'html.parser').select('.block-for-whom__item')
for p in list(pages.values()):
 p['old_id']=8 if p['url']=='/regions/' else None
 if p['url'] in ['/nastroiki-proekta/','/materialy/','/testy/','/vebinary/']:del pages[p['url']];continue
 if p['url'].startswith('/podrostkam/') or p['url'] in ['/roditelyam/','/spetsialistam/']:
  p['layout']='service'
  if p['url']!='/podrostkam/trevoga-i-perezhivaniya/':
   p['blocks']=[];p['draft']=True;p['nav']=False
 else:
  p['layout']={'/':'home','/o-nas/':'about','/blog/':'blog','/kontakty/':'contacts','/o-nas/spetsialisty/':'specialists','/o-nas/foto/':'gallery'}.get(p['url'],'root')
for url in ['/policy/','/politika-konfidentsialnosti/']:
 pages[url]['blocks'][0]['records'][0]['text']=json.loads((B/'source-legal.json').read_text())[url]
article=m['collections']['articles']['records'][0]
body=BeautifulSoup(article['text'],'html.parser')
for block in pages['/']['blocks']:
 if block['class']=='life_text':block['records']=[{'text':'<h2>'+html.escape(article['name'])+'</h2><p>'+html.escape(article['intro'])+'</p>'+''.join(str(node) for node in body.select('p')[:2])}]
exec(compile((B/'popup.py').read_text(),'popup.py','exec'))
ready_links={'/podrostkam/':'/podrostkam/trevoga-i-perezhivaniya/','/roditelyam/':'/blog/?category=parents','/spetsialistam/':'/blog/?category=specialists'}
def ready_value(value):
 if isinstance(value,str):
  if value in ready_links:return ready_links[value]
  for old,new in ready_links.items():value=value.replace('href="'+old+'"','href="'+new+'"')
  return value
 if isinstance(value,list):return [ready_value(x) for x in value]
 if isinstance(value,dict):return {key:ready_value(x) for key,x in value.items()}
 return value
for page in pages.values():
 for block in page['blocks']:block['records']=ready_value(block['records'])
for collection in m['collections'].values():collection['records']=ready_value(collection['records'])
for part in [*C.rglob('*.html'),D/'Header.html',D/'Footer.html']:
 part.write_text(ready_value(part.read_text()))
m['pages']=list(pages.values())
(R/'installer').mkdir(exist_ok=True)
(R/'installer/manifest.json').write_text(json.dumps(m,ensure_ascii=False,indent=2))
I=D/'.install'
if I.exists():shutil.rmtree(I)
I.mkdir()
shutil.copy2(R/'installer/manifest.json',I/'manifest.json')
(I/'.htaccess').write_text('Require all denied\nDeny from all\n')
php=['docker','--host=unix:///var/run/docker.sock','exec','netcat-qa-web','php']
subprocess.run(php+[str(B/'strip-comments.php'),str(R)],check=True)
subprocess.run(['node',str(B/'format.mjs'),str(R)],check=True)
subprocess.run(['python3',str(B/'installer.py')],check=True)
subprocess.run(php+[str(B/'strip-comments.php'),str(R),str(R/'files/zhp-install.php')],check=True)
shutil.copy2(R/'files/zhp-install.php',R/'installer/zhp-install.php')
subprocess.run(['node',str(B/'format.mjs'),str(R),'installer'],check=True)
print('Built v5:',len(m['pages']),'sections,',len(layouts)+1,'layouts')
