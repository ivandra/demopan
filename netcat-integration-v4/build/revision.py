"""Corrections based on the original Medelta files and Netcat 7.2 API.

Executed by native.py after the original semantic-component build. This file is
build-time code; no Python or generic HTML dispatcher runs on the website.
"""

M['version'] = 3
# This key is an installer alias only: bind it to the existing component 223.
# Never create another region component, table, infoblock or initial region.
M['classes']['zhp_regions']['existing'] = True
M['classes']['zhp_regions']['fields'] = [
    f for f in M['classes']['zhp_regions']['fields']
    if f['name'] not in ['demo']
]
M['collections']['regions']['records'] = []
for directory in [ROOT / 'components/zhp_regions', OUT / '.install/components/zhp_regions']:
    if directory.exists():
        shutil.rmtree(directory)

def edit_part(key, part, change):
    path = ROOT / 'components' / key / (part + '.html')
    path.write_text(change(path.read_text()))

def add_fields(key, fields, values):
    M['classes'][key]['fields'].extend(fields)
    for page_def in PAGES.values():
        for block in page_def['blocks']:
            if block['class'] == key or M['classes'][block['class']].get('parent') == key:
                for record in block['records']:
                    for name, value in values.items():
                        record.setdefault(name, value)

def bind_content(key, bindings):
    """Expose coherent content groups as named fields, leaving decoration in CSS."""
    path = ROOT / 'components' / key / 'RecordTemplate.html'
    markup = soup(shield(path.read_text()))
    fields, defaults = [], {}
    for selector, name, caption, kind in bindings:
        node = markup.select_one(selector)
        if node is None:
            continue
        fields.append(field(name, caption, kind))
        if kind == 'file':
            defaults[name] = node.get('src', '')
            node['src'] = V(name)
        else:
            defaults[name] = inner(node)
            node.clear()
            node.append(V(name, 'html'))
    path.write_text(dump(markup))
    base = M['classes'][key].get('parent') or key
    add_fields(base, fields, defaults)

bind_content('zhp_banner_home', [('.hero__stats-text','trust_text','Подпись блока доверия','html')])
bind_content('zhp_video', [('.hero__label','label','Надзаголовок','html'),
                          ('.video-card__video img','img','Обложка видео','file')])
bind_content('zhp_parent_group', [('.header__button','button','Текст кнопки','html')])
for key in ['zhp_program','zhp_program_service']:
    bind_content(key, [('.program__benefits','benefits','Преимущества программы','html'),
                       ('.program__image img','img','Иллюстрация программы','file')])
bind_content('zhp_first_step', [('.hero__label','label','Надзаголовок','html'),
                               ('.first-step__image img','img','Иллюстрация','file')])
for i in range(3):
    bind_content('zhp_first_step', [
        (f'.first-step__card:nth-child({i+1}) .first-step__card-title',f'action{i+1}_title','Карточка '+str(i+1)+' — заголовок','html'),
        (f'.first-step__card:nth-child({i+1}) .first-step__card-text',f'action{i+1}_text','Карточка '+str(i+1)+' — описание','html'),
    ])
bind_content('zhp_socials', [('.socials__main-title','subtitle','Подзаголовок','html')])

# Reused list templates must include the parent's full view explicitly.
for key in ['zhp_articles_home', 'zhp_articles_featured',
            'zhp_specialists_home', 'zhp_specialists_team']:
    (ROOT / 'components' / key / 'RecordTemplateFull.html').write_text(
        '<?php\n// Inherit the full object view from the base component.\n'
        'include $nc_parent_field_path;\n'
    )

for key in ['zhp_articles', 'zhp_specialists']:
    (ROOT / 'components' / key / 'EditCond.html').write_text('''<?php
// Netcat's compact object editor may omit the SEO/URL panel entirely.
// Preserve the object's URL keyword unless the editor explicitly posts it.
if ($posting && !array_key_exists('f_Keyword', $_POST)) {
    $f_Keyword = (string)$db->get_var(
        'SELECT Keyword FROM Message' . (int)$classID . ' WHERE Message_ID=' . (int)$message
    );
}
?>
''')

edit_part('zhp_specialists_team', 'RecordTemplate', lambda text: re.sub(
    r'(<div class="team__item-desc">).*?(</div>)',
    lambda match: match[1] + dump(V('position')) + match[2], text, flags=re.S
))
edit_part('zhp_articles_featured', 'RecordTemplate', lambda text: re.sub(
    r'(<div class="blog__item-meta">).*?(</div>)',
    lambda match: match[1] + "<?= date('d.m.Y', strtotime($f_date)) ?> • "
    + dump(V('readtime')) + match[2], text, flags=re.S
))

# Blog categories are not specialist qualifications. Labels are infoblock settings.
article_filters = [('all', 'Все статьи'), ('teens', 'Подросткам'),
                   ('parents', 'Родителям'), ('specialists', 'Специалистам')]
filter_markup = '<nav class="specialists-block__filters" aria-label="Категории статей">\n'
for category_key, caption in article_filters:
    name = 'filter_' + category_key
    M['classes']['zhp_articles']['settings'][name] = {
        'caption': 'Фильтр: ' + caption, 'type': 'string', 'default_value': caption,
    }
    value = '' if category_key == 'all' else category_key
    filter_markup += (
        '<button type="button" data-article-category="' + value + '" '
        'class="specialists-block__filter<?= '
        '(($_GET[\'category\'] ?? \'\') === \'' + value + '\') '
        '? \' specialists-block__filter--active\' : \'\' ?>">'
        + dump(V(name, settings=True)) + '</button>\n'
    )
filter_markup += '</nav>'
edit_part('zhp_articles', 'FormPrefix', lambda text: re.sub(
    r'<nav class="specialists-block__filters">.*?</nav>',
    lambda _: filter_markup, text, flags=re.S
))

add_fields('zhp_calculator', [field('img', 'Иллюстрация калькулятора', 'file'),
                           field('img_mobile', 'Иллюстрация на телефоне', 'file')],
           {'img': ASSET + 'img/calc.png', 'img_mobile': ASSET + 'img/calc2.png'})
edit_part('zhp_calculator', 'RecordTemplate', lambda text: text.replace(
    ASSET + 'img/calc.png', dump(V('img'))
).replace(ASSET + 'img/calc2.png', dump(V('img_mobile'))).replace('</input>', ''))

social_path = ROOT / 'components/zhp_socials/RecordTemplate.html'
social_markup = soup(shield(social_path.read_text()))
social_fields, social_defaults = [], {}
for item, network in zip(social_markup.select('.socials__item'),
                         ['instagram', 'vk', 'telegram', 'youtube']):
    for index, image in enumerate(item.find_all('img')):
        name = network + '_img' + (str(index + 1) if index else '')
        social_fields.append(field(name, network.upper() + ' — изображение ' + str(index + 1), 'file'))
        social_defaults[name] = image.get('src', '')
        image['src'] = V(name)
social_path.write_text(dump(social_markup))
add_fields('zhp_socials', social_fields, social_defaults)

# Include the source newsletter on list pages as a native shared-record variant.
subscription_parts = {
    part.stem: part.read_text()
    for part in (ROOT / 'components/zhp_subscription').glob('*.html')
}
subscription_parts['Settings'] = '<?php $ignore_cc = 1; $ignore_sub = 1; ?>'
cls('zhp_subscription_shared', 'Подписка — другие страницы', [], 'name',
    subscription_parts, parent='zhp_subscription')
for url in ['/blog/', '/o-nas/foto/', '/o-nas/spetsialisty/']:
    add(url, 'zhp_subscription_shared', 'Подписка на рассылку')
add('/o-nas/spetsialisty/', 'zhp_certificates_home', 'Дипломы и сертификаты')
# Certificates precede the newsletter, as in specialists.html.
blocks = PAGES['/o-nas/spetsialisty/']['blocks']
blocks[-2], blocks[-1] = blocks[-1], blocks[-2]

template_settings = {
    'site_name': {'caption': 'Название проекта', 'type': 'string', 'default_value': 'Жизнь подростка'},
    'copyright': {'caption': 'Текст после года в подвале', 'type': 'string',
                  'default_value': 'Жизнь подростка. Все права защищены.'},
    'support_title': {'caption': 'Заголовок поддержки в статье', 'type': 'string',
                      'default_value': 'Нужна поддержка?'},
    'support_button': {'caption': 'Кнопка поддержки', 'type': 'string',
                       'default_value': 'Связаться с нами'},
    'noindex': {'caption': 'Запретить индексацию (снять перед рабочим запуском)',
                'type': 'checkbox', 'default_value': 1},
}

def setting(name):
    return "<?= htmlspecialchars((string)($template_settings['" + name + "'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"

header_path = OUT / 'Header.html'
header = header_path.read_text()
header = re.sub(r'<\?php if\(shortcode\(\'\[demo\]\'\).*?\?>.*?<\?php } \?>', '', header, flags=re.S)
header = header.replace('<meta name="robots" content="noindex,nofollow">',
    '<?php if (!empty($template_settings[\'noindex\'])) { ?>'
    '<meta name="robots" content="noindex,nofollow"><?php } ?>')
header = header.replace('<main>', '<main id="main-content">')
header = header.replace('class="header__logo"',
                        'class="header__logo" aria-label="' + setting('site_name') + '"')
header = header.replace("$db->get_var(\"SELECT Class_ID FROM Class WHERE Keyword='zhp_regions' AND ClassTemplate=0\")",
                        "$db->get_var('SELECT Class_ID FROM Sub_Class WHERE Sub_Class_ID=57')")
header_path.write_text(header)
footer_path = OUT / 'Footer.html'
footer_path.write_text(re.sub(
    r'©\s*2024 Жизнь подростка\. Все права защищены\.',
    lambda _: "© <?= date('Y') ?> " + setting('copyright'), footer_path.read_text()
))
edit_part('zhp_articles', 'RecordTemplateFull', lambda text: text.replace(
    '<h2>Нужна поддержка?</h2>', '<h2>' + setting('support_title') + '</h2>'
).replace('>Связаться с нами</a>', '>' + setting('support_button') + '</a>'))

# Preserve the actual article sidebar from article.html, including related cards.
related = prepare(page('article').select_one('.article-page__articles'))
container = related.select_one('.article-page__related-list')
card = copy.deepcopy(container.select_one('.article-page__related-item'))
replace(card, 'h4', raw('<a href="<?= $fullLink ?>">') + V('name') + raw('</a>'))
replace(card, '.blog__item-tag', raw(category))
replace(card, '.blog__item-meta', raw("<?= date('d.m.Y', strtotime($f_date)) ?> • ") + V('readtime'))
card.img['src'] = V('img'); card.img['alt'] = V('name')
related_parts = split(related, container, card)
related_parts['Settings'] = '''<?php
$ignore_cc=1; $ignore_sub=1; $curPos=0; $recNum=4;
$query_where='a.Message_ID <> '.(int)($GLOBALS['zhp_current_article'] ?? 0);
?>'''
related_parts['RecordTemplateFull'] = '<?php include $nc_parent_field_path; ?>'
cls('zhp_articles_related', 'Статьи — рядом с подробной статьёй', [], 'name',
    related_parts, parent='zhp_articles')

template_settings['support_text'] = {
    'caption':'Описание поддержки в статье', 'type':'textarea',
    'default_value':plain(page('article').select_one('.article-page__aside-text')),
}
add_fields('zhp_articles', [field('support_img','Иллюстрация поддержки в статье','file')],
           {'support_img':ASSET+'img/banner.png'})
banner = prepare(page('article').select_one('.article-page__banner'))
replace(banner, '.article-page__aside-title', raw(setting('support_title')))
replace(banner, '.article-page__aside-text', raw(setting('support_text')))
replace(banner, '.header__button', raw(setting('support_button')))
banner.img['src'] = V('support_img')
banner.a['href'] = '#consult'; banner.a['data-zhp-consult'] = ''
sidebar = '''<?php
// Reuse the article collection through a native display template.
$zhp_related = $db->get_row(
    "SELECT s.Sub_Class_ID, s.Subdivision_ID, c.Class_ID AS View_ID FROM Sub_Class s " .
    "JOIN Class c ON c.ClassTemplate=s.Class_ID AND c.Keyword='zhp_articles_related' " .
    "WHERE s.Catalogue_ID=".(int)$catalogue." AND s.Class_ID=".(int)$classID.
    " AND s.Class_Template_ID=0 AND s.Checked=1 ORDER BY s.Priority LIMIT 1", ARRAY_A
);
if ($zhp_related) {
    $zhp_previous_article = $GLOBALS['zhp_current_article'] ?? 0;
    $GLOBALS['zhp_current_article'] = (int)$f_RowID;
    echo nc_objects_list((int)$zhp_related['Subdivision_ID'], (int)$zhp_related['Sub_Class_ID'],
                         'nc_ctpl='.(int)$zhp_related['View_ID']);
    $GLOBALS['zhp_current_article'] = $zhp_previous_article;
}
?>''' + dump(banner)
edit_part('zhp_articles', 'RecordTemplateFull', lambda text: re.sub(
    r'<div class="article-page__right">.*?</div>\s*</div>\s*</article>',
    lambda _: '<div class="article-page__right">' + sidebar + '</div>\n</div>\n</article>',
    text, flags=re.S
))

root_settings = (OUT / 'Settings.html').read_text()
root_settings = root_settings.replace('<?php', '''<?php
// Netcat resolves defaults and overrides through the layout/section hierarchy.
$template_settings = $nc_core->subdivision->get_template_settings($sub);
''', 1)
(OUT / 'Settings.html').write_text(root_settings)
M['template_settings'] = template_settings
M['layouts'] = {
    'home': 'Главная', 'inner': 'Внутренняя', 'about': 'О проекте',
    'service': 'Услуги', 'blog': 'Блог и подробная статья',
    'specialists': 'Специалисты и подробная карточка', 'gallery': 'Фотогалерея',
    'contacts': 'Контакты', 'error': '404', 'success': 'Заявка отправлена',
}
for key in M['layouts']:
    directory = OUT / key
    directory.mkdir(exist_ok=True)
    # Parent macros are parsed by Netcat, not emulated by a PHP dispatcher.
    (directory / 'Header.html').write_text(
        '%Header\n<div class="zhp-layout zhp-layout--' + key + '" data-zhp-layout="' + key + '">\n'
    )
    (directory / 'Footer.html').write_text('</div>\n%Footer\n')
    (directory / 'Settings.html').write_text('<?php\n// Shared settings are inherited from the parent layout.\n')
for definition in PAGES.values():
    url = definition['url']
    definition['layout'] = (
        'home' if url == '/' else 'about' if url == '/o-nas/' else
        'blog' if url == '/blog/' else 'specialists' if url == '/o-nas/spetsialisty/' else
        'gallery' if url == '/o-nas/foto/' else 'contacts' if url == '/kontakty/' else
        'error' if url == '/404/' else 'success' if url == '/zayavki/' else
        'service' if any(b['class'] == 'zhp_banner_service' for b in definition['blocks']) else 'inner'
    )

# Approved regional fields only; integration passwords are never public shortcodes.
(OUT / 'regions-widget.html').write_text('''<?php
// Existing Netcat component 223 / infoblock 57. Do not create a second directory.
$region_fields = array(
    'main_domain', 'codename', 'city', 'inCity', 'cityregion', 'cityregionRP',
    'cityRP', 'cityDP', 'address', 'phone', 'phone2', 'email', 'regim', 'licinfo',
    'max', 'telegram', 'whatsapp', 'vk', 'youtube', 'instagram', 'inst', 'mapcode',
    'email_to', 'email_from', 'email_enabled'
);
$region_field = $fields[0] ?? '';
if (in_array($region_field, $region_fields, true)) {
    static $region_rows = array();
    $region_host = strtolower(preg_replace('/:\\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''));
    if (!array_key_exists($region_host, $region_rows)) {
        $region_class = (int)$nc_core->db->get_var(
            'SELECT Class_ID FROM Sub_Class WHERE Sub_Class_ID=57'
        );
        $region_rows[$region_host] = $region_class ? $nc_core->db->get_row(
            'SELECT * FROM Message' . $region_class .
            " WHERE Sub_Class_ID=57 AND Checked=1 AND main_domain='" .
            $nc_core->db->escape($region_host) . "' ORDER BY Priority, Message_ID LIMIT 1", ARRAY_A
        ) : array();
    }
    echo (string)($region_rows[$region_host][$region_field] ?? '');
}
?>
''')

javascript = OUT / 'js/native.js'
js = javascript.read_text()
old_filter = "qa('.articles .specialists-block__filter').forEach((b,i)=>b.addEventListener('click',()=>{const cat=['','teens','parents','specialists'][i]||'';location.href='/blog/'+(cat?'?category='+cat:'');}));"
new_filter = "qa('[data-article-category]').forEach(b=>b.addEventListener('click',()=>{const cat=b.dataset.articleCategory;location.href='/blog/'+(cat?'?category='+encodeURIComponent(cat):'');}));"
javascript.write_text(js.replace(old_filter, new_filter))
