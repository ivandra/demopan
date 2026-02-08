<?php
// app/Views/sites/create.php
// Ожидаемые переменные из контроллера:
// - $templates (array)
// - $accounts (array)  // registrar accounts
//
// Может приходить одно из двух (поддерживаем оба варианта):
// A) Новый вариант: $domain (string), $template (string), $checkResult (array)
// B) Старый вариант: $form (array) или $_POST, $domainCheck (array), $domainCheckError (string)

function h($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

// -------------------- unify check payload (optional server-side check result) --------------------
$check = null;

if (isset($checkResult) && is_array($checkResult)) {
    $check = $checkResult;
} else {
    $domainCheck = $domainCheck ?? null;
    $domainCheckError = (string)($domainCheckError ?? '');
    if ($domainCheckError !== '') $check = ['ok' => false, 'error' => $domainCheckError];
    elseif (is_array($domainCheck)) $check = $domainCheck;
}

// -------------------- form values --------------------
$formDomain = '';
$formTemplate = '';

if (isset($domain)) $formDomain = (string)$domain;
elseif (isset($form) && is_array($form)) $formDomain = (string)($form['domain'] ?? '');
else $formDomain = (string)($_POST['domain'] ?? '');

if (isset($template)) $formTemplate = (string)$template;
elseif (isset($form) && is_array($form)) $formTemplate = (string)($form['template'] ?? '');
else $formTemplate = (string)($_POST['template'] ?? '');

if ($formTemplate === '' && !empty($templates[0])) $formTemplate = (string)$templates[0];

// registrar account selected
$selectedAccId = (int)($registrar_account_id ?? ($_POST['registrar_account_id'] ?? 0));

// начальный JSON для статуса (если сервер уже что-то проверял)
$initialJson = '';
if (is_array($check)) {
    // иногда check может быть не в формате {ok:true,...} — это не страшно, JS сам дорисует как "сырой ответ"
    $initialJson = json_encode($check, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
?>

<h2>Создать сайт</h2>

<style>
    .domain-status {
        border: 1px solid #e5e5e5;
        background: #fafafa;
        padding: 10px;
        margin: 10px 0;
        font-size: 13px;
        line-height: 1.4;
    }
    .domain-status .muted { color:#777; }
    .domain-status .ok { color:#0a0; font-weight:600; }
    .domain-status .bad { color:#b00; font-weight:600; }
    .domain-status .row { margin: 2px 0; }
    .domain-status code { background:#fff; padding:1px 4px; border:1px solid #eee; }
</style>

<form method="post" action="/sites/create" id="createSiteForm">
    <p>
        <label>Домен</label><br>
        <input
            type="text"
            name="domain"
            id="domainInput"
            placeholder="example.com"
            required
            style="width:100%"
            value="<?= h($formDomain) ?>"
            autocomplete="off"
        >
        <small style="color:#666;">
            Можно вводить без https:// (например: testovoe.casino). Путь /... будет отброшен.
        </small>
    </p>

    <div class="domain-status" id="domainStatusBox">
        <div><b>Проверка домена</b></div>
        <div class="muted" id="domainStatusText">Введите домен и нажмите “Проверить домен” (или просто начните ввод — проверка запустится автоматически).</div>
    </div>

    <p>
        <button type="button" id="btnCheckDomain">Проверить домен</button>
    </p>

    <p>
        <label>Registrar account</label><br>
        <select name="registrar_account_id" required>
            <?php foreach (($accounts ?? []) as $a): ?>
                <?php
                    $id = (int)$a['id'];
                    $isSandbox = (int)($a['is_sandbox'] ?? 1) === 1;
                    $label = '#'.$id.' namecheap '.($isSandbox ? 'sandbox' : 'prod').' ('.($a['api_user'] ?? '').')';
                ?>
                <option value="<?= h($id) ?>" <?= ($selectedAccId === $id ? 'selected' : '') ?>>
                    <?= h($label) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </p>

    <p>
        <label>Шаблон</label><br>
        <select name="template" required>
            <?php foreach (($templates ?? []) as $t): ?>
                <?php $t = (string)$t; ?>
                <option value="<?= h($t) ?>" <?= ($formTemplate === $t ? 'selected' : '') ?>>
                    <?= h($t) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </p>

    <div style="margin-top:10px;">
        <button type="submit" formaction="/sites/create">Создать</button>
        <button type="submit" formaction="/sites/check-domain">Проверить домен и цену</button>
    </div>
</form>

<script>
(function(){
    const input = document.getElementById('domainInput');
    const box = document.getElementById('domainStatusBox');
    const text = document.getElementById('domainStatusText');
    const btn = document.getElementById('btnCheckDomain');

    if (!input || !box || !text || !btn) return;

    const initial = <?= $initialJson !== '' ? $initialJson : 'null' ?>;

    function esc(s){
        return String(s ?? '').replace(/[&<>"']/g, m => ({
            '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'
        }[m]));
    }

    function render(obj){
        // ожидаемый ответ: {ok:true, domain, exists, exists_id, dns_a, vps_ip_guess, fastpanel_server_id_guess}
        if (!obj) {
            text.innerHTML = '<span class="muted">—</span>';
            return;
        }

        if (obj.ok !== true) {
            const err = obj.error ? esc(obj.error) : 'unknown';
            text.innerHTML = '<div class="row"><span class="bad">Ошибка проверки:</span> ' + err + '</div>'
                + '<div class="row muted">RAW: <code>' + esc(JSON.stringify(obj)) + '</code></div>';
            return;
        }

        const exists = obj.exists
            ? '<span class="bad">уже добавлен</span> (id ' + esc(obj.exists_id) + ')'
            : '<span class="ok">в системе не найден</span>';

        const dnsA = (obj.dns_a && obj.dns_a.length)
            ? esc(obj.dns_a.join(', '))
            : '<span class="muted">A-запись не найдена</span>';

        const ipGuess = obj.vps_ip_guess ? esc(obj.vps_ip_guess) : '<span class="muted">нет</span>';
        const sidGuess = (obj.fastpanel_server_id_guess !== null && obj.fastpanel_server_id_guess !== undefined)
            ? esc(obj.fastpanel_server_id_guess)
            : '<span class="muted">нет</span>';

        text.innerHTML =
            '<div class="row"><b>Домен:</b> ' + esc(obj.domain || '') + '</div>' +
            '<div class="row"><b>Статус:</b> ' + exists + '</div>' +
            '<div class="row"><b>DNS A сейчас:</b> ' + dnsA + '</div>' +
            '<div class="row"><b>vps_ip (guess):</b> ' + ipGuess + '</div>' +
            '<div class="row"><b>fastpanel_server_id (guess):</b> ' + sidGuess + '</div>';
    }

    async function requestJson(url, opts){
        const r = await fetch(url, Object.assign({credentials:'same-origin'}, opts || {}));
        const ct = (r.headers.get('content-type') || '').toLowerCase();
        let j = null;

        if (ct.includes('application/json')) {
            j = await r.json();
        } else {
            // на случай если сервер вернул HTML/текст
            const t = await r.text();
            try { j = JSON.parse(t); } catch(e) {
                return {ok:false, error:'Non-JSON response (' + r.status + ')', raw:t};
            }
        }
        return j;
    }

    async function check(){
        const v = (input.value || '').trim();
        if (!v) { render(null); return; }

        text.innerHTML = '<span class="muted">Проверяю...</span>';

        // 1) пробуем GET /sites/check-domain?domain=...
        try {
            const j1 = await requestJson('/sites/check-domain?domain=' + encodeURIComponent(v), {method:'GET'});
            if (j1 && (j1.ok === true || j1.error)) { render(j1); return; }
        } catch(e) { /* ignore */ }

        // 2) fallback POST /sites/check-domain
        try {
            const body = 'domain=' + encodeURIComponent(v);
            const j2 = await requestJson('/sites/check-domain', {
                method:'POST',
                headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},
                body: body
            });
            render(j2);
        } catch(e) {
            render({ok:false, error:(e && e.message) ? e.message : String(e)});
        }
    }

    let t = null;
    input.addEventListener('input', function(){
        clearTimeout(t);
        t = setTimeout(check, 350);
    });

    btn.addEventListener('click', function(){
        clearTimeout(t);
        check();
    });

    // если сервер уже прислал результат проверки — покажем
    if (initial) {
        render(initial);
    } else {
        // или проверим поле, если оно заполнено
        setTimeout(check, 50);
    }
})();
</script>
