<?php


function get_utm(){
	if($_GET["utm_source"] OR $_GET["utm_network"] OR $_GET["rb_clickid"] OR $_GET["utm_term"] OR $_GET["pm_source"] OR $_GET["utm_source_type"] OR $_GET["yclid"]  OR $_GET["ahclid"] OR $_GET["openstat"]){
      $utm_get = nc_array_to_string($_GET, array( 'prefix' => '', 'element' => '%KEY=%ELEMENT', 'divider' => '&', 'suffix'  => '' ));
	  unset($_SESSION['utm_get']);
	  $_SESSION['utm_get']=$utm_get;
      $utm_result = $utm_get;
    }else{
		$utm_result = $_SESSION['utm_get'];
	}
	return $utm_result;
}function addalt($html){	/*$xml = new SimpleXMLElement($html);	foreach ($xml->xpath('//img') as $img) {		if (!array_key_exists('alt', $img->attributes())) {			$img->addAttribute('alt', 'img');		}	}	return $xml->asXML();*/		 preg_match_all('/<img (.*?)\/>/', $html, $images);	 if(!is_null($images))	 {		foreach($images[1] as $index => $value)		 {			 if(!preg_match('/alt=/', $value))			 {				$new_img = str_replace('<img', '<img alt="image"', $images[0][$index]);				$html = str_replace($images[0][$index], $new_img, $html);			 }		 }	 }	 $post_title = "picture";	 $html = preg_replace('/(alt=")(.*?)(")/i', '$1'.$post_title.'$3', $html);	return $html;}

function shortcode($text){

	$main_domain = nc_core()->widget->generate('regions', array('main_domain'));
	
	$city = nc_core()->widget->generate('regions', array('city'));
	
	$cityRP = nc_core()->widget->generate('regions', array('cityRP'));
	
	$cityDP = nc_core()->widget->generate('regions', array('cityDP'));
	
	$inCity = nc_core()->widget->generate('regions', array('inCity'));

	$cityregion = nc_core()->widget->generate('regions', array('cityregion'));
	
	$cityregionRP = nc_core()->widget->generate('regions', array('cityregionRP'));

	$address = nc_core()->widget->generate('regions', array('address'));

	$phone = nc_core()->widget->generate('regions', array('phone'));
	$phone2 = nc_core()->widget->generate('regions', array('phone2'));

	$email = nc_core()->widget->generate('regions', array('email'));
	$whatsapp = nc_core()->widget->generate('regions', array('whatsapp'));
	$mapcode = nc_core()->widget->generate('regions', array('mapcode'));
	
	$inst = nc_core()->widget->generate('regions', array('inst'));
	$vk = nc_core()->widget->generate('regions', array('vk'));
	$regim = nc_core()->widget->generate('regions', array('regim'));
	$telegram = nc_core()->widget->generate('regions', array('telegram'));
	$max = nc_core()->widget->generate('regions', array('max'));
	
	//$form = nc_objects_list(67, 14, "nc_ctpl=206");

	//$vrachi = nc_objects_list(7, 346);
	//$review = nc_objects_list(7, 347);
	//$free_cons = nc_objects_list(7, 348);
	//$contacts = nc_objects_list(7, 349);
	//$stoproc = nc_objects_list(7, 402);
	//$cert = nc_objects_list(7, 403);
	
	$text = str_replace( "[vrachi]", $vrachi, $text);
	$text = str_replace( "[review]", $review, $text);
	$text = str_replace( "[free_cons]", $free_cons, $text);
	$text = str_replace( "[contacts]", $contacts, $text);
	$text = str_replace( "[stoproc]", $stoproc, $text);
	$text = str_replace( "[cert]", $cert, $text);

	$text = str_replace( "[main_domain]", $main_domain, $text); 
	$text = str_replace( "[city]", $city, $text); 
	$text = str_replace( "[cityRP]", $cityRP, $text); 
	$text = str_replace( "[phone]", $phone, $text); 
	$text = str_replace( "[phone2]", $phone2, $text);
	$text = str_replace( "[email]", $email, $text); 
	$text = str_replace( "[inCity]", $inCity, $text); 
	$text = str_replace( "[cityregionRP]", $cityregionRP, $text); 
	$text = str_replace( "[cityregion]", $cityregion, $text); 
	$text = str_replace( "[address]", $address, $text); 
	$text = str_replace( "[whatsapp]", $whatsapp, $text); 
	$text = str_replace( "[mapcode]", $mapcode, $text);  
	$text = str_replace( "[form]", $form, $text); 
	$text = str_replace( "[inst]", $inst, $text); 
	$text = str_replace( "[vk]", $vk, $text); 
	$text = str_replace( "[regim]", $regim, $text);
	$text = str_replace( "[telegram]", $telegram, $text);
	$text = str_replace( "[max]", $max, $text);
	    
    return  $text;
}


function shortcode_component($text){

	$main_domain = nc_core()->widget->generate('regions', array('main_domain'));
	
	$city = nc_core()->widget->generate('regions', array('city'));
	
	$cityRP = nc_core()->widget->generate('regions', array('cityRP'));
	
	$cityDP = nc_core()->widget->generate('regions', array('cityDP'));
	
	$inCity = nc_core()->widget->generate('regions', array('inCity'));

	$cityregion = nc_core()->widget->generate('regions', array('cityregion'));
	
	$cityregionRP = nc_core()->widget->generate('regions', array('cityregionRP'));

	$address = nc_core()->widget->generate('regions', array('address'));

	$phone = nc_core()->widget->generate('regions', array('phone'));
	$phone2 = nc_core()->widget->generate('regions', array('phone2'));

	$email = nc_core()->widget->generate('regions', array('email'));
	$whatsapp = nc_core()->widget->generate('regions', array('whatsapp'));
	$mapcode = nc_core()->widget->generate('regions', array('mapcode'));
	
	$inst = nc_core()->widget->generate('regions', array('inst'));
	$vk = nc_core()->widget->generate('regions', array('vk'));
	$regim = nc_core()->widget->generate('regions', array('regim'));
	$telegram = nc_core()->widget->generate('regions', array('telegram'));
	$max = nc_core()->widget->generate('regions', array('max'));
	
	
	//$form = nc_objects_list(67, 14, "nc_ctpl=206");

	

	$text = str_replace( "[main_domain]", $main_domain, $text); 
	$text = str_replace( "[city]", $city, $text); 
	$text = str_replace( "[cityRP]", $cityRP, $text); 
	$text = str_replace( "[phone]", $phone, $text); 
	$text = str_replace( "[phone2]", $phone2, $text); 
	$text = str_replace( "[email]", $email, $text); 
	$text = str_replace( "[inCity]", $inCity, $text); 
	$text = str_replace( "[cityregionRP]", $cityregionRP, $text); 
	$text = str_replace( "[cityregion]", $cityregion, $text); 
	$text = str_replace( "[address]", $address, $text); 
	$text = str_replace( "[whatsapp]", $whatsapp, $text); 
	$text = str_replace( "[mapcode]", $mapcode, $text);  
	$text = str_replace( "[form]", $form, $text); 
	$text = str_replace( "[inst]", $inst, $text); 
	$text = str_replace( "[vk]", $vk, $text); 
	$text = str_replace( "[regim]", $regim, $text);
	$text = str_replace( "[telegram]", $telegram, $text);
	$text = str_replace( "[max]", $max, $text);
      
    return  $text;
}


function formatDate($day, $month, $year) {

    // Создаем объект DateTime

    $date = DateTime::createFromFormat('d-m-Y', "{$day}-{$month}-{$year}");



    // Форматируем дату

    $formattedDate = $date->format('d F Y');



    // Меняем английские названия месяцев на русские

    $months = [

        'January' => 'января',

        'February' => 'февраля',

        'March' => 'марта',

        'April' => 'апреля',

        'May' => 'мая',

        'June' => 'июня',

        'July' => 'июля',

        'August' => 'августа',

        'September' => 'сентября',

        'October' => 'октября',

        'November' => 'ноября',

        'December' => 'декабря'

    ];



    $formattedDate = str_replace(array_keys($months), array_values($months), $formattedDate);

    

    return $formattedDate;

}


/* =====================================================================
   SCHEMA.ORG + OPEN GRAPH для medelta.clinic
   ---------------------------------------------------------------------
   Заменяет старые функции (скопированные из medresurs с чужими данными).
   Данные тянутся из виджета regions, с fallback на реальные значения
   МедНовы, чтобы схема не ломалась, если поле в regions пустое.

   Подключение в <head> (Header.html), ОДИН РАЗ на всех страницах:
       <?php
           print_open_graph_tags();
           print_website_schema();
           print_organization_schema();
           print_medicalorganization_schema();
       ?>

   Точечно на нужных страницах (в шаблоне соответствующего компонента):
       print_breadcrumbs_schema_from_html($breadcrumbs_html);  // где есть крошки
       print_faq_schema($faq_items);                           // страница/раздел FAQ
       print_article_schema(...);                              // страница статьи
       print_service_schema(...);                              // страница услуги
       print_review_schema($reviews);                          // блок отзывов
   ===================================================================== */

/* ---------- Общий помощник: собираем реквизиты организации ---------- */
if (!function_exists('medelta_org_data')) {
    function medelta_org_data() {
        $nc = nc_core();
        $g  = function ($k) use ($nc) { return $nc->widget->generate('regions', array($k)); };

        $main_domain = $g('main_domain') ?: 'medelta.clinic';
        $address     = $g('address')     ?: 'ул. Коваля, д. 80Б';
        $city        = $g('city')        ?: 'Донецк';
        $region_raw  = trim((string)$g('cityregion'));
        // отбрасываем обрывки типа "и области" из виджета
        $region      = (mb_strlen($region_raw, 'UTF-8') > 5 && mb_stripos($region_raw, 'и обла', 0, 'UTF-8') !== 0) ? $region_raw : '';
        $email       = $g('email')       ?: 'info@medelta.clinic';
        $phone_raw   = $g('phone')       ?: '+7(949)766-70-56';
        $lat         = $g('map_latitude');
        $lng         = $g('map_longitude');

        // телефон в формат +7XXXXXXXXXX
        $tel = preg_replace('~[^0-9+]~', '', $phone_raw);
        if ($tel && strpos($tel, '+') !== 0) $tel = '+' . $tel;

        $scheme = (!empty($nc->HTTPS)) ? 'https' : 'https';
        $base   = $scheme . '://' . $main_domain;

        return array(
            'name'        => 'ООО «Центр медицины и психологии»',
            'brand'       => 'МедЭльта',
            'domain'      => $main_domain,
            'base'        => $base,
            'logo'        => $base . '/favicon/android-chrome-512x512.png',
            'email'       => $email,
            'telephone'   => $tel,
            'address'     => $address,
            'city'        => $city,
            'region'      => $region,
            'country'     => 'RU',
            'lat'         => $lat,
            'lng'         => $lng,
            'social'      => array_filter(array(
                $g('vk'), $g('telegram'), $g('inst'),
            )),
        );
    }
}

if (!function_exists('medelta_postal_address')) {
    function medelta_postal_address($d) {
        $addr = array(
            '@type'           => 'PostalAddress',
            'streetAddress'   => $d['address'],
            'addressLocality' => $d['city'],
            'addressCountry'  => $d['country'],
        );
        if (!empty($d['region'])) $addr['addressRegion'] = $d['region'];
        return $addr;
    }
}

if (!function_exists('medelta_ld')) {
    function medelta_ld($schema) {
        echo "\n" . '<script type="application/ld+json">'
           . json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
           . '</script>' . "\n";
    }
}

if (!function_exists('medelta_current_url')) {
    function medelta_current_url() {
        $d = medelta_org_data();
        return $d['base'] . (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/');
    }
}

/* =====================================================================
   1. WebSite  (для sitelinks searchbox / общий узел сайта)
   ===================================================================== */
if (!function_exists('print_website_schema')) {
    function print_website_schema() {
        $d = medelta_org_data();
        medelta_ld(array(
            '@context' => 'https://schema.org',
            '@type'    => 'WebSite',
            '@id'      => $d['base'] . '/#website',
            'url'      => $d['base'] . '/',
            'name'     => $d['brand'],
            'inLanguage' => 'ru-RU',
            'publisher' => array('@id' => $d['base'] . '/#organization'),
        ));
    }
}

/* =====================================================================
   2. Organization
   ===================================================================== */
if (!function_exists('print_organization_schema')) {
    function print_organization_schema() {
        $d = medelta_org_data();
        $schema = array(
            '@context' => 'https://schema.org',
            '@type'    => 'Organization',
            '@id'      => $d['base'] . '/#organization',
            'name'     => $d['name'],
            'alternateName' => $d['brand'],
            'url'      => $d['base'] . '/',
            'logo'     => $d['logo'],
            'email'    => $d['email'],
            'telephone'=> $d['telephone'],
            'address'  => medelta_postal_address($d),
        );
        if ($d['social']) $schema['sameAs'] = array_values($d['social']);
        medelta_ld($schema);
    }
}

/* =====================================================================
   3. MedicalOrganization / MedicalClinic
   ===================================================================== */
if (!function_exists('print_medicalorganization_schema')) {
    function print_medicalorganization_schema() {
        $d = medelta_org_data();
        $nc = nc_core();
        $descr = $nc->widget->generate('regions', array('ncDescription'))
               ?: 'Наркология и психиатрия в Донецке. Анонимная помощь при зависимостях, вывод из запоя, кодирование, лечение зависимостей. Работаем круглосуточно.';

        $schema = array(
            '@context'   => 'https://schema.org',
            '@type'      => array('MedicalClinic', 'MedicalOrganization'),
            '@id'        => $d['base'] . '/#medicalclinic',
            'name'       => $d['brand'],
            'legalName'  => $d['name'],
            'description'=> $descr,
            'url'        => $d['base'] . '/',
            'logo'       => $d['logo'],
            'image'      => $d['logo'],
            'email'      => $d['email'],
            'telephone'  => $d['telephone'],
            'priceRange' => '₽₽',
            'medicalSpecialty' => array('Psychiatric'),
            'address'    => medelta_postal_address($d),
            'openingHoursSpecification' => array(array(
                '@type'     => 'OpeningHoursSpecification',
                'dayOfWeek' => array('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'),
                'opens'     => '00:00',
                'closes'    => '23:59',
            )),
            'availableService' => array(
                array('@type' => 'MedicalProcedure', 'name' => 'Вывод из запоя'),
                array('@type' => 'MedicalProcedure', 'name' => 'Кодирование от алкоголизма'),
                array('@type' => 'MedicalProcedure', 'name' => 'Лечение наркомании'),
                array('@type' => 'MedicalTherapy',   'name' => 'Реабилитация'),
            ),
        );
        if ($d['lat'] && $d['lng']) {
            $schema['geo'] = array(
                '@type'     => 'GeoCoordinates',
                'latitude'  => (float)$d['lat'],
                'longitude' => (float)$d['lng'],
            );
        }
        if ($d['social']) $schema['sameAs'] = array_values($d['social']);
        medelta_ld($schema);
    }
}

/* оставляем старое имя как алиас, чтобы существующие вызовы не падали */
if (!function_exists('print_localbusiness_schema')) {
    function print_localbusiness_schema() {
        // всё покрывает MedicalOrganization выше — дубль не нужен
        return;
    }
}

/* =====================================================================
   4. BreadcrumbList — из HTML хлебных крошек
   ===================================================================== */
if (!function_exists('print_breadcrumbs_schema_from_html')) {
    function print_breadcrumbs_schema_from_html($html) {
        if (empty($html)) return;
        $d = medelta_org_data();

        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);

        $items = array();
        $position = 1;

        // ссылки-крошки
        foreach ($xpath->query('//a') as $link) {
            $href = $link->getAttribute('href');
            $name = trim($link->nodeValue);
            if ($href === '' || $name === '') continue;
            $url = (strpos($href, 'http') === 0) ? $href : $d['base'] . $href;
            $items[] = array(
                '@type'    => 'ListItem',
                'position' => $position++,
                'name'     => $name,
                'item'     => $url,
            );
        }
        // последний (текущий, без ссылки)
        foreach ($xpath->query('//text()[not(ancestor::a)]') as $node) {
            $name = trim($node->nodeValue);
            if ($name !== '' && $name !== '|' && $name !== '/' && $name !== '—') {
                $items[] = array(
                    '@type'    => 'ListItem',
                    'position' => $position++,
                    'name'     => $name,
                    'item'     => medelta_current_url(),
                );
                break;
            }
        }
        if (!$items) return;

        medelta_ld(array(
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $items,
        ));
    }
}

/* =====================================================================
   5. FAQPage — принимает массив [ ['q'=>'...','a'=>'...'], ... ]
   ===================================================================== */
if (!function_exists('print_faq_schema')) {
    function print_faq_schema($items) {
        if (empty($items) || !is_array($items)) return;

        $entities = array();
        foreach ($items as $it) {
            $q = trim(strip_tags(isset($it['q']) ? $it['q'] : ''));
            $a = trim(strip_tags(isset($it['a']) ? $it['a'] : ''));
            if ($q === '' || $a === '') continue;
            $entities[] = array(
                '@type'          => 'Question',
                'name'           => $q,
                'acceptedAnswer' => array(
                    '@type' => 'Answer',
                    'text'  => $a,
                ),
            );
        }
        if (!$entities) return;

        medelta_ld(array(
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => $entities,
        ));
    }
}

/* =====================================================================
   6. Article — страница статьи блога
   ===================================================================== */
if (!function_exists('print_article_schema')) {
    function print_article_schema($title, $text = '', $image = '', $date_published = '', $date_modified = '') {
        if (function_exists('shortcode_component')) {
            $title = shortcode_component((string)$title);
            $text  = shortcode_component((string)$text);
        }
        $title = trim(strip_tags((string)$title));
        if ($title === '') return;
        $d = medelta_org_data();

        if ($image && strpos($image, 'http') !== 0) $image = $d['base'] . $image;

        $schema = array(
            '@context'         => 'https://schema.org',
            '@type'            => 'Article',
            'headline'         => $title,
            'mainEntityOfPage' => array('@type' => 'WebPage', '@id' => medelta_current_url()),
            'author'           => array('@id' => $d['base'] . '/#organization'),
            'publisher'        => array('@id' => $d['base'] . '/#organization'),
            'inLanguage'       => 'ru-RU',
        );
        if ($text)  $schema['description'] = mb_substr(trim(strip_tags($text)), 0, 300, 'UTF-8');
        if ($image) $schema['image'] = $image;
        if ($date_published) $schema['datePublished'] = $date_published;
        if ($date_modified)  $schema['dateModified']  = $date_modified;
        elseif ($date_published) $schema['dateModified'] = $date_published;

        medelta_ld($schema);
    }
}

/* =====================================================================
   7. Service / MedicalProcedure — страница услуги
   ===================================================================== */
if (!function_exists('print_service_schema')) {
    function print_service_schema($name, $description = '', $price = '') {
        /* раскрываем шорткоды ([inCity] и т.п.) ДО очистки */
        if (function_exists('shortcode_component')) {
            $name        = shortcode_component((string)$name);
            $description = shortcode_component((string)$description);
            $price       = shortcode_component((string)$price);
        }
        $name = trim(strip_tags((string)$name));
        if ($name === '') return;
        $d = medelta_org_data();

        /* Service несёт offers/provider легитимно, MedicalProcedure даёт
           медицинскую семантику — двойной тип валиден */
        $schema = array(
            '@context' => 'https://schema.org',
            '@type'    => array('Service', 'MedicalProcedure'),
            'name'     => $name,
            'url'      => medelta_current_url(),
            'provider' => array('@id' => $d['base'] . '/#medicalclinic'),
        );
        if ($description) $schema['description'] = mb_substr(trim(strip_tags($description)), 0, 300, 'UTF-8');

        $num = preg_replace('~[^0-9]~', '', (string)$price);
        if ($num !== '') {
            $schema['offers'] = array(
                '@type'         => 'Offer',
                'price'         => $num,
                'priceCurrency' => 'RUB',
                'availability'  => 'https://schema.org/InStock',
            );
        }
        medelta_ld($schema);
    }
}

/* =====================================================================
   8. Review + AggregateRating — блок отзывов
      $reviews = [ ['author'=>'', 'text'=>'', 'rating'=>5, 'date'=>''], ... ]
   ===================================================================== */
if (!function_exists('print_review_schema')) {
    function print_review_schema($reviews) {
        if (empty($reviews) || !is_array($reviews)) return;
        $d = medelta_org_data();

        $list = array();
        $sum = 0; $cnt = 0;
        foreach ($reviews as $r) {
            $author = trim(strip_tags(isset($r['author']) ? $r['author'] : ''));
            $text   = trim(strip_tags(isset($r['text']) ? $r['text'] : ''));
            if ($author === '' || $text === '') continue;
            $rating = isset($r['rating']) ? (int)$r['rating'] : 5;
            if ($rating < 1 || $rating > 5) $rating = 5;
            $sum += $rating; $cnt++;

            $item = array(
                '@type'         => 'Review',
                'author'        => array('@type' => 'Person', 'name' => $author),
                'reviewBody'    => $text,
                'reviewRating'  => array(
                    '@type'       => 'Rating',
                    'ratingValue' => $rating,
                    'bestRating'  => 5,
                    'worstRating' => 1,
                ),
            );
            if (!empty($r['date'])) $item['datePublished'] = $r['date'];
            $list[] = $item;
        }
        if (!$cnt) return;

        $schema = array(
            '@context'        => 'https://schema.org',
            '@type'           => 'MedicalClinic',
            '@id'             => $d['base'] . '/#medicalclinic',
            'name'            => $d['brand'],
            'aggregateRating' => array(
                '@type'       => 'AggregateRating',
                'ratingValue' => round($sum / $cnt, 1),
                'reviewCount' => $cnt,
                'bestRating'  => 5,
                'worstRating' => 1,
            ),
            'review' => $list,
        );
        medelta_ld($schema);
    }
}

/* =====================================================================
   9. Open Graph
   ===================================================================== */
if (!function_exists('print_open_graph_tags')) {
    function print_open_graph_tags() {
        $d  = medelta_org_data();
        $nc = nc_core();

        $descr = $nc->widget->generate('regions', array('ncDescription'))
               ?: 'Наркология и психиатрия в Донецке. Анонимно, круглосуточно.';
        $image = $nc->widget->generate('regions', array('ncSMO_Image'));
        if (!$image) {
            $image = $d['logo'];
        } elseif (strpos($image, 'http') !== 0) {
            $image = $d['base'] . $image;
        }
        $title = 'МедЭльта — наркология и психиатрия в Донецке';

        echo "\n"
           . '<meta property="og:type" content="website">' . "\n"
           . '<meta property="og:site_name" content="МедЭльта">' . "\n"
           . '<meta property="og:locale" content="ru_RU">' . "\n"
           . '<meta property="og:title" content="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '">' . "\n"
           . '<meta property="og:description" content="' . htmlspecialchars($descr, ENT_QUOTES, 'UTF-8') . '">' . "\n"
           . '<meta property="og:url" content="' . htmlspecialchars(medelta_current_url(), ENT_QUOTES, 'UTF-8') . '">' . "\n"
           . '<meta property="og:image" content="' . htmlspecialchars($image, ENT_QUOTES, 'UTF-8') . '">' . "\n";
    }
}

/* =====================================================================
   10. Physician — страница конкретного врача
   ===================================================================== */
if (!function_exists('print_physician_schema')) {
    function print_physician_schema($fio, $position = '', $img = '', $bio = '') {
        $fio = trim(strip_tags((string)$fio));
        if ($fio === '') return;
        $d = medelta_org_data();

        $schema = array(
            '@context' => 'https://schema.org',
            '@type'    => 'Physician',
            'name'     => $fio,
            'url'      => medelta_current_url(),
        );
        if ($img) $schema['image'] = (strpos($img, 'http') === 0 ? $img : $d['base'] . $img);
        if ($bio) $schema['description'] = mb_substr(trim(strip_tags($bio)), 0, 300, 'UTF-8');

        medelta_ld($schema);
    }
}

if (!function_exists('is_homepage')) {
    function is_homepage() {
        return (empty($_SERVER['REQUEST_URI']) || $_SERVER['REQUEST_URI'] === '/');
    }
}
