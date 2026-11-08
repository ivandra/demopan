"""Check service JSON-LD against visible content of every prepared page."""
import json,os,re
from pathlib import Path
from urllib.parse import urlparse
from bs4 import BeautifulSoup
root=Path(os.environ['NETCAT_QA_OUTPUT']);assert root.parent==Path('/tmp') and root.name.startswith('demopan-')
checks=[]
def check(name,ok):
 checks.append({'name':name,'ok':bool(ok)});assert ok,name
normalize=lambda s:re.sub(r'\s+',' ',s).strip()
paths=list(root.glob('service-*.html'));check('all 18 prepared pages captured',len(paths)==18)
for p in paths:
 soup=BeautifulSoup(p.read_text(),'html.parser');scripts=soup.select('script[type="application/ld+json"]');check(p.stem+': one JSON-LD script',len(scripts)==1)
 data=json.loads(scripts[0].string);graph=data['@graph'];services=[n for n in graph if n['@type']=='Service'];check(p.stem+': one service',len(services)==1)
 node=services[0];check(p.stem+': service name matches visible H1',normalize(node['name'])==normalize(soup.select_one('.contacts-page h1').get_text(' ',strip=True)))
 check(p.stem+': service description matches banner',normalize(node['description'])==normalize(soup.select_one('.contacts-page .hero__description').get_text(' ',strip=True)))
 check(p.stem+': canonical service URL is absolute',urlparse(node['url']).scheme=='https' and ':8080' not in node['url'])
 crumbs=[n for n in graph if n['@type']=='BreadcrumbList'];check(p.stem+': one breadcrumb graph',len(crumbs)==1)
 names=[x['name'] for x in crumbs[0]['itemListElement']];visible=[n.get_text(' ',strip=True) for n in soup.select('.project-breadcrumbs li > a,.project-breadcrumbs li > span')]
 check(p.stem+': breadcrumbs match visible hierarchy',names==visible)
 check(p.stem+': no raw regional tokens',not re.search(r'\[(?:inCity|phone|city|address)\]',json.dumps(data,ensure_ascii=False)))
report={'checks':len(checks),'pages':len(paths),'failures':[c for c in checks if not c['ok']]};(root/'service-schema-results.json').write_text(json.dumps(report,indent=2));print(json.dumps(report))
