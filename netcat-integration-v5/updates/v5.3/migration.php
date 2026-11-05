<?php
function up_dom($html) {
    $document = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="UTF-8"><div id="migration-root">'.(string)$html.'</div>');
    libxml_clear_errors(); libxml_use_internal_errors($previous);
    return [ $document, new DOMXPath($document) ];
}
function up_nodes(DOMXPath $xpath, $class, $context = null) {
    return $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " '.$class.' ")]', $context);
}
function up_node(DOMXPath $xpath, $class, $context = null) { return up_nodes($xpath, $class, $context)->item(0); }
function up_text($node) { return trim(preg_replace('/[\s\x{00a0}]+/u', ' ', $node ? $node->textContent : '')); }
function up_inner($node) {
    if (!$node) { return ''; }
    $output = ''; foreach ($node->childNodes as $child) { $output .= $node->ownerDocument->saveHTML($child); }
    return trim($output);
}
function up_plain_lines($html) {
    if (strpos((string)$html, '<') === false) { return trim((string)$html); }
    list($document,$xpath) = up_dom($html); $lines = [];
    foreach ($document->getElementsByTagName('li') as $node) { if (up_text($node) !== '') { $lines[] = up_text($node); } }
    if ($lines) { return implode("\n",$lines); }
    foreach (iterator_to_array($document->getElementsByTagName('br')) as $node) { $node->parentNode->replaceChild($document->createTextNode("\n"),$node); }
    foreach ($document->getElementsByTagName('p') as $node) { $node->appendChild($document->createTextNode("\n")); }
    return trim($document->getElementById('migration-root')->textContent);
}
function up_settings_array($code, $variable) {
    if (trim((string)$code) === '') { return []; }
    return (static function ($source,$name) { $settings_array=[];$CustomSettings=[];eval($source);return (array)${$name}; })((string)$code,$variable);
}
function up_specialist_plan(array $row) {
    $values = ['city_from_region'=>0,'city'=>'','work_format'=>'','experience'=>'','clients_count'=>'','clients_caption'=>'','reception_from_region'=>0,'reception_address'=>'','reception_caption'=>'','online_caption'=>'','online_platforms'=>'','session_duration'=>'','session_price'=>'','question_caption'=>'','bio_heading'=>'','bio_quote'=>'','methods_heading'=>'','methods_intro'=>'','education_heading'=>''];
    $children = ['life_specialist_methods'=>[],'life_specialist_education'=>[]];
    if (trim((string)$row['stats']) !== '') {
        list($d,$x) = up_dom($row['stats']);$items=up_nodes($x,'specialist-page__stat');
        if ($items->length > 3 || !$items->length) { throw new RuntimeException('Не распознана статистика специалиста №'.(int)$row['Message_ID'].'. Данные не изменялись.'); }
        foreach ($items as $index=>$item) {
            $value=up_text(up_node($x,'specialist-page__stat-value',$item));$caption=up_text(up_node($x,'specialist-page__stat-label',$item));
            if ($index===0) { $values['city_from_region']=(int)($value==='[city]');$values['city']=$value==='[city]'?'':$value;$values['work_format']=$caption; }
            elseif ($index===1) { $values['experience']=$value; }
            else { $values['clients_count']=$value;$values['clients_caption']=$caption; }
        }
    }
    if (trim((string)$row['details']) !== '') {
        list($d,$x)=up_dom($row['details']);$items=up_nodes($x,'specialist-page__detail-row');
        if ($items->length>3 || !$items->length) { throw new RuntimeException('Не распознаны сведения о приёме специалиста №'.(int)$row['Message_ID'].'.'); }
        foreach ($items as $index=>$item) {
            $text=up_node($x,'specialist-page__detail-text',$item);$caption=$text ? $text->getElementsByTagName('span')->item(0) : null;$label=up_text($caption);
            if ($caption) { $caption->parentNode->removeChild($caption); } $rest=up_text($text);
            if ($index===0) { $values['reception_caption']=$label;$values['reception_from_region']=(int)(strpos($rest,'[address]')!==false);$values['reception_address']=$values['reception_from_region']?'':$rest; }
            elseif ($index===1) { $values['online_caption']=$label;$values['online_platforms']=trim($rest,' ()'); }
            else { $parts=preg_split('~\s*/\s*~u',$rest,2);$values['session_duration']=$parts[0] ?? '';$values['session_price']=$parts[1] ?? ''; }
        }
        $values['question_caption']=preg_replace('/\s*→\s*$/u','',up_text(up_node($x,'first-step__link')));
    }
    $values['bio']=(string)$row['bio'];
    if (trim((string)$row['bio'])!=='') {
        list($d,$x)=up_dom($row['bio']);$content=up_node($x,'specialist-page__about-content');
        if ($content) { $values['bio']=up_inner($content);$values['bio_heading']=up_text(up_node($x,'specialist-page__about-title'));$values['bio_quote']=up_text(up_node($x,'specialist-page__quote-bubble')); }
        else { $values['bio_heading']='О специалисте'; }
    }
    foreach (['methods','education'] as $kind) {
        if (trim((string)$row[$kind])==='') { continue; }
        list($d,$x)=up_dom($row[$kind]);$values[$kind.'_heading']=up_text(up_node($x,'specialist-page__about-title'));
        if ($kind==='methods') { $values['methods_intro']=up_text(up_node($x,'hero__description')); }
        $items=up_nodes($x,$kind==='methods'?'specialist-page__method-card':'specialist-page__education-item');
        if (!$items->length) { throw new RuntimeException('Не распознан раздел '.$kind.' специалиста №'.(int)$row['Message_ID'].'.'); }
        foreach ($items as $index=>$item) {
            $name=up_text(up_node($x,$kind==='methods'?'specialist-page__method-title':'specialist-page__education-name',$item));
            $desc=up_node($x,$kind==='methods'?'specialist-page__method-desc':'specialist-page__education-desc',$item);
            $description=up_plain_lines(up_inner($desc));$period='';
            if ($kind==='education') { $lines=preg_split('/\R/u',$description,-1,PREG_SPLIT_NO_EMPTY);$last=trim((string)end($lines));if(preg_match('/\A[12][0-9]{3}(?:\s*[—–-]\s*[12][0-9]{3})?\z/u',$last)){$period=array_pop($lines);$description=trim(implode("\n",array_map('trim',$lines)));} }
            $image=$item->getElementsByTagName('img')->item(0);$image=$image ? $image->getAttribute('src') : '';
            if (!$name && !$description && !$image) { continue; }
            if (!$name) { throw new RuntimeException('У элемента '.$kind.' не заполнено название.'); }
            $child=['specialist'=>(int)$row['Message_ID'],'name'=>$name,'description'=>$description,'Priority'=>$index+1,'Keyword'=>'v53-'.(int)$row['Message_ID'].'-'.($index+1)];
            if ($kind==='education') { $child['period']=$period; }
            if ($image) { if(substr($image,0,1)!=='/'||substr($image,0,2)==='//'||strpos($image,'..')!==false||!is_file($_SERVER['DOCUMENT_ROOT'].$image)) { throw new RuntimeException('Не найдено изображение в образовании или методах специалиста.'); } $child['img']=$image; }
            $children[$kind==='methods'?'life_specialist_methods':'life_specialist_education'][]=$child;
        }
    }
    $values['clients_count']=trim($values['clients_count'].' '.$values['clients_caption']);
    $values['online_platforms']=trim($values['online_caption'].($values['online_platforms']!==''?' ('.$values['online_platforms'].')':''));
    $values['methods']=$values['methods_intro'];
    foreach(['clients_caption','reception_caption','online_caption','question_caption','bio_heading','methods_heading','methods_intro','education_heading'] as $unused){unset($values[$unused]);}
    $values['LastUpdated']=$row['LastUpdated'];
    return ['values'=>$values,'children'=>$children];
}

function up_article_plan(array $row) {
    list($document,$xpath)=up_dom($row['text']);$tips=up_nodes($xpath,'article-page__tips-list');$children=[];$heading='';
    foreach(iterator_to_array($tips) as $list){
        $previous=$list->previousSibling;while($previous&&$previous->nodeType===XML_TEXT_NODE&&trim($previous->textContent)===''){$previous=$previous->previousSibling;}
        if($previous&&preg_match('/\Ah[1-6]\z/',$previous->nodeName)){$heading=up_text($previous);$previous->parentNode->removeChild($previous);}
        foreach(up_nodes($xpath,'article-page__tip-item',$list) as $index=>$item){
            $name=up_text(up_node($xpath,'article-page__tip-title',$item));$description=up_text(up_node($xpath,'article-page__tip-text',$item));$image=$item->getElementsByTagName('img')->item(0);$image=$image?$image->getAttribute('src'):'';
            if(!$name){throw new RuntimeException('У совета в статье не заполнен заголовок.');}
            $child=['article'=>(int)$row['Message_ID'],'name'=>$name,'description'=>$description,'Priority'=>$index+1,'Keyword'=>'v53-article-'.(int)$row['Message_ID'].'-'.($index+1)];
            if($image){if(substr($image,0,1)!=='/'||substr($image,0,2)==='//'||strpos($image,'..')!==false||!is_file($_SERVER['DOCUMENT_ROOT'].$image)){throw new RuntimeException('Не найдено изображение совета в статье.');}$child['img']=$image;}$children[]=$child;
        }
        $list->parentNode->removeChild($list);
    }
    foreach(iterator_to_array(up_nodes($xpath,'article-page__text')) as $container){while($container->firstChild){$container->parentNode->insertBefore($container->firstChild,$container);}$container->parentNode->removeChild($container);}
    foreach($xpath->query('.//*[@class]',$document->getElementById('migration-root')) as $node){$node->removeAttribute('class');}
    $text=up_inner($document->getElementById('migration-root'));
    return ['values'=>['text'=>$text,'tips_heading'=>$heading,'LastUpdated'=>$row['LastUpdated']],'children'=>['life_article_tips'=>$children]];
}
