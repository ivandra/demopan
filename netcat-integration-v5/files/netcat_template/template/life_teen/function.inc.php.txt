<?php
function get_utm()
{
    $values = [];
    foreach (
        ["utm_source", "utm_medium", "utm_campaign", "utm_content", "utm_term", "yclid", "gclid"]
        as $k
    ) {
        if (isset($_GET[$k]) && is_string($_GET[$k])) {
            $values[$k] = mb_substr($_GET[$k], 0, 250);
        }
    }
    if ($values) {
        $_SESSION["utm_get"] = http_build_query($values);
    }
    return (string) ($_SESSION["utm_get"] ?? "");
}
function project_region_values()
{
    static $regions = [];
    $host = strtolower(preg_replace('/:\d+$/', "", $_SERVER["HTTP_HOST"] ?? ""));
    if (!array_key_exists($host, $regions)) {
        $regions[$host] = [];
        if (preg_match("/\A[a-z0-9.-]+\z/", $host)) {
            $nc = nc_core();
            $row = (array) $nc->db->get_row(
                "SELECT * FROM Message223 WHERE Sub_Class_ID=57 AND Checked=1 AND main_domain='" .
                    $nc->db->escape($host) .
                    "' ORDER BY Priority, Message_ID LIMIT 1",
                ARRAY_A,
            );
            $fields = [
                "main_domain",
                "codename",
                "city",
                "inCity",
                "cityregion",
                "cityregionRP",
                "cityRP",
                "cityDP",
                "address",
                "phone",
                "phone2",
                "email",
                "regim",
                "licinfo",
                "max",
                "telegram",
                "whatsapp",
                "vk",
                "youtube",
                "instagram",
                "inst",
                "mapcode",
                "email_to",
                "email_from",
                "email_enabled",
            ];
            $regions[$host] = array_intersect_key($row, array_fill_keys($fields, true));
        }
    }
    return $regions[$host];
}
function shortcode($text)
{
    $region = project_region_values();
    return preg_replace_callback(
        "/\[([a-zA-Z_][a-zA-Z0-9_]*)\]/",
        function ($m) use ($region) {
            return (string) ($region[$m[1]] ?? "");
        },
        (string) $text,
    );
}
function shortcode_component($text)
{
    return shortcode($text);
}
function formatDate($day, $month, $year)
{
    $d = DateTime::createFromFormat("!d-m-Y", sprintf("%02d-%02d-%04d", $day, $month, $year));
    return $d ? $d->format("d.m.Y") : "";
}

function project_title($value)
{
    $value = preg_replace("~<img\b[^>]*hero__title-line[^>]*>~i", "", (string) $value);
    return shortcode_component($value);
}
function project_text($value)
{
    return trim((string) $value) === "<p>Материал готовится к публикации.</p>"
        ? ""
        : shortcode_component($value);
}
function project_label($value)
{
    return str_replace("‑", "‑<wbr>", htmlspecialchars(shortcode($value), ENT_QUOTES, "UTF-8"));
}
function project_shared_block($keyword)
{
    global $db, $catalogue;
    $block = $db->get_row(
        "SELECT s.Subdivision_ID,s.Sub_Class_ID FROM Sub_Class s JOIN Class c USING(Class_ID) " .
            "WHERE s.Catalogue_ID=" .
            (int) $catalogue .
            " AND s.Checked=1 AND c.Keyword='" .
            $db->escape($keyword) .
            "' ORDER BY s.Class_Template_ID,s.Priority,s.Sub_Class_ID LIMIT 1",
        ARRAY_A,
    );
    return $block
        ? nc_objects_list((int) $block["Subdivision_ID"], (int) $block["Sub_Class_ID"])
        : "";
}

function project_breadcrumbs()
{
    global $sub, $sub_level_count, $current_catalogue, $current_sub, $action, $nc_core;
    if ((int)$sub === (int)$current_catalogue['Title_Sub_ID'] && $action !== 'full') {
        return '';
    }
    $link = '<a href="%URL">%NAME</a>';
    $template = ['prefix'=>'', 'suffix'=>'', 'active'=>$link, 'active_link'=>$link, 'unactive'=>$link, 'divider'=>"\n"];
    $html = nc_browse_path_range(-1, max(0, (int)$sub_level_count - 1), $template, 0, 2);
    $items = [['url'=>'/', 'name'=>'Главная']];
    $document = new DOMDocument();
    $before = libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="UTF-8"><div>'.$html.'</div>');
    libxml_clear_errors();
    libxml_use_internal_errors($before);
    foreach ($document->getElementsByTagName('a') as $anchor) {
        $url = $anchor->getAttribute('href');
        if ($url === '/' || !$anchor->textContent) { continue; }
        $items[] = ['url'=>$url, 'name'=>$anchor->textContent];
    }
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $last = end($items);
    if ($action === 'full' && parse_url($last['url'], PHP_URL_PATH) !== $path) {
        $title = $nc_core->page->get_h1() ?: $nc_core->page->get_title();
        $title = preg_replace('/ — Жизнь подростка$/u', '', strip_tags((string)$title));
        $items[] = ['url'=>$path, 'name'=>$title];
    }
    if (count($items) === 1) {
        $items[] = ['url'=>$path, 'name'=>$current_sub['Subdivision_Name']];
    }
    $output = '<nav class="project-breadcrumbs" aria-label="Хлебные крошки"><div class="wrap"><ol>';
    foreach ($items as $index=>$item) {
        $name = htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8');
        $output .= '<li>'.($index === count($items)-1
            ? '<span aria-current="page">'.$name.'</span>'
            : '<a href="'.htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8').'">'.$name.'</a>').'</li>';
    }
    return $output.'</ol></div></nav>';
}

function project_article_counts($infoblock)
{
    $db = nc_core()->db;
    $class = (int)$db->get_var('SELECT Class_ID FROM Sub_Class WHERE Sub_Class_ID='.(int)$infoblock);
    $counts = [];
    if (!$class) { return $counts; }
    foreach ((array)$db->get_results('SELECT category,COUNT(*) AS total FROM Message'.$class.' WHERE Checked=1 AND Sub_Class_ID='.(int)$infoblock.' GROUP BY category', ARRAY_A) as $row) {
        $counts[$row['category']] = (int)$row['total'];
    }
    return $counts;
}

function project_specialty_tokens($values)
{
    static $map = null;
    if ($map === null) {
        $db = nc_core()->db;
        $map = [];
        if ($db->get_var("SELECT Classificator_ID FROM Classificator WHERE Table_Name='LifeSpecialistCategory'")) {
            foreach ((array)$db->get_results('SELECT LifeSpecialistCategory_ID,LifeSpecialistCategory_Name,Value FROM Classificator_LifeSpecialistCategory', ARRAY_A) as $row) {
                $map[(string)$row['LifeSpecialistCategory_ID']] = $row['Value'];
                $map[$row['LifeSpecialistCategory_Name']] = $row['Value'];
                $map[$row['Value']] = $row['Value'];
            }
        }
    }
    $tokens = [];
    foreach ((array)$values as $value) {
        if (!is_scalar($value)) { continue; }
        $value = (string)$value;
        if (isset($map[$value])) { $tokens[] = $map[$value]; continue; }
        foreach (preg_split('/[\s,]+/u', $value, -1, PREG_SPLIT_NO_EMPTY) as $part) {
            $token = $map[$part] ?? $part;
            if (preg_match('/\A[a-z][a-z0-9_-]*\z/i', $token)) { $tokens[] = $token; }
        }
    }
    return implode(' ', array_unique($tokens));
}

function project_document_url($path)
{
    $path = (string)$path;
    return substr($path, 0, 1) === '/' && substr($path, 0, 2) !== '//' && !preg_match('/[\x00-\x20]/', $path) ? $path : '';
}

function project_specialist_documents($message = 0)
{
    global $catalogue;
    $db = nc_core()->db;
    $class = (int)$db->get_var("SELECT Class_ID FROM Class WHERE Keyword='life_specialists' AND ClassTemplate=0");
    $field = (int)$db->get_var("SELECT Field_ID FROM Field WHERE Class_ID=".$class." AND Field_Name='documents' AND TypeOfData_ID=11");
    if (!$class || !$field) { return []; }
    $where = $message ? ' AND specialist.Message_ID='.(int)$message : '';
    $rows = (array)$db->get_results(
        'SELECT files.Name,files.Path,files.Preview,specialist.fio,specialist.Message_ID FROM Multifield files '.
        'JOIN Message'.$class.' specialist ON specialist.Message_ID=files.Message_ID '.
        'JOIN Sub_Class block ON block.Sub_Class_ID=specialist.Sub_Class_ID '.
        'WHERE files.Field_ID='.$field.' AND specialist.Checked=1 AND block.Checked=1 AND block.Catalogue_ID='.(int)$catalogue.$where.
        ' ORDER BY specialist.Priority,specialist.Message_ID,files.Priority,files.ID', ARRAY_A
    );
    $documents = [];
    foreach ($rows as $row) {
        $path = project_document_url($row['Path']);
        if (!$path) { continue; }
        $image = preg_match('/\.(?:png|jpe?g|webp|gif|avif)(?:\?|$)/i', $path) ? $path : project_document_url($row['Preview']);
        $documents[] = ['url'=>$path, 'image'=>$image, 'name'=>$row['Name'], 'specialist'=>$row['fio'], 'message'=>(int)$row['Message_ID']];
    }
    return $documents;
}

function project_global_documents()
{
    global $catalogue;
    $db = nc_core()->db;
    $class = (int)$db->get_var("SELECT Class_ID FROM Class WHERE Keyword='life_certificates' AND ClassTemplate=0");
    if (!$class) { return []; }
    $rows = (array)$db->get_results('SELECT certificate.Message_ID,certificate.name FROM Message'.$class.' certificate JOIN Sub_Class block USING(Sub_Class_ID) WHERE certificate.Checked=1 AND block.Checked=1 AND block.Catalogue_ID='.(int)$catalogue.' ORDER BY certificate.Priority,certificate.Message_ID', ARRAY_A);
    $documents = [];
    foreach ($rows as $row) {
        $image = project_document_url(nc_file_path($class, $row['Message_ID'], 'img'));
        $file = project_document_url(nc_file_path($class, $row['Message_ID'], 'file'));
        if (!$image && !$file) { continue; }
        $documents[] = ['url'=>$file ?: $image, 'image'=>$image, 'name'=>$row['name'], 'specialist'=>'', 'message'=>0];
    }
    return $documents;
}

function project_document_slides(array $documents, $variant = 'certificates')
{
    $output = '';
    foreach ($documents as $document) {
        $url = htmlspecialchars($document['url'], ENT_QUOTES, 'UTF-8');
        $title = trim($document['specialist'].' — '.$document['name'], ' —');
        $title = htmlspecialchars($title ?: 'Диплом или сертификат', ENT_QUOTES, 'UTF-8');
        $image = $document['image'];
        $isImage = preg_match('/\.(?:png|jpe?g|webp|gif|avif)(?:\?|$)/i', $document['url']);
        $fancybox = $isImage ? ' data-fancybox="project-documents"' : (preg_match('/\.pdf(?:\?|$)/i', $document['url']) ? ' data-fancybox="project-documents" data-type="iframe"' : ' target="_blank" rel="noopener"');
        $output .= '<div class="swiper-slide '.$variant.'__item" data-specialist-document="'.(int)$document['message'].'"><a href="'.$url.'"'.$fancybox.' aria-label="'.$title.'">';
        $output .= $image ? '<img src="'.htmlspecialchars($image, ENT_QUOTES, 'UTF-8').'" alt="'.$title.'" loading="lazy">' : '<span class="project-document-preview">'.$title.'</span>';
        $output .= '</a></div>';
    }
    return $output;
}

function project_is_specialist_directory($subdivision)
{
    $db = nc_core()->db;
    return (bool)$db->get_var("SELECT block.Sub_Class_ID FROM Sub_Class block JOIN Class component USING(Class_ID) WHERE component.Keyword='life_specialists' AND block.Class_Template_ID=0 AND block.Checked=1 AND block.Subdivision_ID=".(int)$subdivision.' LIMIT 1');
}

function project_specialist_documents_block($message = 0, $includeGlobal = true)
{
    $documents = project_specialist_documents($message);
    if ($includeGlobal) { $documents = array_merge($documents, project_global_documents()); }
    if (!$documents) { return ''; }
    return '<section class="certificates-block"><div class="wrap"><div class="certificates-block__content">'.
        '<div class="certificates-block__text"><p class="hero__label">Дипломы и сертификаты</p>'.
        '<h2 class="hero__title">Профессионализм в основе <span class="hero__title-accent">нашей работы</span></h2>'.
        '<p class="hero__description">Наши специалисты постоянно развиваются, проходят обучение и повышают квалификацию, чтобы оказать вам качественную поддержку.</p></div>'.
        '<div class="certificates-block__slider"><div class="certificates-block__list swiper4"><div class="swiper-wrapper">'.
        project_document_slides($documents, 'certificates-block').'</div></div></div>'.
        '<div class="certificates-block__actions"><a class="specialists__all-btn" href="/o-nas/sertifikaty/">Все дипломы и сертификаты →</a></div>'.
        '</div></div></section>';
}
