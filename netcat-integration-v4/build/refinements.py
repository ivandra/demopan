def shield(s):return re.sub(r'<\?.*?\?>',lambda m:raw(m[0]),s,flags=re.S)
# Applied at build time after the semantic models and before copying native files.
# Preserve source image variants without repeating the first card's illustration.
M['classes']['zhp_cards']['fields'] += [field('img_mobile','Мобильное изображение','file'),field('link','Ссылка')]
rows,_=CARDS['whom']
for i,r in enumerate(rows):r['img']=ASSET+'img/block-for-whom'+str(i+1)+'.png';r['link']=['/podrostkam/','/roditelyam/','/spetsialistam/'][i]
p=ROOT/'components/zhp_cards_whom/RecordTemplate.html';n=soup(shield(p.read_text()));n.select_one('.block-for-whom__item-image img')['src']=V('img');decor=n.select_one('.block-for-whom__item-img');decor.replace_with(raw('<?php if($f_RowNum%3<2){ ?><img class="block-for-whom__item-img" src="'+ASSET+'img/before<?= $f_RowNum%3+1 ?>.svg" alt=""><?php } ?>'));n.a['href']=V('link');n.a.attrs.pop('data-zhp-consult',None);p.write_text(dump(n))
rows,_=CARDS['peoples']
for i,r in enumerate(rows):r['img']=ASSET+'img/peoples.png' if i==0 else '';r['img_mobile']=ASSET+'img/peoples'+str(i+3)+'.png'
p=ROOT/'components/zhp_cards_peoples/RecordTemplate.html';s=p.read_text().replace('class="peoples__item"','class="peoples__item<?php if($f_RowNum%2){echo \' peoples__item--blue\';} ?>"');n=soup(shield(s));im=n.select_one('.peoples__item-image');im.replace_with(raw('<?php if((string)$f_img!==\'\'){ ?>'+str(im)+'<?php } ?>'));n.select_one('.peoples__item-mob-image')['src']=V('img_mobile');p.write_text(dump(n))
for k,prefix in [('steps','steps'),('way','way')]:
 p=ROOT/('components/zhp_cards_'+k+'/RecordTemplate.html');s=p.read_text();s=re.sub('src="[^"]+"(?=/>)',lambda m:m[0],s)
 s=s.replace(ASSET+'img/'+prefix+'-number1.svg',ASSET+'img/'+prefix+'-number<?= $f_RowNum%4+1 ?>.svg');p.write_text(s)
p=ROOT/'components/zhp_reviews/RecordTemplate.html';s=p.read_text().replace('reviews__item"','reviews__item<?php if($f_RowNum%3===1){echo \' reviews__item--green\';} ?>"').replace(ASSET+'img/review1.png',ASSET+'img/review<?= $f_RowNum%3+1 ?>.png');p.write_text(s);(ROOT/'components/zhp_reviews_home/RecordTemplate.html').write_text(s)
# Categories and publication dates are taken from the same article object in every output.
category="<?= htmlspecialchars(array('teens'=>'Подросткам','parents'=>'Родителям','specialists'=>'Специалистам')[$f_category]??'',ENT_QUOTES,'UTF-8') ?>"
for k in ['zhp_articles','zhp_articles_home']:
 p=ROOT/('components/'+k+'/RecordTemplate.html');s=p.read_text().replace(dump(V('category')),category);s=s.replace('class="blog__item-tag"','class="blog__item-tag<?php if($f_category===\'parents\'){echo \' blog__item-tag--blue\';} ?>"');p.write_text(s)
p=ROOT/'components/zhp_articles/RecordTemplateFull.html';s=p.read_text();s=re.sub(r'(<div class="blog__item-tag">).*?(</div>)',lambda m:m[1]+category+m[2],s,flags=re.S);s=re.sub(r'(<div class="blog__item-meta">).*?(</div>)',lambda m:m[1]+"<?= date('d.m.Y',strtotime($f_date)) ?> • <?= htmlspecialchars($f_readtime,ENT_QUOTES,'UTF-8') ?>"+m[2],s,flags=re.S);p.write_text(s)
# Region-aware location panel replaces the fixed Yandex constructor from the demo.
n=prepare(page('contacts').select_one('.location-block'));r={'name':inner(n.h2),'text':'<p>Очные встречи — по предварительной записи.</p>'};replace(n,'h2',V('name','html'));replace(n,'.hero__description',V('text','html'));t=n.select_one('.location-block__map');t.clear();t.append(raw("<?php $zhp_map=shortcode('[mapcode]');echo $zhp_map?:'<div class=\"zhp-map-note\">Уточните адрес при записи на встречу.</div>'; ?>"));details=n.select('.location-block__detail-text');details[0].clear();details[0].append(R('city')+', '+R('address'));details[1].clear();details[1].append(R('regim'));button=n.find('button');button.name='a';button['href']='#consult';button['data-zhp-consult']='';button.string='Уточнить адрес →';single('zhp_location','Как нас найти',[field('name','Заголовок','html'),field('text','Описание','html')],'name',n);add('/kontakty/','zhp_location','Как нас найти',[r]);(OUT/'css/native.css').write_text((OUT/'css/native.css').read_text()+'.zhp-map-note{height:100%;min-height:280px;display:grid;place-items:center;padding:24px;text-align:center;background:#eee9fc;color:#4b4165}.location-block__map{overflow:hidden}')
# Empty service pages use an explicit H1, collection pages use their collection heading.
for key in ['faq','reviews','certificates']:
 p=ROOT/('components/zhp_'+key+'/FormPrefix.html');s=p.read_text().replace('<h2','<h1').replace('</h2>','</h1>');p.write_text(s)
# Questionnaire answers are validated against published native question objects.
p=ROOT/'components/zhp_leads/AddCond.html';s=p.read_text();marker=" if($f_email!==''"
extra=''' if($zhp_type==='quiz'){
 $zhp_qclass=(int)$db->get_var("SELECT Class_ID FROM Class WHERE Keyword='zhp_quiz' AND ClassTemplate=0");$zhp_answers=array();
 $zhp_questions=$zhp_qclass?(array)$db->get_results("SELECT a.Message_ID,a.question,a.answers FROM Message".$zhp_qclass." a JOIN Sub_Class c ON c.Sub_Class_ID=a.Sub_Class_ID WHERE c.Catalogue_ID=".(int)$catalogue." AND c.Checked=1 AND a.Checked=1 ORDER BY a.Priority,a.Message_ID",ARRAY_A):array();
 if(!$zhp_questions){$zhp_fail='Квиз временно недоступен.';}
 foreach($zhp_questions as $zhp_q){$zhp_answer=$_POST['quiz_'.$zhp_q['Message_ID']]??null;$zhp_options=array_map('trim',preg_split('/\\\\R/u',trim($zhp_q['answers'])));if(!is_string($zhp_answer)||!in_array($zhp_answer,$zhp_options,true)){$zhp_fail='Ответьте на все вопросы квиза.';}else{$zhp_answers[]=$zhp_q['question'].': '.$zhp_answer;}}
 $f_answers=implode("\\n",$zhp_answers);
 }
'''
s=s.replace(marker,extra+marker);p.write_text(s)
p=ROOT/'components/zhp_leads/AddCond.html';s=p.read_text();s=s.replace('/'+chr(92)*4+'R/u','/'+chr(92)+'R/u');p.write_text(s)
# No clinic favicon or fixed external constructor remains in the active layout.
(OUT/'favicon.svg').write_text('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32"><rect width="32" height="32" rx="9" fill="#8b6ad9"/><path d="M16 7v18M7 7l9 9-9 9M25 7l-9 9 9 9" stroke="#fff" stroke-width="2.6" stroke-linejoin="round" fill="none"/></svg>')
p=OUT/'Header.html';p.write_text(p.read_text().replace('</head>','<link rel="icon" href="'+ASSET+'favicon.svg" type="image/svg+xml"></head>'))
for b in PAGES['/404/']['blocks']:
 if b['class']=='zhp_text':b['records'][0]['text']='<p>Такой страницы нет. <a href="/">Вернуться на главную</a>.</p>'
p=ROOT/'components/zhp_specialists/RecordTemplateFull.html';s=p.read_text();s+='''<?php if(isset($f_diploms)&&$f_diploms instanceof nc_multifield&&$f_diploms->count()){ ?><section class="zhp-documents"><div class="wrap"><h2>Документы и сертификаты</h2><div class="zhp-document-list"><?php foreach($f_diploms->to_array() as $zhp_document){ ?><a href="<?= htmlspecialchars((string)$zhp_document->Path,ENT_QUOTES,'UTF-8') ?>" target="_blank" rel="noopener"><?= htmlspecialchars($zhp_document->Name?:'Документ',ENT_QUOTES,'UTF-8') ?></a><?php } ?></div></div></section><?php } ?>''';p.write_text(s)
for key in ['zhp_certificates','zhp_certificates_home']:
 p=ROOT/('components/'+key+'/RecordTemplate.html');s=p.read_text();href="<?= htmlspecialchars((string)$f_file!==''?(string)$f_file:(string)$f_img,ENT_QUOTES,'UTF-8') ?>";s=re.sub(r'href=".*?"',lambda m:'href="'+href+'"',s,count=1);s=s.replace('alt=""','alt="<?= htmlspecialchars($f_name,ENT_QUOTES,\'UTF-8\') ?>"');p.write_text(s)
p=OUT/'css/native.css';p.write_text(p.read_text()+'.zhp-documents{padding:40px 0}.zhp-documents h2{font-size:28px;margin-bottom:22px}.zhp-document-list{display:flex;gap:16px;flex-wrap:wrap}.zhp-document-list a{padding:12px 18px;border-radius:12px;background:#eee9fc;color:#4b4165}')
p=OUT/'Header.html';p.write_text(p.read_text().replace('Демонстрационные контакты. Укажите рабочие реквизиты в разделе «Регионы».','Тестовая версия сайта. Контакты приведены для примера.'))
p=ROOT/'components/zhp_socials/RecordTemplate.html';n=soup(shield(p.read_text()))
for item,k in zip(n.select('.socials__item'),['instagram','vk','telegram','youtube']):item.name='a';item['href']=R(k);item['data-zhp-social']='';item['aria-label']={'instagram':'Instagram','vk':'ВКонтакте','telegram':'Telegram','youtube':'YouTube'}[k]
p.write_text(dump(n))
# A small region chooser appears once two or more regions are published.
p=OUT/'Header.html';s=p.read_text();regionbar='''<?php $zhp_region_class=(int)$db->get_var("SELECT Class_ID FROM Class WHERE Keyword='zhp_regions' AND ClassTemplate=0");$zhp_region_list=$zhp_region_class?(array)$db->get_results("SELECT main_domain,city FROM Message".$zhp_region_class." WHERE Checked=1 ORDER BY Priority,Message_ID",ARRAY_A):array();if(count($zhp_region_list)>1){ ?><div class="zhp-regionbar"><div class="wrap"><details><summary><?= htmlspecialchars(shortcode('[city]'),ENT_QUOTES,'UTF-8') ?> ▾</summary><ul><?php foreach($zhp_region_list as $zhp_region){if(!preg_match('/^[a-z0-9.-]+$/i',$zhp_region['main_domain'])){continue;} ?><li><a href="<?= htmlspecialchars('https://'.$zhp_region['main_domain'].$_SERVER['REQUEST_URI'],ENT_QUOTES,'UTF-8') ?>"><?= htmlspecialchars($zhp_region['city'],ENT_QUOTES,'UTF-8') ?></a></li><?php } ?></ul></details></div></div><?php } ?>''';s=s.replace('<header class="header">',regionbar+'<header class="header">');p.write_text(s)
p=OUT/'css/native.css';p.write_text(p.read_text()+'.zhp-regionbar{position:relative;z-index:45;background:#f5f1fb;font:14px/1.4 sans-serif}.zhp-regionbar .wrap{display:flex;justify-content:flex-end;padding-top:6px;padding-bottom:6px}.zhp-regionbar details{position:relative}.zhp-regionbar summary{cursor:pointer}.zhp-regionbar ul{position:absolute;right:0;top:100%;min-width:180px;background:white;padding:12px;border-radius:12px;box-shadow:0 5px 25px #0002;list-style:none}.zhp-regionbar a{display:block;padding:8px;color:#3d285d}')
