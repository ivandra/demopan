from bs4 import Comment

M['version'] = 4

def shield(value):
    result=[];position=0
    while True:
        start=value.find('<?',position)
        if start<0:
            result.append(value[position:]);break
        result.append(value[position:start]);index=start+2;quote=None
        while index<len(value):
            char=value[index]
            if quote:
                if char=='\\':index+=2;continue
                if char==quote:quote=None
            elif char in ["'",'"','`']:quote=char
            elif value[index:index+2]=='?>':index+=2;break
            index+=1
        result.append(raw(value[start:index]));position=index
    return ''.join(result)

def part_dir(key):
    return ROOT / 'components' / key

def write_parts(key, parts):
    folder = part_dir(key)
    folder.mkdir(exist_ok=True)
    for name, value in parts.items():
        (folder / (name + '.html')).write_text(dump(value))

def original_page(name):
    return soup((SRC / (name + '.html')).read_text())

def original_node(page_name, selector):
    return prepare(original_page(page_name).select_one(selector))

def static_block(key, node):
    M['classes'][key]['fields'] = []
    M['classes'][key]['settings'] = {}
    M['classes'][key]['static'] = True
    write_parts(key, {
        'FormPrefix': dump(node),
        'RecordTemplate': '',
        'FormSuffix': '',
    })
    for definition in PAGES.values():
        for block in definition['blocks']:
            if block['class'] == key:
                block['records'] = []
                block['settings'] = {}

def field_node(key, selector, field_name, kind='html'):
    path = part_dir(key) / 'RecordTemplate.html'
    markup = soup(shield(path.read_text()))
    target = markup.select_one(selector)
    if target:
        target.clear()
        target.append(V(field_name, kind))
    path.write_text(dump(markup))

def retained(key, names):
    M['classes'][key]['fields'] = [f for f in M['classes'][key]['fields'] if f['name'] in names]

M['classes']['zhp_video']['name'] = 'ПОЗНАКОМЬТЕСЬ С НАМИ БЛИЖЕ'
retained('zhp_video', ['name', 'text', 'link', 'img'])
video = original_node('index', '.video-card')
video['id']='video'
replace(video, 'h2', V('name', 'html'))
video.h2.append(prepare(original_page('index').select_one('.video-card h2 img')))
replace(video, '.hero__description', V('text', 'html'))
video.select_one('.video-card__video a')['href'] = V('link')
video.select_one('.video-card__video img')['src'] = V('img')
write_parts('zhp_video', {'FormPrefix':'<?= $f_AdminCommon ?>',
                        'RecordTemplate':'<?= $f_AdminButtons ?>\n'+dump(video), 'FormSuffix':''})

static_block('zhp_parent_group', original_node('index', '.parents-group'))
first = original_node('index', '.first-step')
group_link = first.select('.first-step__card')[1].find('a')
group_link['href'] = R('max')
group_link.attrs.pop('data-zhp-consult', None)
static_block('zhp_first_step', first)
social = original_node('index', '.socials')
for item, key in zip(social.select('.socials__item'), ['instagram','vk','telegram','youtube']):
    item.name = 'a'
    item['href'] = R(key)
static_block('zhp_socials', social)

retained('zhp_banner', ['name','text','img'])
for variant in ['home','about','about_text','specialists','gallery','service']:
    key = 'zhp_banner_' + variant
    source_page, selector, position = {
        'home':('index','.hero',0), 'about':('about','.about',0),
        'about_text':('about','.about',1), 'specialists':('specialists','.specialists-hero',0),
        'gallery':('gallery','.gallery-hero',0), 'service':('service','.contacts-page',0),
    }[variant]
    source = original_page(source_page).select(selector)[position]
    node = prepare(source)
    heading = node.find('h1')
    if heading:
        heading.clear(); heading.append(V('name','html'))
        for decoration in source.find('h1').find_all('img',recursive=False):
            heading.append(prepare(decoration))
    replace(node, '.hero__description', V('text','html'))
    image_selector = {
        'home':'.hero__image img','about':'.about__image img','about_text':'.about__image img',
        'specialists':'.specialists-hero__image img','gallery':'.gallery-hero__image img',
        'service':'.contacts-page__image img',
    }[variant]
    image = node.select_one(image_selector)
    if image: image['src'] = V('img')
    write_parts(key, {'FormPrefix':'<?= $f_AdminCommon ?>',
                     'RecordTemplate':'<?= $f_AdminButtons ?>\n'+dump(node), 'FormSuffix':''})

retained('zhp_founder', ['fio','img','bio','achievements'])
path=part_dir('zhp_founder')/'RecordTemplate.html'
node=soup(shield(path.read_text()))
replace(node,'.hero__label',plain(original_page('index').select_one('.founder .hero__label')))
path.write_text(dump(node))
for key, source in [('zhp_program','index'),('zhp_program_service','service')]:
    retained(key,['name','text','directions','support'])
    node=original_node(source,'.program')
    replace(node,'h2',V('name','html'))
    replace(node,'.hero__description',V('text','html'))
    for target,field_name in zip(node.select('.program__list'),['directions','support']):
        target.clear();target.append(V(field_name,'html'))
    write_parts(key, {'FormPrefix':'<?= $f_AdminCommon ?>',
                     'RecordTemplate':'<?= $f_AdminButtons ?>\n'+dump(node),'FormSuffix':''})

def form_data(kind):
    return hidden(kind).replace('<label class="zhp-hp" aria-hidden="true">Website<input name="zhp_website" tabindex="-1" autocomplete="off"></label>',
                                '<input type="hidden" name="zhp_website" value="">')

def source_form(node, kind):
    node['method']='post';node['action']='/netcat/add.php';node['data-zhp-form']=kind
    fields=['email'] if kind=='subscribe' else ['name','contact']
    for input_node,name in zip(node.select('input:not([type=checkbox]):not([type=hidden])'),fields):
        input_node['name']='f_'+name
        if name!='name':input_node['required']=''
        if name=='email':input_node['type']='email'
    for textarea in node.select('textarea'):textarea['name']='f_message'
    for checkbox in node.select('input[type=checkbox]'):
        checkbox['name']='f_consent';checkbox['value']='1';checkbox['required']=''
    node.append(raw(form_data(kind)))
    node.append(soup('<output role="status" data-form-status></output>'))
    return node

subscription = original_node('index','.subscription')
source_form(subscription.find('form'),'subscribe')
static_block('zhp_subscription', subscription)
static_block('zhp_subscription_shared', subscription)
M['classes']['zhp_subscription_shared']['parent']='zhp_subscription'
M['classes']['zhp_subscription_shared']['static']=True

contacts = original_node('contacts','.contacts-page')
replace(contacts,'h1',V('name','html'))
replace(contacts,'.hero__description',V('text','html'))
source_form(contacts.find('form'),'contact')
for card in contacts.select('.contacts-page__socials-card'):
    network={'YouTube':'youtube','VK':'vk','Instagram':'instagram','Telegram':'telegram'}.get(plain(card.find('h4')))
    if network:
        card.find('a')['href']=R(network)
        card.find('a').attrs.pop('data-zhp-consult',None)
write_parts('zhp_contacts',{'FormPrefix':'<?= $f_AdminCommon ?>',
                          'RecordTemplate':'<?= $f_AdminButtons ?>\n'+dump(contacts),'FormSuffix':''})

calculator = original_node('index','.calc')
calculator['id']='calc'
static_block('zhp_calculator',calculator)
M['classes']['zhp_calculator']['settings']={
    name:{'caption':caption,'type':'string','default_value':value}
    for name,caption,value in [('days_min','Минимум суток',1),('days_max','Максимум суток',90),
                                ('days','Суток по умолчанию',30),('rate_min','Минимальная ставка',3000),
                                ('rate_max','Максимальная ставка',6000),('rate','Ставка по умолчанию',4500)]
}
write_parts('zhp_calculator',{'FormPrefix':dump(calculator)+'''
<script>
window.projectCalculator = <?= json_encode(array_map('intval', $cc_settings)) ?>;
</script>'''})
for definition in PAGES.values():
    for block in definition['blocks']:
        if block['class']=='zhp_calculator':block['settings']={}

quiz=original_node('index','.quiz')
quiz['id']='quiz'
form=quiz.select_one('.quiz__form');form.name='form'
form['method']='post';form['action']='/netcat/add.php';form['data-zhp-form']='quiz'
steps=form.select_one('.quiz__steps');last=copy.deepcopy(steps.select('.quiz__step')[-1])
last['data-step']=raw('<?= $totRows + 1 ?>');last.find('form').name='div'
for input_node,name in zip(last.select('input'),['name','phone','email']):
    input_node['name']='f_'+name
    if name=='phone':input_node['required']=''
steps.clear();steps.append(raw('V4_QUESTION_RECORD'));steps.append(last)
form.append(raw(form_data('quiz')))
form.append(soup('<output role="status" data-form-status></output>'))
quiz.select_one('.quiz__btn--submit')['type']='submit'
prefix,suffix=dump(quiz).split('V4_QUESTION_RECORD')
record=(part_dir('zhp_quiz')/'RecordTemplate.html').read_text()
record=record.replace('class="quiz__step"','class="quiz__step" data-step="<?= $f_RowNum + 1 ?>"')
write_parts('zhp_quiz',{'FormPrefix':'<?= $f_AdminCommon ?>\n'+prefix,'RecordTemplate':record,'FormSuffix':suffix})
M['classes']['zhp_quiz']['settings']={}
for definition in PAGES.values():
    for block in definition['blocks']:
        if block['class']=='zhp_quiz':block['settings']={}

retained('zhp_articles',['name','intro','text','img','date','readtime','category'])
article=part_dir('zhp_articles')/'RecordTemplateFull.html'
text=article.read_text().replace(dump(V('support_img')),ASSET+'img/banner.png')
article.write_text(text)
path=part_dir('zhp_articles')/'FormSuffix.html'
path.write_text(path.read_text().replace('class="zhp-pagination"',''))
path=part_dir('zhp_specialists')/'RecordTemplateFull.html'
text=path.read_text()
text=re.sub(r'<\?php if\(isset\(\$f_diploms\).*$', '', text, flags=re.S)
retained('zhp_specialists', ['fio','position','img','intro','bio','personalquote','stats','details','methods','education','category'])
other = original_node('specialist', '.specialist-page__other')
container = other.select_one('.specialist-page__other-list')
card = copy.deepcopy(container.select_one('.specialist-page__other-card'))
replace(card, 'h3', V('fio'))
replace(card, '.specialist-page__other-role', V('position'))
card.img['src'] = V('img')
parts = split(other, container, card)
parts['Settings'] = '''<?php
$ignore_cc = 1;
$ignore_sub = 1;
$curPos = 0;
$recNum = 4;
$query_where = 'a.Message_ID <> ' . (int) ($GLOBALS['project_current_specialist'] ?? 0);
?>'''
parts['RecordTemplateFull'] = '<?php include $nc_parent_field_path; ?>'
cls('zhp_specialists_other', 'Другие специалисты', [], 'fio', parts, parent='zhp_specialists')
related_call = '''<?php
$project_related = $db->get_row(
    "SELECT s.Sub_Class_ID, s.Subdivision_ID, c.Class_ID AS View_ID FROM Sub_Class s " .
    "JOIN Class c ON c.ClassTemplate=s.Class_ID AND c.Keyword='zhp_specialists_other' " .
    "WHERE s.Catalogue_ID=" . (int)$catalogue . " AND s.Class_ID=" . (int)$classID .
    " AND s.Class_Template_ID=0 AND s.Checked=1 ORDER BY s.Priority LIMIT 1", ARRAY_A
);
if ($project_related) {
    $GLOBALS['project_current_specialist'] = (int)$f_RowID;
    echo nc_objects_list((int)$project_related['Subdivision_ID'],
        (int)$project_related['Sub_Class_ID'], 'nc_ctpl=' . (int)$project_related['View_ID']);
    unset($GLOBALS['project_current_specialist']);
}
?>'''
text = text.rsplit('</section>',1)[0] + related_call + '\n</section>'
path.write_text(text)
path=part_dir('zhp_articles')/'RecordTemplateFull.html'
path.write_text(path.read_text().replace('<a href="/blog/">← К списку статей</a>', ''))
for key in ['zhp_articles','zhp_specialists']:
    path=part_dir(key)/'RecordTemplateFull.html'
    path.write_text(path.read_text()+"\n<?= project_shared_block('zhp_subscription') ?>\n")
path=part_dir('zhp_location')/'RecordTemplate.html'
text=path.read_text()
text=re.sub(r'<\?php \$zhp_map=.*?\?>',"<?= shortcode('[mapcode]') ?>",text,flags=re.S)
path.write_text(text)
path=part_dir('zhp_location')/'RecordTemplate.html'
text=path.read_text().replace('Уточнить адрес →', 'Построить маршрут →')
text=text.replace('href="#consult"', 'href="https://yandex.ru/maps/?text=<?= rawurlencode(shortcode(\'[city], [address]\')) ?>"')
path.write_text(text)
for definition in PAGES.values():
    for block in definition['blocks']:
        if block['class']=='zhp_location':
            for row in block['records']:
                row['text']=plain(original_page('contacts').select_one('.location-block .hero__description')).replace('в центре Москвы','в городе [city]')
path=part_dir('zhp_leads')/'AddTemplate.html'
path.write_text(path.read_text().replace('class="zhp-form-status"','data-form-status'))

# Put the typographic text block from index.html into its own editable component.
typography=original_node('index','.text-block')
M['classes']['zhp_text']['fields']=[field('text','Текст','html')]
typography.select_one('.wrap').clear()
typography.select_one('.wrap').append(raw('''<?php if ((int)$sub !== (int)$current_catalogue['Title_Sub_ID']) { ?>
<h1><?= htmlspecialchars((string)$current_sub['Subdivision_Name'], ENT_QUOTES, 'UTF-8') ?></h1>
<?php } ?>
<?= project_text($f_text) ?>'''))
write_parts('zhp_text',{'FormPrefix':'<?= $f_AdminCommon ?>','RecordTemplate':'<?= $f_AdminButtons ?>\n'+dump(typography),'FormSuffix':''})
PAGES['/']['blocks'].insert(-2,{
    'class':'zhp_text','name':'Текстовый блок','records':[{'text':inner(original_page('index').select_one('.text-block .wrap'))}],
    'settings':{},
})
for definition in PAGES.values():
    if definition['url']=='/':continue
    for block in definition['blocks']:
        if block['class']=='zhp_text':
            for record in block['records']:
                value=record.get('text','')
                if 'Материал готовится' in value:value=''
                record['text']=value

for key,definition in M['classes'].items():
    if definition.get('existing'):continue
    path=part_dir(key)/'RecordTemplate.html'
    if not path.exists():continue
    record=path.read_text()
    prefix_path=part_dir(key)/'FormPrefix.html';suffix_path=part_dir(key)/'FormSuffix.html'
    prefix=prefix_path.read_text() if prefix_path.exists() else ''
    if '<section' not in record or '<section' in prefix:continue
    shielded=soup(shield(record))
    section=shielded.find(['section','article'])
    if section is None:continue
    wrap=section.find('div',class_='wrap',recursive=False)
    if wrap is None:continue
    marker='V4_OBJECT_CONTENT'
    contents=inner(wrap);wrap.clear();wrap.append(raw(marker))
    opening,closing=dump(section).split(marker)
    write_parts(key,{'FormPrefix':'<?= $f_AdminCommon ?>\n'+opening,
                     'RecordTemplate':'<?= $f_AdminButtons ?>\n'+dump(contents),
                     'FormSuffix':closing})

for base,view in [('zhp_cards','zhp_cards_whom'),('zhp_banner','zhp_banner_home')]:
    for name in ['FormPrefix','RecordTemplate','FormSuffix']:
        (part_dir(base)/(name+'.html')).write_text((part_dir(view)/(name+'.html')).read_text())
    M['classes'][base]['settings']=copy.deepcopy(M['classes'][view]['settings'])

path=part_dir('zhp_specialists_team')/'FormPrefix.html'
path.write_text(path.read_text().replace('<h2','<h1').replace('</h2>','</h1>'))
path=part_dir('zhp_articles_featured')/'FormPrefix.html'
path.write_text(path.read_text().replace('<h1','<h2').replace('</h1>','</h2>'))
path=part_dir('zhp_articles_featured')/'RecordTemplate.html'
path.write_text(path.read_text().replace('<h1','<h2').replace('</h1>','</h2>'))

for definition in PAGES.values():
    for block in definition['blocks']:
        key=block['class'];base=M['classes'][key].get('parent') or key
        fields={field['name'] for field in M['classes'][base]['fields']}
        if M['classes'][key].get('static'):
            block['records']=[]
        else:
            block['records']=[{name:value for name,value in row.items() if name in fields or name=='Keyword'} for row in block['records']]
for collection in M['collections'].values():
    fields={field['name'] for field in M['classes'][collection['class']]['fields']}
    collection['records']=[{name:value for name,value in row.items() if name in fields or name=='Keyword'} for row in collection['records']]

for definition in PAGES.values():
    for block in definition['blocks']:
        for record in block['records']:
            if 'name' in record:record['name']=re.sub(r'<img\b[^>]*>','',record['name'])
for path in (ROOT/'components').rglob('*.html'):
    path.write_text(path.read_text().replace('shortcode_component((string)$f_name)','project_title($f_name)').replace("htmlspecialchars(shortcode((string)$f_position),ENT_QUOTES,'UTF-8')",'project_label($f_position)'))
helper=OUT/'function.inc.php.txt'
helper.write_text(helper.read_text()+'''
function project_title($value) {
    $value = preg_replace('~<img\\b[^>]*hero__title-line[^>]*>~i', '', (string)$value);
    return shortcode_component($value);
}
function project_text($value) {
    return trim((string)$value) === '<p>Материал готовится к публикации.</p>' ? '' : shortcode_component($value);
}
function project_label($value) {
    return str_replace('‑', '‑<wbr>', htmlspecialchars(shortcode($value), ENT_QUOTES, 'UTF-8'));
}
function project_shared_block($keyword) {
    global $db, $catalogue;
    $block = $db->get_row(
        'SELECT s.Subdivision_ID,s.Sub_Class_ID FROM Sub_Class s JOIN Class c USING(Class_ID) ' .
        "WHERE s.Catalogue_ID=" . (int)$catalogue . " AND s.Checked=1 AND c.Keyword='" .
        $db->escape($keyword) . "' ORDER BY s.Class_Template_ID,s.Priority,s.Sub_Class_ID LIMIT 1", ARRAY_A
    );
    return $block ? nc_objects_list((int)$block['Subdivision_ID'], (int)$block['Sub_Class_ID']) : '';
}
''')

head=original_page('index').head
for link in head.select('link[rel=stylesheet]'):
    href=link['href']
    if 'jquery-ui' in href:link['href']=ASSET+'css/jquery-ui.min.css'
    elif 'swiper' in href:link['href']=ASSET+'css/swiper-bundle.min.css'
    elif 'fancybox' in href:link['href']=ASSET+'css/jquery.fancybox.min.css'
    else:link['href']=ASSET+href
head.append(soup('<title>'+raw("<?= htmlspecialchars((string)$nc_core->page->get_title(), ENT_QUOTES, 'UTF-8') ?>")+'</title>'))
head.append(raw("<?php if (!empty($template_settings['noindex'])) { ?><meta name=\"robots\" content=\"noindex,nofollow\"><?php } ?>"))
header=prepare(original_page('index').find('header'))
mobile=prepare(original_page('index').select_one('.mobile-menu'))
for link in mobile.select('.mobile-menu__phone a'):
    link['href']=raw("tel:<?= preg_replace('/[^0-9+]/', '', shortcode('[phone]')) ?>")
replace(mobile,'.mobile-menu__hours',R('regim'))
for link,key in zip(mobile.select('.mobile-menu__social a'),['telegram','instagram','vk','youtube']):
    link['href']=R(key)
for node,selector,variable in [(header,'.header__list','zhp_menu'),(mobile,'.mobile-menu__list','zhp_mobile')]:
    node.select_one(selector).replace_with(raw('<?= nc_browse_level(0, $'+variable+') ?>'))
footer=prepare(original_page('index').find('footer'))
footer.select_one('.footer__copy').clear()
footer.select_one('.footer__copy').append(raw("© <?= date('Y') ?> Жизнь подростка. Все права защищены."))
for node in [header,mobile,footer]:
    for a in node.select('a'):
        if 'Нужна помощь' in plain(a):a['href']='/kontakty/'
for a,key in zip(footer.select('.footer__social-link'),['instagram','vk','telegram','youtube']):a['href']=R(key)
(OUT/'Header.html').write_text('<!DOCTYPE html>\n<html lang="ru">\n'+dump(head)+'\n<body>\n'+dump(mobile)+'\n'+dump(header))
(OUT/'Footer.html').write_text(dump(footer)+'\n'+''.join('<script src="'+ASSET+'js/'+name+'"></script>\n' for name in ['jquery.min.js','swiper-bundle.min.js','jquery.fancybox.min.js','jquery-ui.min.js','jquery.ui.touch-punch.min.js','common.js','forms.js'])+'</body>\n</html>\n')
M['layouts']={'home':'Главная','inner':'Внутренняя','error':'404','success':'Заявка отправлена'}
M['template_settings']={key:value for key,value in M['template_settings'].items() if key in ['noindex','support_title','support_button','support_text']}
for key in M['layouts']:
    directory=OUT/key;directory.mkdir(exist_ok=True)
    (directory/'Header.html').write_text('%Header\n')
    (directory/'Footer.html').write_text('%Footer\n')
    (directory/'Settings.html').write_text('')
for definition in PAGES.values():
    definition['layout']='home' if definition['url']=='/' else 'error' if definition['url']=='/404/' else 'success' if definition['url']=='/zayavki/' else 'inner'
for key in ['about','service','blog','specialists','gallery','contacts']:
    directory=OUT/key
    if directory.exists():shutil.rmtree(directory)

for path in [*(ROOT/'components').rglob('*.html'),*OUT.glob('*.html')]:
    text=path.read_text()
    text=text.replace('href="#consult"','href="/kontakty/"')
    text=re.sub(r'\sdata-zhp-consult="[^"]*"','',text)
    path.write_text(text)

add_condition=part_dir('zhp_leads')/'AddCond.html'
text=add_condition.read_text().replace("if(empty($f_consent))", "if(in_array($zhp_type,array('contact','subscribe'),true)&&empty($f_consent))")
text=text.replace('$f_consent=1;', '$f_consent=empty($f_consent)?0:1;')
add_condition.write_text(text)
(part_dir('zhp_leads')/'AddTemplate.html').write_text('''<?php
if (empty($_SESSION['zhp_form_nonce'])) {
    $_SESSION['zhp_form_nonce'] = bin2hex(random_bytes(24));
}
?>
<input type="hidden" name="zhp_nonce" value="<?= htmlspecialchars($_SESSION['zhp_form_nonce'], ENT_QUOTES, 'UTF-8') ?>">
<?= $nc_core->token->get_input() ?>
<?php if (!empty($zhp_already_saved)) { ?>
<div data-zhp-success>Заявка уже принята.</div>
<?php } elseif (!empty($warnText)) { ?>
<div data-zhp-error><?= htmlspecialchars(strip_tags($warnText), ENT_QUOTES, 'UTF-8') ?></div>
<?php } ?>
''')

shutil.copy2(SRC/'js/common.js',OUT/'js/common.js')
shutil.copy2(ROOT.parent/'zhizn-podrostka/zhizn-podrostka/js/jquery.ui.touch-punch.min.js',OUT/'js/jquery.ui.touch-punch.min.js')
ui=Path('/workspace/netcat-study/formatting/node_modules/jquery-ui-dist')
shutil.copy2(ui/'jquery-ui.min.js',OUT/'js/jquery-ui.min.js')
shutil.copy2(ui/'jquery-ui.min.css',OUT/'css/jquery-ui.min.css')
shutil.copytree(ui/'images',OUT/'css/images',dirs_exist_ok=True)
for path in [OUT/'css/native.css',OUT/'js/native.js']:
    path.unlink(missing_ok=True)
(OUT/'favicon.svg').unlink(missing_ok=True)
(OUT/'js/forms.js').write_text((ROOT/'build/forms.js').read_text())
