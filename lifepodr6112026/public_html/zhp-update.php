<?php
$up_cli = PHP_SAPI === 'cli';
$up_ajax = !$up_cli && isset($_GET['ajax']);
$up_ok = true;
$up_done = true;
$up_started = microtime(true);
$up_units = 0;
if ($up_ajax) {
    ob_start();
    $up_buffer = ob_get_level();
    register_shutdown_function(function () {
        global $up_ok, $up_done, $up_buffer;
        $body = '';
        while (ob_get_level() >= $up_buffer) { $body = ob_get_clean().$body; }
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR], true)) {
            $up_ok = false;
            $body .= "\nОшибка PHP. Подробности находятся в журнале хостинга.";
        }
        $status = http_response_code();
        http_response_code($up_ok ? 200 : ($status >= 400 ? $status : 500));
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');
        echo json_encode(['ok'=>$up_ok,'done'=>$up_done,'message'=>trim(strip_tags($body))], JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
    });
}
function up_out($text) { echo PHP_SAPI === 'cli' ? $text."\n" : '<p>'.htmlspecialchars($text, ENT_QUOTES, 'UTF-8').'</p>'; }
function up_ident($name) {
    if (!preg_match('/\A[A-Za-z0-9_]+\z/', $name)) { throw new RuntimeException('Недопустимое имя поля.'); }
    return chr(96).$name.chr(96);
}
function up_value($value) { return $value === null ? 'NULL' : "UNHEX('".bin2hex((string)$value)."')"; }
function up_query($sql) {
    global $db;
    $result = $db->query($sql);
    if ($result === false || $db->last_error) { throw new RuntimeException('Ошибка БД: '.$db->last_error); }
    return $result;
}
function up_sql_update($table, $key, $id, array $values) {
    $set = [];
    foreach ($values as $name=>$value) { $set[] = up_ident($name).'='.up_value($value); }
    up_query('UPDATE '.up_ident($table).' SET '.implode(',', $set).' WHERE '.up_ident($key).'='.(int)$id);
}
function up_store($path, $data) {
    $prefix = "<?php\nhttp_response_code(404);\nexit;\n__halt_compiler();\n";
    $bytes = $prefix.json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    $temporary = $path.'.tmp-'.bin2hex(random_bytes(5));
    if (file_put_contents($temporary, $bytes, LOCK_EX) !== strlen($bytes) || !rename($temporary, $path)) {
        throw new RuntimeException('Не удалось сохранить состояние обновления.');
    }
    @chmod($path, 0600);
}
function up_read($path) {
    if (!is_file($path)) { return []; }
    $bytes = file_get_contents($path);
    $marker = "__halt_compiler();\n";
    $offset = strpos($bytes, $marker);
    if ($offset === false) { throw new RuntimeException('Повреждено состояние обновления.'); }
    return json_decode(substr($bytes, $offset+strlen($marker)), true, 512, JSON_THROW_ON_ERROR);
}
function up_save() { global $up_state, $up_folder; up_store($up_folder.'/state.php', $up_state); }
function up_checkpoint($text) {
    global $up_cli, $up_done, $up_started, $up_units;
    up_out($text);
    if (!$up_cli && (++$up_units >= 8 || microtime(true)-$up_started >= 3)) {
        $up_done = false;
        exit;
    }
}
function up_path($relative) {
    if (!$relative || $relative[0] === '/' || strpos($relative, '\\') !== false || in_array('..', explode('/', $relative), true)) {
        throw new RuntimeException('Недопустимый путь обновления.');
    }
    return __DIR__.'/'.$relative;
}
function up_relative($path) {
    if (strpos($path, __DIR__.'/') !== 0) { throw new RuntimeException('Файлы Netcat находятся вне корня сайта.'); }
    return substr($path, strlen(__DIR__)+1);
}
function up_hash($path) {
    $bytes = file_get_contents($path);
    if ($bytes === false) { throw new RuntimeException('Не удалось прочитать файл пакета.'); }
    if (preg_match('/\.(?:html|php|txt|css|js|json)$/i', $path) || basename($path) === '.htaccess') { $bytes = str_replace("\r\n", "\n", $bytes); }
    return hash('sha256', $bytes);
}
function up_payload($source) {
    global $up_stage, $up_manifest;
    if (!isset($up_manifest['hashes'][$source])) { throw new RuntimeException('Файл отсутствует в манифесте обновления.'); }
    $path = $up_stage.'/'.$source;
    if (!is_file($path) || !hash_equals($up_manifest['hashes'][$source], up_hash($path))) {
        throw new RuntimeException('Пакет загружен не полностью или изменён: '.$source);
    }
    return $path;
}
function up_copy($source, $destination) {
    if (!is_dir(dirname($destination)) && !mkdir(dirname($destination), 0755, true)) { throw new RuntimeException('Не удалось создать каталог обновления.'); }
    $temporary = $destination.'.tmp-'.bin2hex(random_bytes(5));
    if (!copy($source, $temporary) || hash_file('sha256', $temporary) !== hash_file('sha256', $source) || !rename($temporary, $destination)) {
        throw new RuntimeException('Не удалось записать файл обновления.');
    }
    @chmod($destination, 0644);
    if (function_exists('opcache_invalidate')) { @opcache_invalidate($destination, true); }
}
function up_rows_encode(array $rows) {
    foreach ($rows as &$row) { foreach ($row as &$value) { if ($value !== null) { $value = base64_encode((string)$value); } } unset($value); } unset($row);
    return $rows;
}
function up_rows_decode(array $rows) {
    foreach ($rows as &$row) { foreach ($row as &$value) { if ($value !== null) { $value = base64_decode($value, true); } } unset($value); } unset($row);
    return $rows;
}
function up_component_folder(array $row) {
    global $nc_core;
    $path = rtrim($nc_core->CLASS_TEMPLATE_FOLDER, '/').'/'.trim($row['File_Path'], '/');
    up_relative($path.'/Class.html');
    if (!is_dir($path)) { throw new RuntimeException('Не найден каталог компонента '.$row['Keyword']); }
    return $path;
}
function up_prepare() {
    global $up_state, $up_manifest, $up_folder, $db, $nc_core;
    if (!empty($up_state['backup_complete'])) { return; }
    if (empty($up_state['plan_ready'])) {
        $up_state = ['version'=>'5.2.0','created_utc'=>gmdate('c'),'done'=>[],'files'=>[],'components'=>[],'original_site_checked'=>(int)$db->get_var('SELECT Checked FROM Catalogue WHERE Catalogue_ID=1')];
        foreach ($up_manifest['components'] as $key=>$parts) {
            $row = $db->get_row("SELECT * FROM Class WHERE Keyword='".$db->escape($key)."'", ARRAY_A);
            if (!$row || !(int)$row['File_Mode']) { throw new RuntimeException('Не найден файловый компонент '.$key); }
            $up_state['components'][$key] = $row;
            $folder = up_component_folder($row);
            foreach (glob($folder.'/*.html') as $file) { $up_state['files'][up_relative($file)] = null; }
            foreach (array_merge(['Class'], array_keys($parts)) as $part) { $up_state['files'][up_relative($folder.'/'.$part.'.html')] = null; }
        }
        $templates = (array)$db->get_results("SELECT * FROM Template WHERE File_Path='/life_teen/' OR File_Path LIKE '/life_teen/%'", ARRAY_A);
        $up_state['templates'] = $templates;
        foreach ($templates as $template) {
            $folder = rtrim($nc_core->TEMPLATE_FOLDER, '/').'/'.trim($template['File_Path'], '/');
            foreach (['Header','Footer','Settings','Template'] as $part) { $up_state['files'][up_relative($folder.'/'.$part.'.html')] = null; }
        }
        foreach ($up_manifest['theme'] as $relative=>$source) { $up_state['files']['netcat_template/template/life_teen/'.$relative] = null; }
        $up_state['files']['netcat/modules/default/function.inc.php'] = null;
        $specialists = (int)$db->get_var("SELECT Class_ID FROM Class WHERE Keyword='life_specialists' AND ClassTemplate=0");
        $up_state['specialists'] = $specialists;
        $up_state['category_field'] = $db->get_row("SELECT * FROM Field WHERE Class_ID=".$specialists." AND Field_Name='category'", ARRAY_A);
        if (!$up_state['category_field']) { throw new RuntimeException('Не найдено поле категорий специалистов.'); }
        $up_state['document_field'] = $db->get_row("SELECT * FROM Field WHERE Class_ID=".$specialists." AND Field_Name='documents'", ARRAY_A);
        $up_state['category_rows'] = (array)$db->get_results('SELECT Message_ID,category,LastUpdated FROM Message'.$specialists, ARRAY_A);
        $up_state['subdivisions'] = [];
        foreach ($up_manifest['empty_pages'] as $url) {
            $row = $db->get_row("SELECT Subdivision_ID,Checked,Read_Access_ID FROM Subdivision WHERE Catalogue_ID=1 AND Hidden_URL='".$db->escape($url)."'", ARRAY_A);
            if (!$row) { throw new RuntimeException('Не найден раздел '.$url); }
            if ((int)$db->get_var('SELECT COUNT(*) FROM Sub_Class WHERE Subdivision_ID='.(int)$row['Subdivision_ID'])) {
                throw new RuntimeException('Раздел уже заполнен: '.$url.'. Автоматическая смена его состояния остановлена.');
            }
            $up_state['subdivisions'][] = $row;
        }
        $list = $db->get_row("SELECT * FROM Classificator WHERE Table_Name='LifeSpecialistCategory'", ARRAY_A);
        if ($list && (int)$up_state['category_field']['TypeOfData_ID'] !== 10) { throw new RuntimeException('Имя списка категорий уже занято.'); }
        $times = (array)$db->get_results('SELECT Subdivision_ID,LastUpdated,LastModified FROM Subdivision WHERE Catalogue_ID=1',ARRAY_A);
        $backup = ['classes'=>up_rows_encode(array_values($up_state['components'])),'templates'=>up_rows_encode($templates),'category_field'=>up_rows_encode([$up_state['category_field']]),'document_field'=>up_rows_encode($up_state['document_field'] ? [$up_state['document_field']] : []),'categories'=>up_rows_encode($up_state['category_rows']),'subdivisions'=>up_rows_encode($up_state['subdivisions']),'section_times'=>up_rows_encode($times),'classifier'=>up_rows_encode($list ? [$list] : [])];
        up_store($up_folder.'/database.php', $backup);
        $up_state['plan_ready'] = true;
        up_save();
    }
    foreach ($up_state['files'] as $relative=>$info) {
        if ($info !== null) { continue; }
        $source = up_path($relative);
        $info = ['existed'=>is_file($source)];
        if ($info['existed']) {
            $target = $up_folder.'/files/'.$relative;
            if (!is_dir(dirname($target))) { mkdir(dirname($target), 0700, true); }
            if (!copy($source, $target) || hash_file('sha256', $source) !== hash_file('sha256', $target)) { throw new RuntimeException('Не удалось проверить резервную копию файла.'); }
            @chmod($target, 0600);
            $info['sha256'] = hash_file('sha256', $target);
        }
        $up_state['files'][$relative] = $info;
        up_save();
        up_checkpoint('Сохранён файл обновляемой части сайта.');
    }
    $up_state['backup_complete'] = true;
    up_save();
}
function up_categories() {
    global $up_state, $db, $nc_core;
    if (!empty($up_state['done']['categories'])) { return; }
    require_once $nc_core->ADMIN_FOLDER.'classificator.inc.php';
    $id = (int)$db->get_var("SELECT Classificator_ID FROM Classificator WHERE Table_Name='LifeSpecialistCategory'");
    if (!$id) {
        $id = (int)AddClassificatorCompleted('Специализации специалистов', 'LifeSpecialistCategory', 0, 0, 0);
        if (!$id) { throw new RuntimeException('Не создан список специализаций.'); }
        $up_state['classifier_created'] = true;
        up_save();
    }
    $labels = ['psychologist'=>'Психолог','psychotherapist'=>'Психотерапевт','family'=>'Семейный психолог','educator'=>'Педагог','psychiatrist'=>'Психиатр','yoga'=>'Инструктор йоги','other'=>'Другая специализация'];
    foreach ($up_state['category_rows'] as $row) {
        foreach (preg_split('/\s+/u', trim((string)$row['category']), -1, PREG_SPLIT_NO_EMPTY) as $token) {
            if (!isset($labels[$token]) && preg_match('/\A[a-z][a-z0-9_-]*\z/i', $token)) { $labels[$token] = $token; }
        }
    }
    $ids = [];
    foreach ($labels as $token=>$label) {
        $item = (int)$db->get_var("SELECT LifeSpecialistCategory_ID FROM Classificator_LifeSpecialistCategory WHERE Value='".$db->escape($token)."' LIMIT 1");
        if (!$item) {
            InsertInOneClassificatorCompleted($id, $label, count($ids)+1, $token);
            $item = (int)$db->get_var("SELECT LifeSpecialistCategory_ID FROM Classificator_LifeSpecialistCategory WHERE Value='".$db->escape($token)."' LIMIT 1");
        }
        if (!$item) { throw new RuntimeException('Не создана специализация.'); }
        $ids[$token] = $item;
    }
    $class = (int)$up_state['specialists'];
    up_query('ALTER TABLE Message'.$class.' MODIFY category TEXT NULL');
    foreach ($up_state['category_rows'] as $row) {
        $tokens = preg_split('/\s+/u', trim((string)$row['category']), -1, PREG_SPLIT_NO_EMPTY);
        $values = [];
        foreach ($tokens as $token) {
            if (!isset($ids[$token])) { throw new RuntimeException('Не распознана старая категория специалиста.'); }
            $values[] = $ids[$token];
        }
        $value = $values ? ','.implode(',', array_unique($values)).',' : '';
        up_sql_update('Message'.$class, 'Message_ID', $row['Message_ID'], ['category'=>$value,'LastUpdated'=>$row['LastUpdated']]);
    }
    up_sql_update('Field', 'Field_ID', $up_state['category_field']['Field_ID'], ['TypeOfData_ID'=>10,'Format'=>'LifeSpecialistCategory:checkbox:6','Description'=>'Специализации','NotNull'=>0]);
    $nc_core->component->clear_cache();
    $up_state['done']['categories'] = true;
    up_save();
    up_checkpoint('Категории специалистов перенесены в список Netcat.');
}
function up_documents_field() {
    global $up_state, $nc_core, $db;
    if (!empty($up_state['done']['documents'])) { return; }
    $class = (int)$up_state['specialists'];
    $field = (int)$db->get_var("SELECT Field_ID FROM Field WHERE Class_ID=".$class." AND Field_Name='documents'");
    if (!$field) {
        $field = (int)$nc_core->component->add_field($class, ['Field_Name'=>'documents','Description'=>'Дипломы и сертификаты','TypeOfData_ID'=>NC_FIELDTYPE_MULTIFILE,'Format'=>'0;multifile_min:0;multifile_max:50;use_resize:0;use_preview:0;','NotNull'=>0,'DoSearch'=>0]);
    }
    if (!$field) { throw new RuntimeException('Не создано поле документов специалиста.'); }
    $up_state['document_field_id'] = $field;
    $up_state['done']['documents'] = true;
    up_save();
    up_checkpoint('Добавлено поле документов в карточку специалиста.');
}
function up_apply() {
    global $up_state, $up_manifest, $nc_core, $db;
    up_prepare();
    up_categories();
    up_documents_field();
    if (empty($up_state['done']['runtime'])) {
        up_copy(up_payload($up_manifest['runtime']), up_path('netcat/modules/default/function.inc.php'));
        $up_state['done']['runtime'] = true; up_save(); up_checkpoint('Обновлены общие функции сайта.');
    }
    foreach ($up_manifest['theme'] as $relative=>$source) {
        $key = 'theme:'.$relative;
        if (!empty($up_state['done'][$key])) { continue; }
        up_copy(up_payload($source), up_path('netcat_template/template/life_teen/'.$relative));
        $up_state['done'][$key] = true; up_save(); up_checkpoint('Обновлена часть макета: '.$relative);
    }
    if (empty($up_state['done']['layout'])) {
        $root = null;
        foreach ($up_state['templates'] as $row) { if ($row['File_Path'] === '/life_teen/') { $root = $row; } }
        if (!$root) { throw new RuntimeException('Не найден общий макет.'); }
        $parts = [];
        foreach (['Header','Footer','Settings'] as $part) { $parts[$part] = file_get_contents(up_path('netcat_template/template/life_teen/'.$part.'.html')); }
        $editor = new nc_tpl_template_editor($nc_core->TEMPLATE_FOLDER, $db);
        $editor->load_template($root['Template_ID'], $root['File_Path']);
        $editor->save_new_template($parts, false);
        $up_state['done']['layout'] = true; up_save(); up_checkpoint('Обновлён общий макет с хлебными крошками.');
    }
    foreach ($up_manifest['components'] as $key=>$parts) {
        if (!empty($up_state['done']['component:'.$key])) { continue; }
        $row = $up_state['components'][$key];
        $folder = up_component_folder($row);
        foreach ($parts as $part=>$source) { up_copy(up_payload($source), $folder.'/'.$part.'.html'); }
        $tpl = new nc_tpl($nc_core->CLASS_TEMPLATE_FOLDER, $db);
        $tpl->load((int)$row['Class_ID'], 'Class');
        $master = '';
        foreach (['FormPrefix','RecordTemplate','FormSuffix'] as $part) {
            $content = file_get_contents($folder.'/'.$part.'.html');
            if ($part === 'RecordTemplate') { $content = nc_cleaned_RecordTemplate_of_string_service($content); }
            $master .= '<!-- '.$part.' -->'.$content.'<!-- /'.$part." -->\n";
        }
        $temporary = $folder.'/Class.html.tmp-'.bin2hex(random_bytes(5));
        if (file_put_contents($temporary,$master,LOCK_EX) !== strlen($master) || !rename($temporary,$folder.'/Class.html')) {
            throw new RuntimeException('Не удалось сохранить основной файл компонента.');
        }
        nc_tpl_parser::main2parts($tpl, '');
        $nc_core->event->execute(nc_event::AFTER_COMPONENT_UPDATED, (int)$row['Class_ID']);
        $up_state['done']['component:'.$key] = true; up_save(); up_checkpoint('Обновлён компонент: '.$key);
    }
    foreach ($up_state['subdivisions'] as $row) {
        $key = 'section:'.$row['Subdivision_ID'];
        if (!empty($up_state['done'][$key])) { continue; }
        up_sql_update('Subdivision','Subdivision_ID',$row['Subdivision_ID'],['Checked'=>1,'Read_Access_ID'=>1]);
        $nc_core->event->execute(nc_event::AFTER_SUBDIVISION_UPDATED, 1, (int)$row['Subdivision_ID']);
        $up_state['done'][$key] = true; up_save(); up_checkpoint('Пустой раздел оставлен видимым в меню.');
    }
    $nc_core->component->clear_cache();
    $nc_core->event->execute(nc_event::AFTER_SITE_UPDATED, 1);
    $up_state['complete'] = true;
    up_save();
    up_verify(false);
    up_out('Обновление 5.2 применено. Записи и исходная резервная копия сохранены. Следующий шаг — проверка сайта.');
}
function up_verify($runtime = true) {
    global $up_state, $up_manifest, $db, $up_cli;
    if (empty($up_state['complete'])) { throw new RuntimeException('Обновление 5.2 ещё не завершено. Продолжите режим update.'); }
    if (!$up_cli && function_exists('opcache_invalidate')) {
        foreach (array_keys($up_state['files']) as $relative) { @opcache_invalidate(up_path($relative),true); }
    }
    foreach ($up_manifest['theme'] as $relative=>$source) {
        if (!hash_equals($up_manifest['hashes'][$source], up_hash(up_path('netcat_template/template/life_teen/'.$relative)))) { throw new RuntimeException('Не совпадает обновлённый файл: '.$relative); }
    }
    if (!hash_equals($up_manifest['hashes'][$up_manifest['runtime']], up_hash(up_path('netcat/modules/default/function.inc.php')))) { throw new RuntimeException('Не совпадают общие функции сайта.'); }
    foreach ($up_manifest['components'] as $key=>$parts) {
        $folder = up_component_folder($up_state['components'][$key]);
        foreach ($parts as $part=>$source) {
            if ($part === 'RecordTemplate') {
                $actual = trim(str_replace("\r\n","\n",nc_cleaned_RecordTemplate_of_string_service(file_get_contents($folder.'/'.$part.'.html'))));
                $expected = trim(str_replace("\r\n","\n",file_get_contents(up_payload($source))));
                $matches = hash_equals(hash('sha256',$expected),hash('sha256',$actual));
            } else { $matches = hash_equals($up_manifest['hashes'][$source], up_hash($folder.'/'.$part.'.html')); }
            if (!$matches) { throw new RuntimeException('Не совпадает часть компонента '.$key.'/'.$part); }
        }
    }
    if ((int)$db->get_var('SELECT Checked FROM Catalogue WHERE Catalogue_ID=1') !== $up_state['original_site_checked']) { throw new RuntimeException('Изменилось состояние публикации самого сайта.'); }
    foreach ($up_state['subdivisions'] as $row) {
        $id = (int)$row['Subdivision_ID'];
        if (!(int)$db->get_var('SELECT Checked FROM Subdivision WHERE Subdivision_ID='.$id)) { throw new RuntimeException('Пустой раздел скрыт.'); }
        if ((int)$db->get_var('SELECT COUNT(*) FROM Sub_Class WHERE Subdivision_ID='.$id)) { throw new RuntimeException('В пустом разделе появились инфоблоки.'); }
    }
    if ((int)$db->get_var('SELECT TypeOfData_ID FROM Field WHERE Field_ID='.(int)$up_state['category_field']['Field_ID']) !== 10) { throw new RuntimeException('Категории не переведены в список Netcat.'); }
    if ((int)$db->get_var('SELECT TypeOfData_ID FROM Field WHERE Field_ID='.(int)$up_state['document_field_id']) !== 11) { throw new RuntimeException('Не найдено поле документов специалиста.'); }
    if ($runtime && !function_exists('project_breadcrumbs')) { throw new RuntimeException('Обновлённые функции ещё не загружены обработчиком PHP. Обновите страницу установщика или перезапустите обработчик.'); }
    up_out('Проверка обновления 5.2 пройдена.');
}
function up_restore_rows($table, array $rows) {
    foreach ($rows as $row) {
        $columns = array_map('up_ident', array_keys($row));
        up_query('REPLACE INTO '.up_ident($table).' ('.implode(',',$columns).') VALUES ('.implode(',',array_map('up_value',array_values($row))).')');
    }
}
function up_rollback() {
    global $up_state, $up_folder, $db, $nc_core;
    if (empty($up_state['backup_complete'])) { throw new RuntimeException('Полная копия обновляемой части сайта ещё не создана.'); }
    if (!$up_state['document_field'] && !empty($up_state['document_field_id']) && (int)$db->get_var('SELECT COUNT(*) FROM Multifield WHERE Field_ID='.(int)$up_state['document_field_id'])) {
        throw new RuntimeException('После обновления загружены документы специалистов. Сначала сохраните их; откат схемы остановлен до изменения данных.');
    }
    $backup = up_read($up_folder.'/database.php');
    foreach (['classes'=>'Class','templates'=>'Template','category_field'=>'Field'] as $key=>$table) { up_restore_rows($table,up_rows_decode($backup[$key])); }
    foreach (up_rows_decode($backup['subdivisions']) as $row) { $id=$row['Subdivision_ID'];unset($row['Subdivision_ID']);up_sql_update('Subdivision','Subdivision_ID',$id,$row); }
    foreach (up_rows_decode($backup['categories']) as $row) { $id=$row['Message_ID'];unset($row['Message_ID']);up_sql_update('Message'.$up_state['specialists'],'Message_ID',$id,$row); }
    up_query('ALTER TABLE Message'.(int)$up_state['specialists'].' MODIFY category CHAR(255) NULL');
    if (!$up_state['document_field']) {
        up_query("DELETE FROM Field WHERE Class_ID=".(int)$up_state['specialists']." AND Field_Name='documents'");
        if ($db->get_var("SHOW COLUMNS FROM Message".(int)$up_state['specialists']." LIKE 'documents'")) { up_query('ALTER TABLE Message'.(int)$up_state['specialists'].' DROP COLUMN documents'); }
    }
    if (!$backup['classifier']) {
        up_query("DELETE FROM Classificator WHERE Table_Name='LifeSpecialistCategory'");
        up_query('DROP TABLE IF EXISTS Classificator_LifeSpecialistCategory');
    }
    foreach ($up_state['files'] as $relative=>$info) {
        if ($info['existed']) { up_copy($up_folder.'/files/'.$relative,up_path($relative)); }
        elseif (is_file(up_path($relative))) { unlink(up_path($relative)); }
    }
    $nc_core->component->clear_cache();
    $nc_core->event->execute(nc_event::AFTER_SITE_UPDATED,1);
    foreach (up_rows_decode($backup['section_times'] ?? []) as $row) {
        $id=$row['Subdivision_ID'];unset($row['Subdivision_ID']);up_sql_update('Subdivision','Subdivision_ID',$id,$row);
    }
    $up_state['complete'] = false;
    $up_state['rolled_back'] = true;
    up_save();
    up_out('Откат обновления 5.2 завершён. Сайт восстановлен к состоянию перед обновлением.');
}
try {
    $mode = 'check';
    if ($up_cli) {
        $host = getenv('HTTP_HOST') ?: 'localhost';
        foreach (array_slice($argv,1) as $argument) {
            if (strpos($argument,'--host=') === 0) { $host = substr($argument,7); }
            elseif (in_array($argument,['check','update','verify','rollback'],true)) { $mode=$argument; }
            else { throw new RuntimeException('Неизвестный аргумент запуска.'); }
        }
        if (!preg_match('/\A[a-z0-9.-]+\z/i',$host)) { throw new RuntimeException('Некорректный домен.'); }
        $_SERVER['DOCUMENT_ROOT']=__DIR__;putenv('DOCUMENT_ROOT='.__DIR__);
        $_SERVER['HTTP_HOST']=$host;putenv('HTTP_HOST='.$host);
        $_SERVER['SERVER_NAME']=$host;$_SERVER['REQUEST_URI']='/';$_SERVER['REQUEST_METHOD']='GET';
        $_SERVER['REMOTE_ADDR']='127.0.0.1';$_SERVER['HTTP_USER_AGENT']='Netcat project updater';
    } else {
        $mode=(string)($_POST['mode']??'check');
        header('Cache-Control: no-store');header('X-Robots-Tag: noindex,nofollow');
    }
    @set_time_limit($up_cli?0:20);
    if (!ini_get('short_open_tag')) { throw new RuntimeException('Для Netcat нужен short_open_tag=On.'); }
    if (function_exists('opcache_invalidate')) { @opcache_invalidate(__DIR__.'/netcat/modules/default/function.inc.php',true); }
    require_once __DIR__.'/vars.inc.php';
    require_once $ROOT_FOLDER.'connect_io.php';
    $nc_core=nc_core();$db=$nc_core->db;$catalogue=1;
    $nc_core->modules->load_env();
    require_once $nc_core->ADMIN_FOLDER.'lang/'.$nc_core->lang->detect_lang().'.php';
    $nc_core->user->attempt_to_authorize();
    if (!$up_cli && !$nc_core->InsideAdminAccess()) { http_response_code(403);throw new RuntimeException('Войдите администратором Netcat на этом домене.'); }
    if (!in_array($mode,['check','update','verify','rollback'],true)) { throw new RuntimeException('Неизвестный режим.'); }
    if (!$up_cli) {
        if (empty($_SESSION['life_update_csrf'])) { $_SESSION['life_update_csrf']=bin2hex(random_bytes(32)); }
        if ($up_ajax && ($_SERVER['REQUEST_METHOD']!=='POST' || !hash_equals($_SESSION['life_update_csrf'],(string)($_POST['token']??'')))) { http_response_code(403);throw new RuntimeException('Обновление доступно только кнопками этой страницы.'); }
    }
    if ((int)$db->get_var('SELECT COUNT(*) FROM Catalogue') !== 1) { throw new RuntimeException('Обновление рассчитано на один сайт Netcat.'); }
    if (!class_exists('DOMDocument')) { throw new RuntimeException('Для хлебных крошек требуется расширение PHP DOM.'); }
    up_query("SET time_zone='+00:00'");
    $up_folder=rtrim($nc_core->FILES_FOLDER,'/').'/life_teen_v52';
    if (!is_dir($up_folder) && !mkdir($up_folder,0700,true)) { throw new RuntimeException('Не создана папка копии обновления.'); }
    if (!is_file($up_folder.'/.htaccess')) { file_put_contents($up_folder.'/.htaccess',"Require all denied\nDeny from all\n"); }
    $up_state=up_read($up_folder.'/state.php');
    $up_stage=__DIR__.'/netcat_template/template/life_teen/.updates/v5.2';
    $up_manifest=json_decode(file_get_contents($up_stage.'/manifest.json'),true,512,JSON_THROW_ON_ERROR);
    if (($up_manifest['version']??'')!=='5.2.0') { throw new RuntimeException('Нужен полный пакет обновления 5.2.'); }
    if (!$up_cli && !$up_ajax) {
        $token=htmlspecialchars($_SESSION['life_update_csrf'],ENT_QUOTES,'UTF-8');
        echo '<!doctype html><html lang="ru"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Обновление Жизнь подростка</title><style>body{font:17px/1.5 system-ui;background:#f7f3fb;color:#211a31;margin:0}main{max-width:950px;margin:35px auto;padding:28px;background:white;border-radius:24px}button{font:inherit;background:#7858dc;color:white;border:0;border-radius:12px;padding:12px 18px;margin:5px}button:disabled{opacity:.5}pre{background:#f6f3fb;white-space:pre-wrap;padding:18px;border-radius:16px;max-height:420px;overflow:auto}</style><main><h1>Обновление «Жизнь подростка» 5.2</h1><p>Исправления применяются к существующему сайту с сохранением записей. Перед работой сохраните обычную копию сайта и базы в панели хостинга.</p><button data-mode="check">Проверить файлы</button><button data-mode="update">Применить / продолжить</button><button data-mode="verify">Проверить результат</button><button data-mode="rollback">Откатить обновление 5.2</button><pre id="log"></pre><a href="/">Открыть сайт</a></main><script>const token="'.$token.'";let busy=false;document.querySelectorAll("[data-mode]").forEach(button=>button.onclick=async()=>{if(busy)return;busy=true;document.querySelectorAll("button").forEach(b=>b.disabled=true);try{let done=false;while(!done){const response=await fetch("?ajax=1",{method:"POST",body:new URLSearchParams({mode:button.dataset.mode,token})});const result=await response.json();document.querySelector("#log").textContent+=result.message+"\\n";if(!result.ok)throw new Error("Операция остановлена.");done=result.done;}}catch(error){document.querySelector("#log").textContent+=error.message+"\\n";}finally{busy=false;document.querySelectorAll("button").forEach(b=>b.disabled=false);}});</script></html>';
        exit;
    }
    up_out('Netcat '.$nc_core->get_settings('VersionNumber').'; PHP '.PHP_VERSION.'; обновление 5.2.0; режим '.$mode);
    $lock=fopen($up_folder.'/update.lock','c');
    if (!$lock || !flock($lock,LOCK_EX|LOCK_NB)) { throw new RuntimeException('Другой процесс обновления ещё выполняется.'); }
    if ($mode==='check' || $mode==='update') { foreach ($up_manifest['hashes'] as $source=>$hash) { up_payload($source); } }
    if ($mode==='check') { up_out('Пакет проверен. Изменения ещё не применялись.'); }
    elseif ($mode==='update') {
        if (!empty($up_state['rolled_back'])) {
            $archive=$up_folder.'/previous-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(3));
            if (!mkdir($archive,0700)) { throw new RuntimeException('Не удалось сохранить предыдущую копию обновления.'); }
            foreach (['state.php','database.php','files'] as $name) {
                if (file_exists($up_folder.'/'.$name) && !rename($up_folder.'/'.$name,$archive.'/'.$name)) { throw new RuntimeException('Не удалось сохранить предыдущий откат.'); }
            }
            $up_state=[];up_save();
        }
        if (!empty($up_state['complete'])) { up_verify(false);up_out('Обновление 5.2 уже применено. Повторные записи не создавались.'); }
        else { up_apply(); }
    } elseif ($mode==='verify') { up_verify(); }
    else { up_rollback(); }
} catch (Throwable $error) {
    $up_ok=false;
    if (!$up_cli && http_response_code()<400) { http_response_code(500); }
    up_out('ОШИБКА: '.$error->getMessage());
    exit(1);
}
