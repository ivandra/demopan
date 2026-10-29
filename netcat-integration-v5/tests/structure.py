"""Check source classes, styles and editable component boundaries."""
from pathlib import Path
import json,re,hashlib
from bs4 import BeautifulSoup

root=Path(__file__).resolve().parents[1]
source=root/'build/reference-layout'
design=root/'files/netcat_template/template/life_teen'
manifest=json.loads((root/'installer/manifest.json').read_text())
checks=[]
def check(name,condition,detail=None):
    checks.append({'name':name,'passed':bool(condition),'detail':detail})
    assert condition,(name,detail)

source_classes=set()
for file in source.glob('*.html'):
    for tag in BeautifulSoup(file.read_text(),'html.parser').select('[class]'):
        source_classes.update(tag['class'])

def without_php(text):
    result=[];position=0
    while True:
        start=text.find('<?',position)
        if start<0:return ''.join(result)+text[position:]
        result.append(text[position:start]);index=start+2;quote=None
        while index<len(text):
            char=text[index]
            if quote:
                if char=='\\':index+=2;continue
                if char==quote:quote=None
            elif char in ['"',"'",'`']:quote=char
            elif text[index:index+2]=='?>':index+=2;break
            index+=1
        position=index

new_classes={}
parts=list((root/'components').rglob('*.html'))+list(design.glob('*.html'))
for file in parts:
    original=file.read_text()
    check('no formatter tokens '+str(file.relative_to(root)),not re.search(r'PHPTOKEN\d+END|ZHPRAW\d+END|php-block-\d|nc-part-boundary|NC_FORMAT_END',original))
    text=without_php(original)
    check('no HTML comments '+str(file.relative_to(root)),'<!--' not in text)
    for classes in re.findall(r'\bclass="([^"]*)"',text):
        for name in classes.split():
            if name not in source_classes:new_classes.setdefault(name,[]).append(str(file.relative_to(root)))
check('all markup classes exist in supplied HTML',not new_classes,new_classes)
record_classes=set()
def visit(value):
    if isinstance(value,str):
        for classes in re.findall(r"class=[\"\']([^\"\']+)",value):record_classes.update(classes.split())
    elif isinstance(value,list):
        for item in value:visit(item)
    elif isinstance(value,dict):
        for item in value.values():visit(item)
visit(manifest)
check('all default record classes exist in supplied HTML',not(record_classes-source_classes),sorted(record_classes-source_classes))
source_style=(root.parent/'zhizn-podrostka/zhizn-podrostka/style.css').read_text()
style=(design/'style.css').read_text()
strip_fonts=lambda value:re.sub(r'url\((?:https://fonts.gstatic.com/[^)]+|fonts/[^)]+)\)','url(FONT)',value)
check('source CSS rules unchanged',strip_fonts(source_style)==strip_fonts(style))
check('source common.js unchanged',(source/'js/common.js').read_bytes()==(design/'js/common.js').read_bytes())
check('no added stylesheet',not(design/'css/native.css').exists())
check('only source layout markup',not re.search(r'<main|zhp-layout|Тестовая версия сайта',(design/'Header.html').read_text()))
fields=sum(len(c['fields']) for c in manifest['classes'].values() if not c.get('existing'))
check('reduced fields',fields==74,fields)
for key in ['life_parent_group','life_first_step','life_socials','life_subscription','life_calculator','life_popup']:
    folder=root/'components'/key
    check('static block in prefix '+key,not manifest['classes'][key]['fields'] and not(folder/'RecordTemplate.html').read_text().strip() and not(folder/'FormSuffix.html').read_text().strip())
check('video has four fields',len(manifest['classes']['life_video']['fields'])==4)
for key in manifest['layouts']:
    check('inherited layout '+key,(design/key/'Header.html').read_text().strip()=='%Header' and (design/key/'Footer.html').read_text().strip()=='%Footer')
(root/'tests/structure-results.json').write_text(json.dumps({'passed':True,'fields':fields,'checks':checks},ensure_ascii=False,indent=2))
print('PASS',len(checks),'structural checks; fields',fields)
