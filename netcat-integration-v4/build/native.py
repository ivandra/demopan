from pathlib import Path
from bs4 import BeautifulSoup
import json,shutil,copy,re,html
ROOT=Path(__file__).resolve().parents[1];SRC=ROOT.parent/'zhizn-podrostka/zhizn-podrostka';OLD=ROOT/'files/netcat_template/template/zhizn_podrostka';OUT=ROOT/'files/netcat_template/template/zhizn_podrostka';ASSET='/netcat_template/template/zhizn_podrostka/'
OUT.mkdir(parents=True,exist_ok=True)
if (ROOT/'build/reference-layout').exists():SRC=ROOT/'build/reference-layout'
if not OLD.exists():OLD=OUT
for d in ['img','fonts','css','js']:
 if OLD!=OUT and (OLD/d).exists():shutil.copytree(OLD/d,OUT/d,dirs_exist_ok=True)
for f in ['style.css','video.mp4']:
 source=(OLD if (OLD/f).exists() else SRC)/f
 if source.resolve()!=(OUT/f).resolve():shutil.copy2(source,OUT/f)
old=json.loads((ROOT/'build/source-content.json' if (ROOT/'build/source-content.json').exists() else OLD/'inc/manifest.json').read_text());M={'version':2,'classes':{},'pages':[], 'collections':{}}
TOK={}
def raw(code):
 k='ZHPRAW'+str(len(TOK))+'END';TOK[k]=code;return k
def dump(n):
 s=str(n)
 for _ in range(12):
  before=s
  for k,v in TOK.items():s=s.replace(k,v)
  if s==before:break
 return s

def soup(s):return BeautifulSoup(s,'html.parser')
def page(n):return soup((SRC/(n+'.html')).read_text())
def inner(n):return n.decode_contents() if n else ''
def plain(n):return n.get_text(' ',strip=True) if n else ''
def val(n,s):return plain(n.select_one(s))
def V(f,kind='plain',settings=False):
 expr="($cc_settings['"+f+"'] ?? '')" if settings else '$f_'+f
 if kind=='html':expr='shortcode_component((string)'+expr+')'
 elif kind=='plain':expr='htmlspecialchars(shortcode((string)'+expr+"),ENT_QUOTES,'UTF-8')"
 return raw('<?= '+expr+' ?>')
def R(f):
 if f=='regim':
  return raw("<?= nl2br(htmlspecialchars(preg_replace('~<br\\s*/?>~i', \"\\n\", shortcode('[regim]')), ENT_QUOTES, 'UTF-8')) ?>")
 return raw("<?= htmlspecialchars(shortcode('["+f+"]'),ENT_QUOTES,'UTF-8') ?>")
def replace(n,sel,v):
 t=n.select_one(sel)
 if t:t.clear();t.append(v)
 return t

def prepare(node):
 assert node is not None
 n=copy.deepcopy(node)
 for t in [n]+list(n.find_all(True)):
  for a in ['src','poster','href']:
   x=t.get(a)
   if isinstance(x,str) and (x.startswith('img/') or x=='video.mp4'):t[a]=ASSET+x
  if t.get('style'):t['style']=t['style'].replace('img/',ASSET+'img/')
  if t.name=='img':t['alt']=t.get('alt','');t['decoding']='async'
 for t in list(n.find_all(string=True)):
  v=str(t)
  for a,b in [('8 (800) 123-45-67',R('phone')),('+7 (800) 123-45-67',R('phone')),('info@life-podrostka.ru',R('email')),('info@zhizn-podrostka.ru',R('email')),('г. Москва, ул. Примерная, 15',R('city')+', '+R('address')),('Россия, г. Краснодар',R('city')),('Пн-Пт: 10:00 — 20:00',R('regim'))]:v=v.replace(a,b)
  if v!=str(t):t.replace_with(v)
 routes={'О проекте':'/o-nas/','Блог':'/blog/','Статьи':'/blog/','Контакты':'/kontakty/','Подросткам':'/podrostkam/','Родителям':'/roditelyam/','Специалистам':'/spetsialistam/','Все специалисты':'/o-nas/spetsialisty/','Смотреть всех':'/o-nas/spetsialisty/','Вопросы и ответы':'/o-nas/voprosy-i-otvety/','Тесты':'/#quiz','Вебинары':'/#video','Полезные материалы':'/blog/','Советы психологов':'/roditelyam/','Поддержка':'/kontakty/'}
 routes.update({'Все статьи':'/blog/','Все отзывы':'/o-nas/otzyvy/','Смотреть все':'/blog/','Документы':'/o-nas/sertifikaty/','Пользовательское соглашение':'/policy/','Тесты и упражнения':'/#quiz','Вебинары и лекции':'/#video'})
 for a in n.find_all('a'):
  text=plain(a).replace('→','').strip();cl=' '.join(a.get('class',[]));h=a.get('href','')
  if 'burger' in cl or 'mobile-menu__close' in cl:a['href']='#';a['role']='button';continue
  if 'slider-nav__link' in cl:a['href']='#';continue
  if h.startswith('tel:'):a['href']=raw("tel:<?= preg_replace('/[^0-9+]/','',shortcode('[phone]')) ?>");continue
  if h.startswith('mailto:'):a['href']=raw("mailto:<?= htmlspecialchars(shortcode('[email]'),ENT_QUOTES,'UTF-8') ?>");continue
  if h and h!='#':continue
  if 'logo' in cl:a['href']='/'
  elif re.search('политик|персональных данных',text,re.I):a['href']='/politika-konfidentsialnosti/'
  elif text in routes:a['href']=routes[text]
  elif 'подросток' in text.lower():a['href']='/podrostkam/'
  elif 'родитель' in text.lower() and 'групп' not in text.lower():a['href']='/roditelyam/'
  elif 'видео' in text.lower():a['href']='/#video'
  elif 'групп' in text.lower():a['href']=R('max');a['data-zhp-social']=''
  else:a['href']='#consult';a['data-zhp-consult']=''
 for b in n.find_all('button'):
  b['type']=b.get('type','button')
  if re.search('Запис|Подобрать|помощь|Задать',plain(b),re.I) and not b.find_parent('form'):b['data-zhp-consult']=''
 return n

def field(n,label,t='string',required=False):return {'name':n,'label':label,'type':t,'required':required}
def cls(k,name,fields,title,parts=None,parent=None,settings=None):
 M['classes'][k]={'name':name,'fields':fields,'title_field':title,'parent':parent,'settings':settings or {}}
 p=ROOT/'components'/k;p.mkdir(parents=True,exist_ok=True)
 for f,s in (parts or {}).items():(p/(f+'.html')).write_text(dump(s))
 return k

def single(k,name,fields,title,node):return cls(k,name,fields,title,{'FormPrefix':'<?= $f_AdminCommon ?>','RecordTemplate':'<?= $f_AdminButtons ?>'+dump(node),'FormSuffix':''})
def split(n,c,card):
 c.clear();c.append(raw('RECORD_SPLIT'));a,b=dump(n).split('RECORD_SPLIT');return {'FormPrefix':'<?= $f_AdminCommon ?>'+a,'RecordTemplate':'<?= $f_AdminButtons ?>'+dump(card),'FormSuffix':b}
def listing(k,name,fields,title,node,container,item,bindings,parent=None):
 n=prepare(node);c=n.select_one(container);items=n.select(item);assert c and items,(k,container,item);rec=copy.deepcopy(items[0]);rows=[]
 for i in items:
  r={}
  for f,sel,kind in bindings:
   t=i.select_one(sel) if sel else i;r[f]=(t.get('src','') if kind=='file' else t.get('href','') if kind=='href' else inner(t) if kind=='html' else plain(t)) if t else ''
  rows.append(r)
 for f,sel,kind in bindings:
  t=rec.select_one(sel) if sel else rec
  if t:
   if kind in ['file','href']:t['src' if kind=='file' else 'href']=V(f)
   else:t.clear();t.append(V(f,'html' if kind=='html' else 'plain'))
 settings={};values={}
 for sel,f,label in [('h2','heading','Заголовок блока'),('.hero__description','description','Описание блока')]:
  t=n.select_one(sel)
  if t and not any(t in x.descendants for x in items):
   values[f]=inner(t);settings[f]={'caption':label,'type':'textarea','default_value':inner(t)};t.clear();t.append(V(f,'html',True))
 parts=split(n,c,rec);cls(k,name,fields,title,parts,parent,settings);return rows,values

PAGES={p['url']:{'url':p['url'],'title':p['title'],'old_id':p['old_id'],'nav':p['nav'],'blocks':[]} for p in old['pages']}
def add(url,k,name,rows=None,settings=None):PAGES[url]['blocks'].append({'class':k,'name':name,'records':rows or [],'settings':settings or {}})
H=page('index');A=page('about');S=page('service')
# Schema is explicitly declared. No caption comes from the contents of an object.
cardfields=[field('name','Название',required=True),field('text','Описание','html'),field('img','Изображение или иконка','file')]
cls('zhp_cards','Карточки и преимущества',cardfields,'name',{'FormPrefix':'<?= $f_AdminCommon ?><section class="zhp-text"><div class="wrap">','RecordTemplate':'<?= $f_AdminButtons ?><article><h3><?= htmlspecialchars($f_name,ENT_QUOTES,"UTF-8") ?></h3><?= shortcode_component($f_text) ?></article>','FormSuffix':'</div></section>'})
CARDS={}
for key,title,node,container,item,h,p in [('whom','Для кого',H.select_one('.block-for-whom'),'.block-for-whom__list','.block-for-whom__item','h3','p'),('steps','Этапы',H.select_one('.steps'),'.steps__stages','.steps__stage','h3','p'),('offer','Что предлагает проект',H.select_one('.what-we-offer'),'.what-we-offer__list','.what-we-offer__item','h3','p'),('values','Ценности проекта',A.select_one('.our-values'),'.specialist-page__methods-list','.specialist-page__method-card','h3','p'),('peoples','Подросткам и родителям',A.select_one('.peoples'),'.peoples__list','.peoples__item','h3','.peoples__item-data'),('way','Как проходит работа',A.select_one('.way'),'.steps__stages','.steps__stage','h3','p'),('reasons','Причины трудностей',S.select_one('.steps'),'.steps__stages','.steps__stage','h3','p'),('help','Как мы помогаем',S.select_one('.help-block'),'.help-block__list','.help-block__item','h3','p')]:
 CARDS[key]=listing('zhp_cards_'+key,title,[],'name',node,container,item,[('name',h,'plain'),('text',p,'html'),('img','img','file')],parent='zhp_cards')
# Banner fields describe the object rather than individual text nodes.
bannerfields=[field('name','Заголовок','html',True),field('label','Надзаголовок'),field('text','Описание','html'),field('img','Основное изображение','file'),field('button','Текст первой кнопки'),field('link','Ссылка первой кнопки'),field('button2','Текст второй кнопки'),field('link2','Ссылка второй кнопки')]
cls('zhp_banner','Баннер страницы',bannerfields,'name',{'FormPrefix':'<?= $f_AdminCommon ?>','RecordTemplate':'<?= $f_AdminButtons ?><section class="zhp-text"><div class="wrap"><h1><?= shortcode_component($f_name) ?></h1><?= shortcode_component($f_text) ?></div></section>','FormSuffix':''})
BANNERS={}
for key,title,node,h,psel,imgsel in [('home','Главный баннер',H.select_one('.hero'),'h1','.hero__description','.hero__image img'),('about','О проекте — первый экран',A.select('.about')[0],'h1','.hero__description','.about__image img'),('about_text','О проекте — вступление',A.select('.about')[1],'h1','.hero__description','.about__image img'),('specialists','Заголовок списка специалистов',page('specialists').select_one('.specialists-hero'),'h1','.hero__description','img'),('gallery','Заголовок галереи',page('gallery').select_one('.gallery-hero'),'h1','.hero__description','img'),('service','Баннер услуги',S.select_one('.contacts-page'),'h1','.hero__description','.contacts-page__image img')]:
 n=prepare(node);r={'name':inner(n.select_one(h)),'text':inner(n.select_one(psel)),'label':val(n,'.hero__label')};replace(n,h,V('name','html'));replace(n,psel,V('text','html'));replace(n,'.hero__label',V('label'))
 im=n.select_one(imgsel)
 if not im:ims=[x for x in n.find_all('img') if x.get('src','').endswith('.png')];im=ims[-1] if ims else None
 if im:r['img']=im['src'];im['src']=V('img');im['loading']='eager'
 if key=='about_text':
  for x in n.find_all('h1'):x.name='h2'
 for i,a in enumerate([x for x in n.find_all('a') if 'button' in ' '.join(x.get('class',[]))][:2]):
  f='button' if i==0 else 'button2';l='link' if i==0 else 'link2';r[f]=plain(a);r[l]=a.get('href','');a.clear();a.append(V(f));a['href']=V(l);a.attrs.pop('data-zhp-consult',None)
 cls('zhp_banner_'+key,title,[],'name',{'FormPrefix':'<?= $f_AdminCommon ?>','RecordTemplate':'<?= $f_AdminButtons ?>'+dump(n),'FormSuffix':''},parent='zhp_banner');BANNERS[key]=r
n=prepare(H.select_one('.founder'));r={'fio':plain(n.h2),'position':val(n,'.hero__label'),'img':n.img['src'],'bio':inner(n.select_one('.hero__description')),'achievements':inner(n.select_one('.founder__achievements'))};n.img['src']=V('img');replace(n,'h2',V('fio'));replace(n,'.hero__label',V('position'));replace(n,'.hero__description',V('bio','html'));replace(n,'.founder__achievements',V('achievements','html'))
single('zhp_founder','Основатель проекта',[field('fio','ФИО',required=True),field('position','Должность / подпись'),field('img','Фотография','file'),field('bio','Описание','html'),field('achievements','Достижения','html')],'fio',n);FOUNDER=r
n=prepare(H.select_one('.video-card'));r={'name':inner(n.h2),'text':inner(n.select_one('.hero__description')),'link':ASSET+'video.mp4'};replace(n,'h2',V('name','html'));replace(n,'.hero__description',V('text','html'));n['id']='video'
for a in n.find_all('a'):
 if 'video.mp4' in a.get('href',''):a['href']=V('link')
for t in n.find_all('video'):t['src']=V('link');t['controls']='';t['preload']='metadata'
single('zhp_video','Видео',[field('name','Заголовок','html'),field('text','Описание','html'),field('link','Ссылка на видео')],'name',n);VIDEO=r
PROGS={}
for key,node in [('home',H.select_one('.program')),('service',S.select_one('.program'))]:
 n=prepare(node);r={'name':inner(n.h2),'label':val(n,'.hero__label'),'text':inner(n.select_one('.hero__description'))};replace(n,'h2',V('name','html'));replace(n,'.hero__label',V('label'));replace(n,'.hero__description',V('text','html'))
 for i,t in enumerate(n.select('.program__subtitle')):
  f=['directions_title','support_title'][i];r[f]=plain(t);t.clear();t.append(V(f))
 for i,t in enumerate(n.select('.program__list')):
  f=['directions','support'][i];r[f]=inner(t);t.clear();t.append(V(f,'html'))
 fields=[field('name','Заголовок','html'),field('label','Надзаголовок'),field('text','Вступление','html'),field('directions_title','Заголовок направлений'),field('directions','Направления программы','html'),field('support_title','Заголовок сопровождения'),field('support','Содержание сопровождения','html')];single('zhp_program' if key=='home' else 'zhp_program_service','Программа' if key=='home' else 'Содержание услуги',fields,'name',n);PROGS[key]=r
SINGLES={}
for key,title,selector,h,p in [('parent_group','Группа для родителей','.parents-group','h3','.parents-group__description'),('first_step','Как связаться','.first-step','h2','.hero__description'),('socials','Социальные сети','.socials','h2','.hero__description')]:
 n=prepare(H.select_one(selector));r={'name':inner(n.select_one(h)),'text':inner(n.select_one(p))};replace(n,h,V('name','html'));replace(n,p,V('text','html'))
 if key=='socials':
  for a in n.find_all('a'):
   k=next((x for x in ['youtube','vk','instagram','telegram'] if x in plain(a).lower()),None)
   if k:a['href']=R(k);a['data-zhp-social']='';a.attrs.pop('data-zhp-consult',None)
 single('zhp_'+key,title,[field('name','Заголовок','html'),field('text','Описание','html')],'name',n);SINGLES[key]=r
# Specialists: base component plus native templates. All outputs share native objects.
fields=[field('fio','ФИО',required=True),field('position','Должность / специализация'),field('img','Фотография','file'),field('intro','Краткое описание','text'),field('bio','О специалисте','html'),field('personalquote','Цитата','text'),field('stats','Город, формат и опыт','html'),field('details','Сведения о приёме','html'),field('methods','Методы и подходы','html'),field('education','Образование','html'),field('diploms','Документы и сертификаты','multifile'),field('category','Категории фильтра (через пробел)')]
specs=[{'fio':r['name'],'position':r['role'],'img':r.get('portrait') or r['image'],'intro':r['intro'],'bio':r['bio'],'personalquote':r.get('quote',''),'stats':r.get('stats',''),'details':r.get('top',''),'methods':r['methods'],'education':r['education'],'category':r['category'],'Keyword':r['Keyword']} for r in old['collections']['specialists']['records']]
n=prepare(page('specialists').select_one('.specialists-block'));c=n.select_one('.specialists-block__list');card=copy.deepcopy(c.select_one('.specialists-block__card'));replace(card,'h3',V('fio'));replace(card,'.specialists-block__card-role',V('position'));replace(card,'.specialists-block__card-description',V('intro'));card.img['src']=V('img');card.img['alt']=V('fio');card['data-specialist-category']=V('category');card.a['href']=raw('<?= $fullLink ?>');card.a.attrs.pop('data-zhp-consult',None)
for i,b in enumerate(n.select('.specialists-block__filter')):b['data-specialist-filter']=['all','psychologist','psychotherapist','family','educator','other'][i]
for b in n.select('.specialists-block__show-more-btn'):b.decompose()
cls('zhp_specialists','Специалисты',fields,'fio',split(n,c,card))
for key,title,node,container,item,h,p in [('home','Специалисты — слайдер',H.select_one('.specialists'),'.swiper-wrapper','.specialists__item','h3','p'),('team','Специалисты — команда',A.select_one('.team'),'.team__list','.team__item','h3','p')]:
 n=prepare(node)
 for h1 in n.find_all('h1'):h1.name='h2'
 c=n.select_one(container);card=copy.deepcopy(c.select_one(item));replace(card,h,V('fio'));replace(card,p,V('position'));card.img['src']=V('img');card.img['alt']=V('fio')
 if card.a:card.a['href']=raw('<?= $fullLink ?>');card.a.attrs.pop('data-zhp-consult',None)
 cls('zhp_specialists_'+key,title,[],'fio',{'Settings':'<?php $ignore_cc=1;$ignore_sub=1; ?>',**split(n,c,card)},parent='zhp_specialists')
n=prepare(page('specialist').select_one('.specialist-page'));replace(n,'h1',V('fio'));replace(n,'.specialist-page__info .hero__label',V('position'));replace(n,'.specialist-page__info .hero__description',V('personalquote'));replace(n,'.specialist-page__stats',V('stats','html'));replace(n,'.specialist-page__details',V('details','html'));n.select_one('.specialist-page__photo img')['src']=V('img')
for sel,f in [('.specialist-page__about','bio'),('.specialist-page__methods','methods'),('.specialist-page__education','education')]:n.select_one(sel).replace_with(V(f,'html'))
n.select_one('.specialist-page__other').decompose();(ROOT/'components/zhp_specialists/RecordTemplateFull.html').write_text('<?= $f_AdminButtons ?>'+dump(n));M['collections']['specialists']={'class':'zhp_specialists','page':'/o-nas/spetsialisty/','name':'Специалисты','records':specs}
# Articles are title, introduction and a coherent WYSIWYG body.
arts=[{'name':r['title'],'intro':r['intro'],'text':r['body'],'img':r['image'],'date':r['published'],'readtime':r['readtime'],'category':r['category'],'Keyword':r['Keyword']} for r in old['collections']['articles']['records']]
def artparts(node,container):
 n=prepare(node);c=n.select_one(container);a=copy.deepcopy(c.select_one('.blog__item'));replace(a,'h3',raw('<a href="<?= $fullLink ?>">')+V('name')+raw('</a>'));replace(a,'.blog__item-description',V('intro'));replace(a,'.blog__item-meta',raw("<?= date('d.m.Y',strtotime($f_date)) ?> • ")+V('readtime'));replace(a,'.blog__item-tag',V('category'));a.img['src']=V('img');a.img['alt']=V('name');return split(n,c,a)
parts=artparts(page('blog').select_one('.articles'),'.articles__list');parts['Settings']='''<?php if (!empty($_GET['category'])&&is_string($_GET['category'])&&in_array($_GET['category'],array('teens','parents','specialists'),true)) {$query_where="a.category='".$db->escape($_GET['category'])."'";} ?>''';parts['FormSuffix']+='''<?php if ($totRows>$recNum) { ?><nav class="zhp-pagination"><?= browse_messages($cc_env,5) ?></nav><?php } ?>'''
cls('zhp_articles','Статьи',[field('name','Заголовок',required=True),field('intro','Анонс','text'),field('text','Текст статьи','html'),field('img','Изображение','file'),field('date','Дата публикации','datetime'),field('readtime','Время чтения'),field('category','Категория (teens / parents / specialists)')],'name',parts)
parts=artparts(H.select_one('.blog'),'.blog__list');parts['Settings']='<?php $ignore_cc=1;$ignore_sub=1;$curPos=0;$recNum=3; ?>';cls('zhp_articles_home','Статьи — главная',[],'name',parts,parent='zhp_articles')
n=prepare(page('blog').select_one('.articles-block'));n.h2.name='h1';replace(n,'h3',V('name'));replace(n,'.blog__item-description',V('intro'))
for im in n.find_all('img'):
 if im.get('src','').endswith('.png'):im['src']=V('img')
for b in n.find_all('button'):
 if 'Читать' in plain(b):b.name='a';b['href']=raw('<?= $fullLink ?>');b.attrs.pop('data-zhp-consult',None)
cls('zhp_articles_featured','Рекомендуемая статья',[],'name',{'Settings':'<?php $ignore_cc=1;$ignore_sub=1;$curPos=0;$recNum=1; ?>','FormPrefix':'<?= $f_AdminCommon ?>','RecordTemplate':'<?= $f_AdminButtons ?>'+dump(n),'FormSuffix':''},parent='zhp_articles')
n=prepare(page('article').select_one('.article-preview'));replace(n,'h1',V('name'));replace(n,'p',V('intro'));art=prepare(page('article').select_one('.article-page'));left=art.select_one('.article-page__left');left.clear();left.append(soup('<div class="article-page__hero"><img src="'+V('img')+'" alt="'+V('name')+'"></div>'));left.append(V('text','html'));left.append(soup('<a href="/blog/">← К списку статей</a>'));right=art.select_one('.article-page__right');right.clear();right.append(soup('<h2>Нужна поддержка?</h2><a href="#consult" data-zhp-consult>Связаться с нами</a>'));(ROOT/'components/zhp_articles/RecordTemplateFull.html').write_text('<?= $f_AdminButtons ?>'+dump(n)+dump(art));M['collections']['articles']={'class':'zhp_articles','page':'/blog/','name':'Статьи','records':arts}
# Native list components and their display templates.
for k,title,node,container,item,fields,titlefield,bind,source,rows,alias in [
 ('reviews','Отзывы',H.select_one('.reviews'),'.swiper-wrapper','.reviews__item',[field('name','Имя'),field('role','Подпись'),field('text','Текст отзыва','text'),field('img','Аватар','file')],'name',[('name','.reviews__item-author-name','plain'),('role','.reviews__item-author-role','plain'),('text','.reviews__item-text','plain'),('img','.reviews__item-avatar img','file')],'/o-nas/otzyvy/',[{'name':r['name'],'role':r['detail'],'text':r['text'],'img':r['image']} for r in old['collections']['reviews']['records']],'home'),
 ('faq','Вопросы и ответы',page('contacts').select_one('.faq-block'),'.faq-block__list','.faq-block__item',[field('question','Вопрос',required=True),field('answer','Ответ','html')],'question',[('question','.faq-block__trigger','plain'),('answer','.faq-block__answer','html')],'/o-nas/voprosy-i-otvety/',old['collections']['faq']['records'],'contacts'),
 ('certificates','Дипломы и сертификаты',H.select_one('.certificates'),'.swiper-wrapper','.certificates__item',[field('name','Название документа'),field('img','Изображение документа','file'),field('file','Файл для скачивания','file')],'name',[('img','img','file')],'/o-nas/sertifikaty/',[{'name':'Диплом / сертификат','img':ASSET+'img/certificates.png'}],'home')]:
 _,settings=listing('zhp_'+k,title,fields,titlefield,node,container,item,bind)
 if k=='certificates':
  p=ROOT/'components/zhp_certificates/RecordTemplate.html';p.write_text(re.sub('href="[^"]+"', 'href="'+dump(V('img'))+'"',p.read_text()))
 parts={p.stem:p.read_text() for p in (ROOT/('components/zhp_'+k)).glob('*.html')};parts['Settings']='<?php $ignore_cc=1;$ignore_sub=1; ?>';cls('zhp_'+k+'_'+alias,title+' — другие страницы',[],titlefield,parts,parent='zhp_'+k);M['collections'][k]={'class':'zhp_'+k,'page':source,'name':title,'records':rows,'settings':settings}
n=prepare(page('gallery').select_one('.gallery'));c=n.select_one('.gallery__list--all')
for x in n.select('.gallery__list'):
 if x is not c:x.decompose()
card=soup('<a class="gallery__item" href="'+V('img')+'" data-fancybox="gallery" data-photo-category="'+V('category')+'"><img src="'+V('img')+'" alt="'+V('name')+'" loading="lazy"></a>')
cls('zhp_gallery','Фотогалерея',[field('name','Подпись фотографии'),field('img','Фотография','file'),field('category','Категория (classes / events / team / more)')],'name',split(n,c,card));M['collections']['gallery']={'class':'zhp_gallery','page':'/o-nas/foto/','name':'Фотографии','records':[{'name':r['caption'],'img':r['image'],'category':r['category']} for r in old['collections']['gallery']['records']]}
cls('zhp_text','Текстовый блок',[field('name','Заголовок'),field('text','Текст','html')],'name',{'FormPrefix':'<?= $f_AdminCommon ?>','RecordTemplate':'<?= $f_AdminButtons ?><section class="zhp-text"><div class="wrap"><h1><?= htmlspecialchars($f_name,ENT_QUOTES,"UTF-8") ?></h1><?= shortcode_component($f_text) ?></div></section>','FormSuffix':''})
# Explicit infoblock order, independent of generated record values.
add('/','zhp_banner_home','Верхний баннер',[BANNERS['home']]);add('/','zhp_founder','Основатель проекта',[FOUNDER]);add('/','zhp_video','Видео о проекте',[VIDEO]);add('/','zhp_program','Программа',[PROGS['home']]);add('/','zhp_parent_group','Группа для родителей',[SINGLES['parent_group']]);add('/','zhp_first_step','Как связаться',[SINGLES['first_step']])
for k in ['whom','steps','offer']:add('/','zhp_cards_'+k,M['classes']['zhp_cards_'+k]['name'],*CARDS[k])
for k in ['specialists','reviews','certificates','articles']:add('/','zhp_'+k+'_home',M['classes']['zhp_'+k]['name'])
add('/','zhp_socials','Социальные сети',[SINGLES['socials']])
for k in ['about','about_text']:add('/o-nas/','zhp_banner_'+k,M['classes']['zhp_banner_'+k]['name'],[BANNERS[k]])
for k in ['values','peoples','way']:add('/o-nas/','zhp_cards_'+k,M['classes']['zhp_cards_'+k]['name'],*CARDS[k])
add('/o-nas/','zhp_specialists_team','Команда');add('/o-nas/spetsialisty/','zhp_banner_specialists','Заголовок страницы',[BANNERS['specialists']]);add('/o-nas/foto/','zhp_banner_gallery','Заголовок галереи',[BANNERS['gallery']]);add('/blog/','zhp_articles_featured','Рекомендуемая статья')
service=old['service_url'];add(service,'zhp_banner_service','Баннер услуги',[BANNERS['service']]);add(service,'zhp_program_service','Содержание услуги',[PROGS['service']])
for k in ['reasons','help','way']:add(service,'zhp_cards_'+k,M['classes']['zhp_cards_'+k]['name'],*CARDS[k])
add(service,'zhp_specialists_team','Специалисты')
# Form, contacts, calculator, quiz and root template parts are explicit modules of the build only.
exec(compile((ROOT/'build/interactive.py').read_text(),'interactive.py','exec'))
for c in M['collections'].values():
 if c['class'] not in ['zhp_regions','zhp_leads']:add(c['page'],c['class'],c['name'],c['records'],c.get('settings',{}))
for p in PAGES.values():
 if not p['blocks'] and p['url'] not in ['/regions/','/zayavki/','/nastroiki-proekta/']:add(p['url'],'zhp_text','Текст страницы',[{'name':p['title'],'text':'<p>Материал готовится к публикации.</p>'}])
exec(compile((ROOT/'build/refinements.py').read_text(),'refinements.py','exec'))
exec(compile((ROOT/'build/revision.py').read_text(),'revision.py','exec'))
exec(compile((ROOT/'build/editorial.py').read_text(),'editorial.py','exec'))
M['pages']=list(PAGES.values())
def clean(v):
 if isinstance(v,str):return v.replace('/netcat_template/template/lifepodrostka/',ASSET).replace('{{region:city}}','[city]').replace('{{region:address}}','[address]').replace('src="img/','src="'+ASSET+'img/').replace('/politika-konfidencialnosti/','/politika-konfidentsialnosti/')
 if isinstance(v,list):return [clean(x) for x in v]
 if isinstance(v,dict):return {k:clean(x) for k,x in v.items()}
 return v
M=clean(M);(ROOT/'installer/manifest.json').write_text(json.dumps(M,ensure_ascii=False,indent=2))
for p in list((ROOT/'components').rglob('*.html'))+list(OUT.glob('*.html')):p.write_text(clean(p.read_text()))
I=OUT/'.install';I.mkdir(exist_ok=True);shutil.copytree(ROOT/'components',I/'components',dirs_exist_ok=True);shutil.copy2(ROOT/'installer/manifest.json',I/'manifest.json');(I/'.htaccess').write_text('Require all denied\nDeny from all\n')
print('Native classes and templates:',len(M['classes']),'Pages:',len(M['pages']))
