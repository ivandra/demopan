<?php

$cli = PHP_SAPI === "cli";
$li_ajax = !$cli && isset($_GET["ajax"]) && $_GET["ajax"] === "1";
$li_ajax_done = true;
$li_ajax_ok = true;
$li_request_started = microtime(true);
$li_work_units = 0;
if ($li_ajax) {
    ob_start();
    $li_buffer_level = ob_get_level();
    register_shutdown_function(function () {
        global $li_buffer_level, $li_ajax_done, $li_ajax_ok, $state;
        $body = "";
        while (ob_get_level() >= $li_buffer_level) {
            $body = ob_get_clean() . $body;
        }
        $err = error_get_last();
        $fatal =
            $err && in_array($err["type"], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true);
        if ($fatal) {
            $li_ajax_ok = false;
            $body .= " Ошибка PHP: " . $err["message"];
        }
        header("Content-Type: application/json; charset=UTF-8");
        header("Cache-Control: no-store");
        $status = $li_ajax_ok ? 200 : (http_response_code() >= 400 ? http_response_code() : 500);
        if (function_exists("nc_set_http_response_code")) {
            nc_set_http_response_code($status);
        } else {
            http_response_code($status);
        }
        echo json_encode(
            [
                "ok" => $li_ajax_ok,
                "done" => $li_ajax_done,
                "message" => trim(html_entity_decode(strip_tags($body), ENT_QUOTES, "UTF-8")),
                "installed" => !empty($state["installed"]),
                "active" => !empty($state["active"]),
                "components" => count($state["component_ready"] ?? []),
                "pages" => count($state["pages"] ?? []),
                "records" => count($state["seeded"] ?? []),
            ],
            JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE,
        );
    });
}
$allowed = ["check", "status", "install", "activate", "rollback", "reset", "verify"];
$mode = "status";
if ($cli) {
    foreach (array_slice($argv, 1) as $arg) {
        if (in_array($arg, $allowed, true)) {
            $mode = $arg;
        } elseif (strpos($arg, "--host=") === 0) {
            $_SERVER["HTTP_HOST"] = substr($arg, 7);
        } else {
            fwrite(STDERR, "Неизвестный аргумент\n");
            $li_ajax_ok = false;
            exit(2);
        }
    }
    $_SERVER["DOCUMENT_ROOT"] = __DIR__;
    putenv("DOCUMENT_ROOT=" . __DIR__);
    $_SERVER["HTTP_HOST"] = $_SERVER["HTTP_HOST"] ?? "dev.lifepodrostka.ru";
    $_SERVER["SERVER_NAME"] = $_SERVER["HTTP_HOST"];
    $_SERVER["REQUEST_URI"] = "/";
    $_SERVER["REQUEST_METHOD"] = "GET";
    $_SERVER["REMOTE_ADDR"] = "127.0.0.1";
    $_SERVER["HTTP_USER_AGENT"] = "LP installer";
} else {
    header("Content-Type: text/html; charset=UTF-8");
    header("Cache-Control: no-store");
    header("X-Robots-Tag: noindex,nofollow");
    $mode = (string) ($_POST["mode"] ?? ($_GET["mode"] ?? "status"));
}
if (function_exists("set_time_limit")) {
    @set_time_limit($cli ? 0 : 20);
}
function li_fail($s)
{
    throw new RuntimeException($s);
}
function li_checkpoint($label)
{
    global $li_ajax, $li_request_started, $li_work_units, $li_ajax_done;
    if (!$li_ajax) {
        return;
    }
    $li_work_units++;
    if ($li_work_units >= 8 || microtime(true) - $li_request_started >= 3) {
        $li_ajax_done = false;
        li_out($label . " Состояние сохранено, следующий шаг…");
        exit();
    }
}
function li_out($s)
{
    echo PHP_SAPI === "cli"
        ? $s . "\n"
        : "<p>" . htmlspecialchars($s, ENT_QUOTES, "UTF-8") . "</p>";
}
if (!ini_get("short_open_tag")) {
    li_out("Для ядра Netcat требуется short_open_tag=On.");
    $li_ajax_ok = false;
    exit(2);
}
if (!is_file(__DIR__ . "/vars.inc.php")) {
    li_out("Положите zhp-install.php в корень сайта рядом с vars.inc.php.");
    $li_ajax_ok = false;
    exit(2);
}
require_once __DIR__ . "/vars.inc.php";
require_once $ROOT_FOLDER . "connect_io.php";
$nc_core = nc_Core::get_object();
$nc_core->modules->load_env();
require_once $nc_core->ADMIN_FOLDER . "lang/" . $nc_core->lang->detect_lang() . ".php";
$db = $nc_core->db;
$nc_core->user->attempt_to_authorize();
if (!$cli && !$nc_core->InsideAdminAccess()) {
    $li_ajax_ok = false;
    nc_set_http_response_code(403);
    li_out("Войдите в админку Netcat как администратор, затем обновите эту страницу.");
    if (!$li_ajax) {
        echo '<p><a href="/netcat/admin/" target="_blank" rel="noopener">Открыть админку Netcat</a></p>';
    }
    exit();
}
if (!in_array($mode, $allowed, true)) {
    $li_ajax_ok = false;
    li_out("Неизвестный режим");
    $li_ajax_ok = false;
    exit(2);
}
if (!$cli) {
    if (empty($_SESSION["zhp_install_csrf"])) {
        $_SESSION["zhp_install_csrf"] = bin2hex(random_bytes(32));
    }
    if (
        !in_array($mode, ["check", "status", "verify"], true) &&
        ($_SERVER["REQUEST_METHOD"] !== "POST" ||
            !hash_equals($_SESSION["zhp_install_csrf"], (string) ($_POST["token"] ?? "")))
    ) {
        $li_ajax_ok = false;
        nc_set_http_response_code(403);
        li_out("Изменения доступны только кнопками формы установщика.");
        exit();
    }
}
$design = __DIR__ . "/netcat_template/template/life_teen";
$state_dir = rtrim($nc_core->FILES_FOLDER, "/") . "/life_teen_v5";
$state_file = $state_dir . "/state.php";
$backup_file = $state_dir . "/backup.php";
$state = is_file($state_file) ? include $state_file : [];
$state = is_array($state) ? $state : [];
if (!$cli && !$li_ajax && !in_array($mode, ["status", "check", "verify"], true)) {
    $mode = "status";
}
if (!$cli && !$li_ajax) {
    li_dashboard();
}
function li_dashboard()
{
    global $state;
    $token = htmlspecialchars($_SESSION["zhp_install_csrf"], ENT_QUOTES, "UTF-8");
    $status = !empty($state["active"])
        ? "Новый сайт включён"
        : (!empty($state["installed"])
            ? "Установка завершена, сайт ещё не включён"
            : "Можно начинать установку");
    echo '<!doctype html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Установка — Жизнь подростка</title><style>body{font:16px/1.5 system-ui,sans-serif;margin:0;background:#f6f4fb;color:#242034}main{max-width:900px;margin:40px auto;padding:28px;background:white;border-radius:20px;box-shadow:0 8px 30px #2a174b12}h1{margin-top:0;font-size:30px}button,a{font:inherit}button{border:0;border-radius:9px;padding:12px 16px;margin:5px 4px 5px 0;background:#7858dc;color:white;cursor:pointer}button.secondary{background:#ece7f8;color:#342355}button:disabled{opacity:.45;cursor:wait}#log{white-space:pre-wrap;overflow-wrap:anywhere;background:#f7f5fb;padding:16px;border-radius:12px;max-height:340px;overflow:auto;font-size:14px}progress{width:100%;height:22px}.notice{padding:12px 16px;background:#fff5d8;border-radius:10px}a{color:#6544c6}.result{padding-top:14px}@media(max-width:650px){main{margin:10px;padding:18px}button{width:100%;margin:5px 0}}</style></head><body><main><h1>Жизнь подростка</h1><p>Установка через браузер. Консоль не требуется.</p><p class="notice">Перед установкой сохраните резервную копию файлов и базы данных через панель хостинга.</p><p id="status" role="status">' .
        htmlspecialchars($status, ENT_QUOTES, "UTF-8") .
        '</p><div><button data-mode="reset" class="secondary">Восстановить исходный сайт</button><button data-mode="check" class="secondary">1. Проверить файлы</button><button data-mode="install">2. Установить / продолжить</button><button data-mode="activate">3. Включить и очистить прежний сайт</button><button data-mode="verify" class="secondary">Проверить установку</button><button data-mode="rollback" class="secondary">Откатить изменения</button></div><progress id="progress" hidden></progress><p id="counts"></p><pre id="log" aria-live="polite"></pre><div class="result"><a href="/" target="_blank" rel="noopener">Открыть сайт ↗</a> · <a href="/netcat/admin/" target="_blank" rel="noopener">Админка Netcat ↗</a><p>Когда закончите, удалите <b>zhp-install.php</b> через FileZilla. Папку netcat_template оставьте. Старые lp-install.php и lp-request.php отключены новым установщиком.</p></div><script id="zhp-installer-config" type="application/json">' .
        json_encode(
            ["token" => html_entity_decode($token, ENT_QUOTES, "UTF-8")],
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT,
        ) .
        '</script><script>
(function(){
 const config=JSON.parse(document.getElementById("zhp-installer-config").textContent), buttons=[...document.querySelectorAll("[data-mode]")], log=document.getElementById("log"), status=document.getElementById("status"), progress=document.getElementById("progress");let busy=false;
 function append(text){if(!text)return;log.textContent+=(log.textContent?"\n":"")+text;log.scrollTop=log.scrollHeight;}
 async function run(mode){if(busy)return;busy=true;buttons.forEach(b=>b.disabled=true);progress.hidden=false;progress.removeAttribute("value");status.textContent=mode==="install"?"Устанавливаем. Страница выполняет короткие шаги автоматически…":"Выполняем операцию…";
 try{let done=false;while(!done){const url=new URL(location.href);url.search="?ajax=1";const response=await fetch(url.href,{method:"POST",credentials:"same-origin",cache:"no-store",body:new URLSearchParams({mode,token:config.token})});let result;try{result=await response.json();}catch(e){throw new Error("Сервер прервал ответ. Дождитесь завершения запроса и нажмите «Установить / продолжить». Если ошибка повторяется — проверьте журнал ошибок в панели хостинга.");}if(!response.ok||!result.ok)throw new Error(result.message||"Не удалось выполнить операцию.");append(result.message);document.getElementById("counts").textContent="Готово компонентов: "+result.components+" · Разделов: "+result.pages+" · Записей: "+result.records;done=result.done;if(!done)await new Promise(resolve=>setTimeout(resolve,100));}status.textContent=mode==="install"?"Установка завершена. Теперь нажмите «Включить новый сайт».":mode==="activate"?"Новый сайт включён. Можно открыть сайт и проверить страницы.":(mode==="rollback"||mode==="reset")?"Исходная версия восстановлена.":"Проверка завершена.";
 }catch(e){status.textContent="Операция остановлена";append(e.message);}finally{busy=false;buttons.forEach(b=>b.disabled=false);progress.hidden=true;}}
 buttons.forEach(b=>b.addEventListener("click",()=>run(b.dataset.mode)));
})();
</script></main></body></html>';
}
function li_query($sql)
{
    global $db;
    $r = $db->query($sql);
    if ($r === false || $db->last_error) {
        li_fail("Ошибка БД: " . $db->last_error);
    }
    return $r;
}
function li_save()
{
    global $state, $state_file;
    li_write_php($state_file, $state);
}
function li_write_php($path, $data)
{
    $tmp = $path . ".tmp-" . bin2hex(random_bytes(4));
    if (
        file_put_contents($tmp, "<?php\nreturn " . var_export($data, true) . ";\n", LOCK_EX) ===
            false ||
        !rename($tmp, $path)
    ) {
        li_fail("Не удалось сохранить служебное состояние.");
    }
    @chmod($path, 0600);
    if (function_exists("opcache_invalidate")) {
        opcache_invalidate($path, true);
    }
}
function li_update($table, $idfield, $id, array $data)
{
    global $db;
    $sets = [];
    foreach ($data as $k => $v) {
        $sets[] = "`" . $k . "`=" . ($v === null ? "NULL" : "'" . $db->escape((string) $v) . "'");
    }
    li_query(
        "UPDATE `" .
            $table .
            "` SET " .
            implode(",", $sets) .
            " WHERE `" .
            $idfield .
            "`=" .
            (int) $id,
    );
}

function li_clear_cache()
{
    global $nc_core, $state;
    $nc_core->event->execute(nc_event::AFTER_SITE_UPDATED, (int) $state["site_id"]);
    foreach ($state["pages"] ?? [] as $id) {
        $nc_core->event->execute(
            nc_event::AFTER_SUBDIVISION_UPDATED,
            (int) $state["site_id"],
            (int) $id,
        );
    }
}
function li_component($key, $def)
{
    global $db, $nc_core, $state, $manifest, $design;
    if (!empty($def["existing"])) {
        return li_existing_regions($key, $def);
    }
    $id = (int) ($state["classes"][$key] ?? 0);
    if ($id && !empty($state["component_ready"][$key])) {
        return $id;
    }
    $parent = $def["parent"] ? (int) $state["classes"][$def["parent"]] : null;
    if (!$id) {
        if (
            $db->get_var(
                "SELECT Class_ID FROM Class WHERE Keyword='" .
                    $db->escape($key) .
                    "' AND ClassTemplate=" .
                    (int) $parent,
            )
        ) {
            li_fail("Ключ компонента уже занят: " . $key);
        }
        $custom = $def["settings"]
            ? '$settings_array=' . var_export($def["settings"], true) . ";"
            : "";
        $id = (int) $nc_core->component->create(
            [
                "Class_Name" => $def["name"],
                "Keyword" => $key,
                "Class_Group" => "Жизнь подростка",
                "DisableBlockMarkup" => 1,
                "TitleTemplate" => $def["title_template"] ?? '$f_' . $def["title_field"],
                "FullPageMode" => in_array($key, ["life_specialists", "life_articles"], true)
                    ? "template"
                    : "none",
                "RecordsPerPage" => 100,
                "CustomSettingsTemplate" => $custom,
            ],
            null,
            $parent,
        );
        if (!$id) {
            li_fail("Не создан компонент " . $key);
        }
        $state["classes"][$key] = $id;
        li_save();
        li_checkpoint("Создан компонент: " . $def["name"]);
    }

    li_update("Class", "Class_ID", $id, [
        "CustomSettingsTemplate" => $def["settings"]
            ? '$settings_array=' . var_export($def["settings"], true) . ";"
            : "",
    ]);
    $existing = (array) $db->get_col("SELECT Field_Name FROM Field WHERE Class_ID=" . $id);
    foreach ($def["fields"] as $f) {
        if (in_array($f["name"], $existing, true)) {
            continue;
        }
        $types = [
            "string" => NC_FIELDTYPE_STRING,
            "text" => NC_FIELDTYPE_TEXT,
            "html" => NC_FIELDTYPE_TEXT,
            "file" => NC_FIELDTYPE_FILE,
            "datetime" => NC_FIELDTYPE_DATETIME,
            "bool" => NC_FIELDTYPE_BOOLEAN,
            "int" => NC_FIELDTYPE_INT,
            "multifile" => NC_FIELDTYPE_MULTIFILE,
        ];
        $format =
            $f["type"] === "html"
                ? "12:70;html:1;br:2;fck:1;panel:0;typo:0;bbcode:0;"
                : ($f["type"] === "text"
                    ? "4:70;html:2;br:2;fck:2;panel:0;typo:0;bbcode:0;"
                    : ($f["type"] === "file"
                        ? "fs1:inline;use_resize:0;use_preview:0;"
                        : ""));
        $nc_core->component->add_field($id, [
            "Field_Name" => $f["name"],
            "Description" => $f["label"],
            "TypeOfData_ID" => $types[$f["type"]],
            "Format" => $format,
            "NotNull" => $f["required"] ? 1 : 0,
            "DefaultState" => "",
            "DoSearch" => 0,
        ]);
        $state["added_fields"][$key][] = $f["name"];
        li_save();
        li_checkpoint("Добавлено поле: " . $f["label"]);
    }
    $desired = array_column($def["fields"], "name");
    if (!$parent) {
        $names = $desired
            ? implode(
                ",",
                array_map(function ($name) use ($db) {
                    return "'" . $db->escape($name) . "'";
                }, $desired),
            )
            : "''";
        li_query(
            "UPDATE Field SET Checked=IF(Field_Name IN (" . $names . "),1,0) WHERE Class_ID=" . $id,
        );
    }
    li_update("Class", "Class_ID", $id, [
        "Class_Name" => $def["name"],
        "CustomSettingsTemplate" => $def["settings"]
            ? '$settings_array=' . var_export($def["settings"], true) . ";"
            : "",
    ]);
    $path = (string) $db->get_var("SELECT File_Path FROM Class WHERE Class_ID=" . $id);
    $dir = rtrim($nc_core->CLASS_TEMPLATE_FOLDER, "/") . "/" . trim($path, "/");
    $src = $design . "/.install/components/" . $key;
    $master = "";
    foreach (["FormPrefix", "RecordTemplate", "FormSuffix"] as $part) {
        $master .=
            "<!-- " .
            $part .
            " -->" .
            (is_file($src . "/" . $part . ".html")
                ? file_get_contents($src . "/" . $part . ".html")
                : "") .
            "<!-- /" .
            $part .
            " -->" .
            "\n";
    }
    file_put_contents($dir . "/Class.html", $master, LOCK_EX);
    $tpl = new nc_tpl($nc_core->CLASS_TEMPLATE_FOLDER, $db);
    $tpl->load($id, "Class");
    nc_tpl_parser::main2parts(
        $tpl,
        (string) $db->get_var("SELECT File_Hash FROM Class WHERE Class_ID=" . $id),
    );
    foreach (glob($src . "/*.html") as $file) {
        if (
            !in_array(
                basename($file),
                ["FormPrefix.html", "RecordTemplate.html", "FormSuffix.html"],
                true,
            )
        ) {
            file_put_contents($dir . "/" . basename($file), file_get_contents($file), LOCK_EX);
        }
    }
    $state["component_ready"][$key] = true;
    li_save();
    li_checkpoint("Готов компонент: " . $def["name"]);
    return $id;
}
function li_infoblock($key, $sub_id, $title, $priority, $settings = [])
{
    global $db, $nc_core, $state, $manifest;
    $state_key = $sub_id . ":" . $key;
    if (isset($state["blocks"][$state_key])) {
        $id = (int) $state["blocks"][$state_key];
        li_update("Sub_Class", "Sub_Class_ID", $id, ["Priority" => $priority]);
        return $id;
    }
    if ($key === "life_regions") {
        return 57;
    }
    $parent = $manifest["classes"][$key]["parent"];
    $base = $parent ?: $key;
    $private = in_array($key, ["life_regions", "life_leads"], true);
    $id = (int) $nc_core->sub_class->create(
        $state["classes"][$base],
        [
            "Subdivision_ID" => $sub_id,
            "Class_Template_ID" => $parent ? $state["classes"][$key] : 0,
            "Sub_Class_Name" => $title,
            "EnglishName" => $key,
            "Checked" => 0,
            "Priority" => $priority,
            "DefaultAction" => "index",
            "RecordsPerPage" => $key === "life_articles" ? 6 : 100,
            "Read_Access_ID" => $private ? 3 : 1,
            "Write_Access_ID" => $key === "life_leads" ? 1 : 3,
            "Edit_Access_ID" => 3,
            "Delete_Access_ID" => 3,
            "Checked_Access_ID" => 3,
        ],
        $settings,
    );
    if (!$id) {
        li_fail("Не создан инфоблок " . $title);
    }
    $state["blocks"][$state_key] = $id;
    $state["new_blocks"][] = $id;
    $state["block_locations"][$id] = $sub_id;
    $state["block_classes"][$id] = $base;
    li_save();
    li_checkpoint("Создан инфоблок: " . $title);
    return $id;
}
function li_seed($id, $key, $records)
{
    global $nc_core, $db, $state, $manifest;
    li_new_field_values($id, $key, $records);
    $base = $manifest["classes"][$key]["parent"] ?: $key;
    $class = (int) $state["classes"][$base];
    foreach ($records as $i => $r) {
        $sk = $id . ":" . $i;
        if (!empty($state["seeded"][$sk])) {
            continue;
        }
        if ($key === "life_regions" && $i === 0) {
            $r["main_domain"] = $state["domain"];
        }
        $r["Priority"] = $i + 1;
        $r["Checked"] = 1;
        $r["Keyword"] = $r["Keyword"] ?? "zhp-item-" . ($i + 1);
        $existing = (int) $db->get_var(
            "SELECT Message_ID FROM Message" .
                $class .
                " WHERE Sub_Class_ID=" .
                (int) $id .
                " AND Keyword='" .
                $db->escape($r["Keyword"]) .
                "'",
        );
        if ($existing) {
            $state["seeded"][$sk] = $existing;
            li_save();
            continue;
        }
        if (in_array($key, ["life_specialists", "life_articles"], true)) {
            $r["ncTitle"] = ($r["fio"] ?? $r["name"]) . " — Жизнь подростка";
            $r["ncDescription"] = $r["intro"] ?? "";
        }
        $msg = (int) $nc_core->message->create($id, $r);
        if (!$msg) {
            li_fail("Не создана запись " . $key);
        }
        $state["seeded"][$sk] = $msg;
        li_save();
        li_checkpoint("Импортирована запись: " . $key);
    }
}
function li_payload_check($persistent = false)
{
    global $design, $state;
    $hash_file = $design . "/.install/hashes.json";
    if (!is_file($hash_file)) {
        li_fail("Не найден список файлов пакета.");
    }
    $hashes = json_decode(file_get_contents($hash_file), true);
    if (!$hashes) {
        li_fail("Повреждён список файлов пакета.");
    }
    foreach ($hashes as $file => $hash) {
        if ($persistent && !empty($state["checked_files"][$file])) {
            continue;
        }
        if (
            !is_file($design . "/" . $file) ||
            !hash_equals($hash, hash_file("sha256", $design . "/" . $file))
        ) {
            li_fail(
                "Файл отсутствует или изменён при загрузке: " .
                    $file .
                    ". Загрузите его повторно из этого архива.",
            );
        }
        if ($persistent) {
            $state["checked_files"][$file] = true;
            li_save();
            li_checkpoint("Проверен файл " . $file);
        }
    }
    if ($persistent) {
        $state["payload_checked"] = true;
        li_save();
    }
}

function li_existing_regions($key, array $definition)
{
    global $db, $nc_core, $state, $backup_file;
    $component = $db->get_row(
        "SELECT c.Class_ID, c.ClassTemplate, s.Subdivision_ID FROM Sub_Class s JOIN Class c USING(Class_ID) " .
            "WHERE s.Sub_Class_ID=57",
        ARRAY_A,
    );
    if (!$component || (int) $component["Class_ID"] !== 223 || (int) $component["ClassTemplate"]) {
        li_fail("Не найден исходный компонент регионов 223 / инфоблок 57. Изменения остановлены.");
    }
    if ((int) $component["Subdivision_ID"] !== 8) {
        $nc_core->sub_class->move(57, ["Subdivision_ID" => 8, "Priority" => false], false);
        $backup = include $backup_file;
        foreach ($backup["blocks"] as $block) {
            if ((int) $block["Sub_Class_ID"] === 57) {
                li_update("Sub_Class", "Sub_Class_ID", 57, $block);
            }
        }
    }
    $state["classes"][$key] = 223;
    $existing = (array) $db->get_col("SELECT Field_Name FROM Field WHERE Class_ID=223");
    foreach ($definition["fields"] as $field) {
        if (in_array($field["name"], $existing, true)) {
            continue;
        }
        $types = [
            "string" => NC_FIELDTYPE_STRING,
            "html" => NC_FIELDTYPE_TEXT,
            "bool" => NC_FIELDTYPE_BOOLEAN,
        ];
        $nc_core->component->add_field(223, [
            "Field_Name" => $field["name"],
            "Description" => $field["label"],
            "TypeOfData_ID" => $types[$field["type"]],
            "NotNull" => 0,
            "Format" =>
                $field["type"] === "html" ? "12:70;html:1;br:2;fck:1;panel:0;typo:0;bbcode:0;" : "",
            "DefaultState" => "",
            "DoSearch" => 0,
        ]);
        $state["added_region_fields"][] = $field["name"];
        li_save();
        li_checkpoint("Добавлено поле в существующие регионы: " . $field["label"]);
    }
    $state["component_ready"][$key] = true;
    $state["collections"]["regions"] = ["class_id" => 223, "cc_id" => 57, "sub_id" => 8];
    li_save();
    return 223;
}

function li_layouts()
{
    global $db, $nc_core, $state, $manifest, $design;
    if (!empty($state["layouts_ready"])) {
        return;
    }
    $definitions = ["root" => "Жизнь подростка — общий макет"] + $manifest["layouts"];
    foreach ($definitions as $key => $title) {
        if (!empty($state["layout_written"][$key])) {
            continue;
        }
        $is_root = $key === "root";
        $path = "/life_teen/" . ($is_root ? "" : $key . "/");
        $id = $is_root
            ? (int) ($state["template_id"] ?? 0)
            : (int) ($state["templates"][$key] ?? 0);
        if (!$id) {
            $id = (int) nc_db_table::make("Template")->insert([
                "Description" => $title,
                "Keyword" => $is_root ? "zhizn_podrostka" : $key,
                "Parent_Template_ID" => $is_root ? 0 : $state["template_id"],
                "File_Mode" => 1,
                "File_Path" => $path,
                "File_Hash" => "",
                "Header" => "",
                "Footer" => "",
                "Settings" => "",
                "CustomSettings" => "",
                "Priority" => 10,
            ]);
            if (!$id) {
                li_fail("Не создан макет " . $title);
            }
            if ($is_root) {
                $state["template_id"] = $id;
            } else {
                $state["templates"][$key] = $id;
            }
            li_save();
        }
        $folder = $design . ($is_root ? "" : "/" . $key);
        $parts = [];
        foreach (["Settings", "Header", "Footer"] as $part) {
            $parts[$part] = file_get_contents($folder . "/" . $part . ".html");
        }
        $custom = $is_root
            ? '$settings_array=' . var_export($manifest["template_settings"], true) . ";"
            : "";
        li_update("Template", "Template_ID", $id, [
            "Description" => $title,
            "Parent_Template_ID" => $is_root ? 0 : $state["template_id"],
            "CustomSettings" => $custom,
        ]);
        $editor = new nc_tpl_template_editor($nc_core->TEMPLATE_FOLDER, $db);
        $editor->load_template($id, $path);
        $editor->save_new_template($parts, false);
        $state["layout_written"][$key] = true;
        li_save();
        li_checkpoint("Готов макет: " . $title);
    }
    $state["layouts_ready"] = true;
    li_save();
}

function li_new_field_values($infoblock_id, $key, array $records)
{
    global $db, $nc_core, $state, $manifest;
    $base = $manifest["classes"][$key]["parent"] ?: $key;
    $fields = $state["added_fields"][$base] ?? [];
    if (!$fields) {
        return;
    }
    $table = "Message" . (int) $state["classes"][$base];
    foreach ($records as $index => $record) {
        $id = (int) ($state["seeded"][$infoblock_id . ":" . $index] ?? 0);
        if (!$id) {
            continue;
        }
        foreach ($fields as $name) {
            if (!array_key_exists($name, $record)) {
                continue;
            }
            $type = null;
            foreach ($manifest["classes"][$base]["fields"] as $definition) {
                if ($definition["name"] === $name) {
                    $type = $definition["type"];
                    break;
                }
            }
            if ($type === "file") {
                $current = $db->get_row(
                    "SELECT `" .
                        $name .
                        "`,LastUpdated FROM `" .
                        $table .
                        "` WHERE Message_ID=" .
                        $id,
                    ARRAY_A,
                );
                if ($current[$name] !== null && $current[$name] !== "") {
                    continue;
                }
                if ($record[$name] === "") {
                    continue;
                }

                $saved = $nc_core->files->field_save_file(
                    (int) $state["classes"][$base],
                    $name,
                    $id,
                    $record[$name],
                );
                if (!$saved) {
                    li_fail("Не удалось заполнить файловое поле " . $name);
                }
                li_query(
                    "UPDATE `" .
                        $table .
                        "` SET LastUpdated='" .
                        $db->escape($current["LastUpdated"]) .
                        "' WHERE Message_ID=" .
                        $id,
                );
                continue;
            }
            li_query(
                "UPDATE `" .
                    $table .
                    "` SET `" .
                    $name .
                    "`='" .
                    $db->escape((string) $record[$name]) .
                    "',LastUpdated=LastUpdated " .
                    "WHERE Message_ID=" .
                    $id .
                    " AND Sub_Class_ID=" .
                    (int) $infoblock_id .
                    " AND (`" .
                    $name .
                    "` IS NULL OR `" .
                    $name .
                    "`='')",
            );
        }
    }
}

function li_snapshot($site_id)
{
    global $state, $state_dir, $db, $nc_core;
    if (!empty($state["snapshot_complete"])) {
        return;
    }
    $folder = $state_dir . "/snapshot";
    if (!is_dir($folder . "/db")) {
        mkdir($folder . "/db", 0700, true);
    }
    file_put_contents($state_dir . "/.htaccess", "Require all denied\nDeny from all\n");
    if (empty($state["snapshot_tables"])) {
        $state["snapshot_tables"] = (array) $db->get_col("SHOW TABLES");
        $state["snapshot_table_index"] = 0;
        $state["snapshot_row_offset"] = 0;
        $paths = [];
        foreach (
            [
                $nc_core->CLASS_TEMPLATE_FOLDER,
                $nc_core->TEMPLATE_FOLDER,
                $nc_core->FILES_FOLDER,
                __DIR__ . "/netcat_template/widget/regions",
            ]
            as $base
        ) {
            if (!is_dir($base)) {
                continue;
            }
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
            );
            foreach ($it as $file) {
                $path = $file->getPathname();
                if (
                    !$file->isFile() ||
                    strpos($path, $state_dir . "/") === 0 ||
                    strpos($path, __DIR__ . "/netcat_template/template/life_teen/") === 0
                ) {
                    continue;
                }
                $paths[substr($path, strlen(__DIR__) + 1)] = true;
            }
        }
        $paths["netcat/modules/default/function.inc.php"] = true;
        foreach (["lp-install.php", "lp-request.php"] as $name) {
            if (is_file(__DIR__ . "/" . $name)) {
                $paths[$name] = true;
            }
        }
        $state["snapshot_files"] = array_keys($paths);
        $state["snapshot_file_index"] = 0;
        li_save();
    }
    while ($state["snapshot_table_index"] < count($state["snapshot_tables"])) {
        $table = $state["snapshot_tables"][$state["snapshot_table_index"]];
        $safe = str_replace("`", "``", $table);
        $offset = (int) $state["snapshot_row_offset"];
        $rows = (array) $db->get_results(
            "SELECT * FROM `" . $safe . "` LIMIT " . $offset . ",250",
            ARRAY_A,
        );
        if (!$offset) {
            $create = $db->get_row("SHOW CREATE TABLE `" . $safe . "`", ARRAY_N);
            li_write_php($folder . "/db/" . rawurlencode($table) . "-schema.php", [
                "sql" => $create[1],
            ]);
        }
        if ($rows) {
            li_write_php($folder . "/db/" . rawurlencode($table) . "-" . $offset . ".php", $rows);
            $state["snapshot_row_offset"] += count($rows);
        } else {
            $state["snapshot_table_index"]++;
            $state["snapshot_row_offset"] = 0;
        }
        li_save();
        li_checkpoint("Сохранена база: " . $table);
    }
    while ($state["snapshot_file_index"] < count($state["snapshot_files"])) {
        $relative = $state["snapshot_files"][$state["snapshot_file_index"]];
        $target = $folder . "/files/" . $relative;
        if (!is_dir(dirname($target))) {
            mkdir(dirname($target), 0700, true);
        }
        if (
            !copy(__DIR__ . "/" . $relative, $target) ||
            !hash_equals(
                hash_file("sha256", __DIR__ . "/" . $relative),
                hash_file("sha256", $target),
            )
        ) {
            li_fail("Не удалось проверить резервную копию файла " . $relative);
        }
        @chmod($target, 0600);
        $state["snapshot_file_index"]++;
        li_save();
        li_checkpoint("Сохранены файлы: " . $state["snapshot_file_index"]);
    }
    li_write_php($folder . "/manifest.php", [
        "tables" => $state["snapshot_tables"],
        "files" => $state["snapshot_files"],
        "created" => date("c"),
    ]);
    $state["snapshot_complete"] = true;
    li_save();
}
function li_restore()
{
    global $state, $state_dir, $db;
    $folder = $state_dir . "/snapshot";
    if (empty($state["snapshot_complete"]) || !is_file($folder . "/manifest.php")) {
        li_fail("Полная резервная копия ещё не завершена.");
    }
    $backup = include $folder . "/manifest.php";
    if (!isset($state["restore_table_index"])) {
        $state["restore_table_index"] = 0;
        $state["restore_row_offset"] = 0;
        $state["restore_file_index"] = 0;
        $state["restore_admin_id"] = (int) ($GLOBALS["AUTH_USER_ID"] ?? 0);
        $state["restore_class_dirs"] = [];
        foreach ($state["classes"] ?? [] as $id) {
            if ((int) $id === 223) {
                continue;
            }
            $path = $db->get_var("SELECT File_Path FROM Class WHERE Class_ID=" . (int) $id);
            if ($path) {
                $state["restore_class_dirs"][] =
                    nc_core()->CLASS_TEMPLATE_FOLDER . trim($path, "/");
            }
        }
        $extra = array_diff((array) $db->get_col("SHOW TABLES"), $backup["tables"]);
        foreach ($extra as $table) {
            if (preg_match('/^Message[0-9]+$/', $table)) {
                li_query("DROP TABLE `" . $table . "`");
            }
        }
        li_save();
    }
    li_query("SET FOREIGN_KEY_CHECKS=0");
    while ($state["restore_table_index"] < count($backup["tables"])) {
        $table = $backup["tables"][$state["restore_table_index"]];
        $safe = str_replace("`", "``", $table);
        $offset = (int) $state["restore_row_offset"];
        if (!$offset) {
            $schema = include $folder . "/db/" . rawurlencode($table) . "-schema.php";
            li_query("DROP TABLE IF EXISTS `" . $safe . "`");
            li_query($schema["sql"]);
        }
        $file = $folder . "/db/" . rawurlencode($table) . "-" . $offset . ".php";
        if (is_file($file)) {
            $rows = include $file;
            foreach ($rows as $row) {
                $columns = [];
                $values = [];
                foreach ($row as $name => $value) {
                    $columns[] = "`" . str_replace("`", "``", $name) . "`";
                    $values[] = $value === null ? "NULL" : "X'" . bin2hex((string) $value) . "'";
                }
                li_query(
                    "INSERT INTO `" .
                        $safe .
                        "` (" .
                        implode(",", $columns) .
                        ") VALUES (" .
                        implode(",", $values) .
                        ")",
                );
            }
            $state["restore_row_offset"] += count($rows);
        } else {
            $state["restore_table_index"]++;
            $state["restore_row_offset"] = 0;
        }
        if (in_array($table, ["Session", "User"], true) && !empty($state["restore_admin_id"])) {
            nc_core()->user->authorize_by_id((int) $state["restore_admin_id"]);
        }
        li_save();
        li_checkpoint("Восстановлена база: " . $table);
    }
    li_query("SET FOREIGN_KEY_CHECKS=1");
    foreach ($state["restore_class_dirs"] ?? [] as $directory) {
        if (!is_dir($directory)) {
            continue;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($directory);
    }
    $state["restore_class_dirs"] = [];
    li_save();
    while ($state["restore_file_index"] < count($backup["files"])) {
        $relative = $backup["files"][$state["restore_file_index"]];
        $target = __DIR__ . "/" . $relative;
        if (!is_dir(dirname($target))) {
            mkdir(dirname($target), 0755, true);
        }
        if (!copy($folder . "/files/" . $relative, $target)) {
            li_fail("Не восстановлен файл " . $relative);
        }
        @chmod($target, 0644);
        if (function_exists("opcache_invalidate")) {
            opcache_invalidate($target, true);
        }
        $state["restore_file_index"]++;
        li_save();
        li_checkpoint("Восстановлены файлы: " . $state["restore_file_index"]);
    }
    $state = [
        "version" => 5,
        "site_id" => 1,
        "snapshot_complete" => true,
        "snapshot_tables" => $backup["tables"],
        "snapshot_files" => $backup["files"],
        "active" => false,
        "restored" => true,
    ];
    li_save();
}
function li_cleanup()
{
    global $state, $db, $nc_core;
    if (empty($state["snapshot_complete"])) {
        li_fail("Очистка без полной резервной копии запрещена.");
    }
    if (!empty($state["cleaned"])) {
        return;
    }
    require_once $nc_core->ADMIN_FOLDER . "subdivision.inc.php";
    require_once $nc_core->ADMIN_FOLDER . "class/function.inc.php";
    $keep = array_merge([57], array_map("intval", $state["new_blocks"]));
    $blocks = (array) $db->get_col(
        "SELECT Sub_Class_ID FROM Sub_Class WHERE Catalogue_ID=" .
            (int) $state["site_id"] .
            " AND Sub_Class_ID NOT IN (" .
            implode(",", $keep) .
            ") ORDER BY Parent_Sub_Class_ID DESC,Sub_Class_ID DESC",
    );
    foreach ($blocks as $id) {
        if ($db->get_var("SELECT Sub_Class_ID FROM Sub_Class WHERE Sub_Class_ID=" . (int) $id)) {
            $nc_core->sub_class->delete((int) $id);
        }
        li_checkpoint("Удалён прежний инфоблок #" . $id);
    }
    $keep = array_merge([8], array_map("intval", array_values($state["pages"])));
    li_update("Subdivision", "Subdivision_ID", 8, ["Parent_Sub_ID" => 0]);
    $sections = (array) $db->get_col(
        "SELECT Subdivision_ID FROM Subdivision WHERE Catalogue_ID=" .
            (int) $state["site_id"] .
            " AND Subdivision_ID NOT IN (" .
            implode(",", $keep) .
            ") ORDER BY Subdivision_ID DESC",
    );
    foreach ($sections as $id) {
        CascadeDeleteSubdivision((int) $id);
        li_checkpoint("Удалён прежний раздел #" . $id);
    }
    $classes = (array) $db->get_col(
        "SELECT Class_ID FROM Class WHERE Class_ID<>223 AND ClassTemplate=0 AND (Class_Group IN ('medelta','medelta о нас','Архив неудачного переноса','Архив нового переноса') OR Keyword LIKE 'zhp\\_%' OR Keyword LIKE 'lp\\_%')",
    );
    foreach ($classes as $id) {
        if ($db->get_var("SELECT Sub_Class_ID FROM Sub_Class WHERE Class_ID=" . (int) $id)) {
            li_fail("Прежний компонент используется другим сайтом: #" . $id);
        }
        CascadeDeleteClass((int) $id);
        li_checkpoint("Удалён прежний компонент #" . $id);
    }
    $templates = (array) $db->get_results(
        "SELECT Template_ID,File_Path FROM Template WHERE File_Path LIKE '/medelta/%' OR File_Path LIKE '/lifepodrostka/%' OR File_Path LIKE '/zhizn_podrostka/%' ORDER BY LENGTH(File_Path) DESC",
        ARRAY_A,
    );
    foreach ($templates as $row) {
        $id = (int) $row["Template_ID"];
        if (
            $db->get_var("SELECT Catalogue_ID FROM Catalogue WHERE Template_ID=" . $id) ||
            $db->get_var("SELECT Subdivision_ID FROM Subdivision WHERE Template_ID=" . $id)
        ) {
            li_fail("Прежний макет ещё используется: #" . $id);
        }
        $editor = new nc_tpl_template_editor($nc_core->TEMPLATE_FOLDER, $db);
        $editor->load_template($id);
        $editor->delete_template();
        li_query("DELETE FROM Template WHERE Template_ID=" . $id);
        $nc_core->event->execute(nc_Event::AFTER_TEMPLATE_DELETED, $id);
        li_checkpoint("Удалён прежний макет #" . $id);
    }
    foreach (["zhizn_podrostka", "lifepodrostka"] as $name) {
        $directory = rtrim($nc_core->FILES_FOLDER, "/") . "/" . $name;
        if (!is_dir($directory)) {
            continue;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($directory);
    }
    foreach (["lp-install.php", "lp-request.php"] as $name) {
        if (is_file(__DIR__ . "/" . $name)) {
            unlink(__DIR__ . "/" . $name);
        }
    }
    li_update("Class", "Class_ID", 223, ["Class_Group" => "Регионы"]);
    $state["cleaned"] = true;
    li_save();
}
function li_verify_clean()
{
    global $state, $db, $nc_core;
    if (empty($state["active"]) || empty($state["cleaned"])) {
        li_fail("Чистая установка ещё не завершена.");
    }
    $keep = array_merge([8], array_map("intval", array_values($state["pages"])));
    if (
        $db->get_var(
            "SELECT COUNT(*) FROM Subdivision WHERE Catalogue_ID=" .
                (int) $state["site_id"] .
                " AND Subdivision_ID NOT IN (" .
                implode(",", $keep) .
                ")",
        )
    ) {
        li_fail("Остались прежние разделы.");
    }
    if (
        $db->get_var(
            "SELECT COUNT(*) FROM Template WHERE File_Path LIKE '/medelta/%' OR File_Path LIKE '/lifepodrostka/%' OR File_Path LIKE '/zhizn_podrostka/%'",
        )
    ) {
        li_fail("Остались прежние макеты.");
    }
    if (
        $db->get_var(
            "SELECT COUNT(*) FROM Class WHERE Keyword LIKE 'zhp\\_%' OR Keyword LIKE 'lp\\_%'",
        )
    ) {
        li_fail("Остались компоненты прежнего переноса.");
    }
    li_out(
        "В дереве сайта готовые страницы, запрошенные пустые черновики и существующий справочник регионов. Прежние макеты и компоненты удалены.",
    );
}

try {
    $manifest_file = $design . "/.install/manifest.json";
    if (!is_file($manifest_file)) {
        li_fail("Не найдены файлы пакета v5.");
    }
    $manifest = json_decode(file_get_contents($manifest_file), true);
    if (!$manifest || (int) $manifest["version"] !== 5) {
        li_fail("Нужен полный пакет v5.");
    }
    $site_id = 1;
    if ((int) $db->get_var("SELECT COUNT(*) FROM Catalogue") !== 1) {
        li_fail("Этот чистый патч рассчитан на приложенную базу с одним сайтом.");
    }
    $catalogue = $db->get_row("SELECT * FROM Catalogue WHERE Catalogue_ID=1", ARRAY_A);
    if (!$catalogue) {
        li_fail("Исходный сайт №1 не найден.");
    }
    if ((int) $db->get_var("SELECT Class_ID FROM Sub_Class WHERE Sub_Class_ID=57") !== 223) {
        li_fail("Нет исходного компонента регионов №223 / инфоблока №57.");
    }
    if (
        !is_writable($nc_core->CLASS_TEMPLATE_FOLDER) ||
        !is_writable($nc_core->TEMPLATE_FOLDER) ||
        !is_writable($nc_core->FILES_FOLDER)
    ) {
        li_fail("Папки шаблонов и файлов Netcat недоступны для записи.");
    }
    li_out(
        "Netcat " .
            $nc_core->get_settings("VersionNumber") .
            "; PHP " .
            PHP_VERSION .
            "; чистый патч v5",
    );
    if ($mode === "check") {
        li_payload_check(false);
        li_out(
            "Проверены файлы. В пакете " .
                count($manifest["pages"]) .
                " разделов и " .
                (count($manifest["layouts"]) + 1) .
                " макетов. Старые разделы, инфоблоки, макеты и компоненты переноса будут удалены после полной резервной копии.",
        );
        exit();
    }
    if ($mode === "status") {
        li_out(!empty($state["active"]) ? "Чистый сайт включён." : "Можно продолжить установку.");
        exit();
    }
    if ($mode === "verify") {
        li_verify_clean();
        exit();
    }
    if (!is_dir($state_dir) && !mkdir($state_dir, 0700, true)) {
        li_fail("Не создана папка резервной копии.");
    }
    $lock = fopen($state_dir . "/install.lock", "c");
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
        li_fail("Другой запрос установщика ещё выполняется.");
    }
    if ($mode === "rollback" || $mode === "reset") {
        li_restore();
        li_out("Восстановлены исходная база и файлы из полной резервной копии.");
        exit();
    }
    $state += [
        "version" => 5,
        "site_id" => 1,
        "domain" => $catalogue["Domain"],
        "active" => false,
        "classes" => [],
        "pages" => [],
        "blocks" => [],
        "new_subdivisions" => [],
        "new_blocks" => [],
        "seeded" => [],
        "collections" => [],
    ];
    li_save();
    if ($mode === "install") {
        if (!empty($state["installed"])) {
            li_out("Патч уже установлен. Повторные записи не создавались.");
            exit();
        }
        li_snapshot(1);
        if (empty($state["payload_checked"])) {
            li_payload_check(true);
        }
        foreach ($manifest["classes"] as $key => $definition) {
            li_component($key, $definition);
        }
        li_layouts();
        $pages = $manifest["pages"];
        usort($pages, function ($a, $b) {
            return substr_count($a["url"], "/") <=> substr_count($b["url"], "/");
        });
        foreach ($pages as $page) {
            $url = $page["url"];
            if (isset($state["pages"][$url])) {
                continue;
            }
            if ($url === "/regions/") {
                $state["pages"][$url] = 8;
                li_save();
                continue;
            }
            $parent_url = preg_replace('~[^/]+/$~', "", $url);
            $parent = $parent_url === "/" ? 0 : (int) ($state["pages"][$parent_url] ?? 0);
            $id = (int) $nc_core->subdivision->create([
                "Catalogue_ID" => 1,
                "Parent_Sub_ID" => $parent,
                "Subdivision_Name" => $page["title"],
                "EnglishName" => "life-draft-" . bin2hex(random_bytes(4)),
                "Checked" => 0,
                "Read_Access_ID" => 3,
                "Write_Access_ID" => 3,
                "Template_ID" => $state["template_id"],
            ]);
            if (!$id) {
                li_fail("Не создан раздел " . $page["title"]);
            }
            $state["pages"][$url] = $id;
            $state["new_subdivisions"][] = $id;
            li_save();
            li_checkpoint("Подготовлен раздел: " . $page["title"]);
        }
        $state["home_id"] = (int) $state["pages"]["/"];
        li_save();
        foreach ($manifest["pages"] as $page) {
            foreach ($page["blocks"] as $index => $block) {
                $id = li_infoblock(
                    $block["class"],
                    $state["pages"][$page["url"]],
                    $block["name"],
                    $index + 1,
                    $block["settings"],
                );
                li_seed($id, $block["class"], $block["records"]);
                foreach ($manifest["collections"] as $name => $collection) {
                    if (
                        $collection["class"] === $block["class"] &&
                        $collection["page"] === $page["url"]
                    ) {
                        $state["collections"][$name] = [
                            "class_id" => $state["classes"][$collection["class"]],
                            "cc_id" => $id,
                            "sub_id" => $state["pages"][$page["url"]],
                        ];
                        li_save();
                    }
                }
            }
        }
        foreach (["regions", "leads"] as $name) {
            $collection = $manifest["collections"][$name];
            $id = li_infoblock(
                $collection["class"],
                $state["pages"][$collection["page"]],
                $collection["name"],
                1,
            );
            $state["collections"][$name] = [
                "class_id" => $state["classes"][$collection["class"]],
                "cc_id" => $id,
                "sub_id" => $state["pages"][$collection["page"]],
            ];
            li_save();
            li_seed($id, $collection["class"], $collection["records"]);
        }
        $table = "Message" . (int) $state["classes"]["life_leads"];
        if (
            !$db->get_var(
                "SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='" .
                    $table .
                    "' AND INDEX_NAME='life_request_key'",
            )
        ) {
            li_query("ALTER TABLE `" . $table . "` ADD UNIQUE KEY life_request_key (request_key)");
        }
        $state["installed"] = true;
        li_save();
        li_out(
            "Полная резервная копия и заполненный сайт подготовлены. Следующий шаг — включить сайт и очистить прежние объекты CMS.",
        );
        exit();
    }
    if ($mode === "activate") {
        if (empty($state["installed"])) {
            li_fail("Сначала завершите установку.");
        }
        if (!empty($state["active"]) && !empty($state["cleaned"])) {
            li_out("Чистый сайт уже включён.");
            exit();
        }
        if (empty($state["switched"])) {
            $keep = array_merge([8], array_map("intval", array_values($state["pages"])));
            $old_sections = (array) $db->get_col(
                "SELECT Subdivision_ID FROM Subdivision WHERE Catalogue_ID=1 AND Subdivision_ID NOT IN (" .
                    implode(",", $keep) .
                    ")",
            );
            foreach ($old_sections as $old_id) {
                li_update("Subdivision", "Subdivision_ID", $old_id, [
                    "EnglishName" => "life-old-" . (int) $old_id,
                    "Hidden_URL" => "/life-old-" . (int) $old_id . "/",
                    "Parent_Sub_ID" => 0,
                ]);
            }
            $nav = [
                "/o-nas/" => 1,
                "/podrostkam/" => 2,
                "/roditelyam/" => 3,
                "/spetsialistam/" => 4,
                "/blog/" => 5,
                "/kontakty/" => 6,
            ];
            foreach ($manifest["pages"] as $index => $page) {
                $url = $page["url"];
                $id = (int) $state["pages"][$url];
                $private =
                    in_array($url, ["/regions/", "/zayavki/"], true) || !empty($page["draft"]);
                $template =
                    $page["layout"] === "root"
                        ? $state["template_id"]
                        : $state["templates"][$page["layout"]];
                $props = [
                    "Subdivision_Name" => $page["title"],
                    "Template_ID" => $template,
                    "Checked" => $page["nav"] ? 1 : 0,
                    "Read_Access_ID" => $private ? 3 : 1,
                    "Write_Access_ID" => $url === "/zayavki/" ? 1 : 3,
                    "Title" => $page["title"] . " — Жизнь подростка",
                    "Keywords" => "",
                    "ncH1" => $page["title"],
                    "ExternalURL" => "",
                    "Priority" => $nav[$url] ?? $index + 10,
                ];
                if ($url === "/") {
                    $props["Hidden_URL"] = "/";
                    $props["EnglishName"] = "home";
                }
                if ($url !== "/") {
                    $props["Hidden_URL"] = $url;
                    $props["EnglishName"] = basename(trim($url, "/"));
                    $parent = preg_replace('~[^/]+/$~', "", $url);
                    $props["Parent_Sub_ID"] =
                        $parent === "/" ? 0 : (int) ($state["pages"][$parent] ?? 0);
                }
                li_update("Subdivision", "Subdivision_ID", $id, $props);
            }
            foreach ($state["new_blocks"] as $id) {
                li_update("Sub_Class", "Sub_Class_ID", $id, [
                    "Checked" => 1,
                    "Read_Access_ID" => $state["block_classes"][$id] === "life_leads" ? 3 : 1,
                ]);
            }
            file_put_contents(
                __DIR__ . "/netcat/modules/default/function.inc.php",
                file_get_contents($design . "/function.inc.php.txt"),
                LOCK_EX,
            );
            file_put_contents(
                __DIR__ . "/netcat_template/widget/regions/Template.html",
                file_get_contents($design . "/regions-widget.html"),
                LOCK_EX,
            );
            if (function_exists("opcache_invalidate")) {
                opcache_invalidate(__DIR__ . "/netcat/modules/default/function.inc.php", true);
                opcache_invalidate(__DIR__ . "/netcat_template/widget/regions/Template.html", true);
            }
            $lead = $state["collections"]["leads"];
            li_update("Catalogue", "Catalogue_ID", 1, [
                "Catalogue_Name" => "Жизнь подростка",
                "Template_ID" => $state["template_id"],
                "Title_Sub_ID" => $state["home_id"],
                "E404_Sub_ID" => $state["pages"]["/404/"],
                "PrivacyPolicy_Sub_ID" => $state["pages"]["/politika-konfidentsialnosti/"],
                "Consent_Sub_ID" => $state["pages"]["/policy/"],
                "zakaz_sub" => $lead["sub_id"],
                "zakaz_cc" => $lead["cc_id"],
                "mail_admin" => "",
                "Checked" => 1,
            ]);
            $state["switched"] = true;
            li_save();
        }
        li_cleanup();
        $state["active"] = true;
        li_save();
        li_clear_cache();
        li_verify_clean();
        li_out("Чистый сайт включён. Заявки сохраняются в Netcat.");
    }
} catch (Throwable $error) {
    $li_ajax_ok = false;
    nc_set_http_response_code(500);
    li_out("ОШИБКА: " . $error->getMessage());
    exit(1);
}
