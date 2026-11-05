"""Semantic checks against the HTML actually rendered by the isolated browser fixture.
This is a local check, not a substitute for a hosted schema.org / Google result.
"""
import json, os, re
from pathlib import Path
from urllib.parse import urlparse
from bs4 import BeautifulSoup
root=Path(os.environ['NETCAT_QA_OUTPUT'])
assert root.parent==Path('/tmp') and root.name.startswith('demopan-')
checks=[]
def check(name,condition):
    checks.append({'name':name,'ok':bool(condition)})
    assert condition,name
required={'blog':{'Organization','WebSite','CollectionPage','Blog','BlogPosting','BreadcrumbList'},'article':{'BlogPosting','BreadcrumbList'},'article-2':{'BlogPosting'},'article-3':{'BlogPosting'},'faq':{'FAQPage','BreadcrumbList'},'contacts':{'ContactPage','FAQPage','BreadcrumbList'},'service':{'Service','BreadcrumbList'},'empty':{'CollectionPage','ItemList'},'reviews':{'Review','Organization'}}
for name in ['home','about','blog','article','article-2','article-3','faq','contacts','service','empty','reviews']:
    soup=BeautifulSoup((root/(name+'.html')).read_text(),'html.parser')
    scripts=soup.select('script[type="application/ld+json"]')
    check(name+': one JSON-LD script',len(scripts)==1)
    data=json.loads(scripts[0].string)
    check(name+': schema.org context',data['@context']=='https://schema.org')
    graph=data['@graph'];ids=[node['@id'] for node in graph]
    check(name+': unique absolute node IDs',len(ids)==len(set(ids)) and all(urlparse(x).scheme=='https' and ':8080' not in x for x in ids))
    types={t for node in graph for t in (node['@type'] if isinstance(node['@type'],list) else [node['@type']])}
    check(name+': requested types',required.get(name,set())<=types)
    def walk(value):
        if isinstance(value,dict):
            for key,child in value.items():
                check(name+': nonempty '+key,child is not None and child!='' and child!=[] and child!={})
                if key in ('url','item','image','logo','@id') and isinstance(child,str):
                    check(name+': absolute '+key,urlparse(child).scheme in ('http','https') and bool(urlparse(child).hostname))
                check(name+': no invented rating',key not in ('ratingValue','aggregateRating','reviewRating'))
                walk(child)
        elif isinstance(value,list):
            for child in value:walk(child)
    walk(graph)
    faq=[n for n in graph if 'FAQPage' in ([n['@type']] if isinstance(n['@type'],str) else n['@type'])]
    if faq:
        questions=faq[0]['mainEntity'];visible=soup.select('.faq-block__item')
        check(name+': schema matches FAQ count',len(questions)==len(visible))
        check(name+': FAQ names and answers match content',all(q['name']==re.sub(r'\s+',' ',v.select_one('.faq-block__question').get_text(' ',strip=True)).strip() and q['acceptedAnswer']['text']==re.sub(r'\s+',' ',v.select_one('.faq-block__answer').get_text(' ',strip=True)).strip() for q,v in zip(questions,visible)))
    crumbs=[n for n in graph if n['@type']=='BreadcrumbList']
    if crumbs:check(name+': ordered breadcrumb positions',[x['position'] for x in crumbs[0]['itemListElement']]==list(range(1,len(crumbs[0]['itemListElement'])+1)))
    if name.startswith('article'):
        post=next(n for n in graph if n['@type']=='BlogPosting')
        check(name+': headline matches H1',post['headline']==soup.h1.get_text(' ',strip=True))
        check(name+': article body matches visible article',all(part in post['articleBody'] for part in [soup.select_one('.article-page__text').get_text(' ',strip=True)] if part))
        check(name+': no article list falsely identifies detail as collection','CollectionPage' not in types)
    if name=='contacts':check('contact FAQ is a section',any(n.get('hasPart')=={'@id':faq[0]['@id']} for n in graph))
    if name=='faq':check('dedicated FAQ page carries mainEntity',faq[0]['@id'].endswith('#webpage'))
    if name=='empty':check('empty service category has no invented Service','Service' not in types)
report={'checks':len(checks),'failures':[x for x in checks if not x['ok']],'pages':len(required)+2,'external_schema_org':'blocked: proxy CONNECT 403','external_google_rich_results':'blocked: proxy CONNECT 403'}
(root/'schema-results.json').write_text(json.dumps(report,indent=2))
print(json.dumps(report))
