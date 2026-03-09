<?php
// app/Views/sites/create.php

function h($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$check = null;

if (isset($checkResult) && is_array($checkResult)) {
    $check = $checkResult;
} else {
    $domainCheck = $domainCheck ?? null;
    $domainCheckError = (string)($domainCheckError ?? '');
    if ($domainCheckError !== '') {
        $check = ['ok' => false, 'error' => $domainCheckError];
    } elseif (is_array($domainCheck)) {
        $check = $domainCheck;
    }
}

$formDomain = '';
$formTemplate = '';

if (isset($domain)) {
    $formDomain = (string)$domain;
} elseif (isset($form) && is_array($form)) {
    $formDomain = (string)($form['domain'] ?? '');
} else {
    $formDomain = (string)($_POST['domain'] ?? '');
}

if (isset($template)) {
    $formTemplate = (string)$template;
} elseif (isset($form) && is_array($form)) {
    $formTemplate = (string)($form['template'] ?? '');
} else {
    $formTemplate = (string)($_POST['template'] ?? '');
}

if ($formTemplate === '' && !empty($templates[0])) {
    $formTemplate = (string)$templates[0];
}

$selectedAccId = (int)($registrar_account_id ?? ($_POST['registrar_account_id'] ?? 0));

$initialJson = '';
if (is_array($check)) {
    $initialJson = json_encode($check, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
?>

<div class="page-head">
    <h1 class="page-title">Создать сайт</h1>
    <div class="page-actions">
        <a class="btn btn-secondary" href="/sites">К списку сайтов</a>
    </div>
    <div class="page-subtitle">
        Сначала выберите домен, аккаунт регистратора и шаблон. Домен можно проверить до создания сайта.
    </div>
</div>

<div class="panel-grid panel-grid--2">
    <div class="panel-card stack-gap-lg">
        <h2 class="section-title">Новый сайт</h2>

        <form method="post" action="/sites/create" id="createSiteForm" class="stack-gap-md">
            <div class="field-row">
                <label>Домен</label>
                <input
                    type="text"
                    name="domain"
                    id="domainInput"
                    placeholder="example.com"
                    required
                    value="<?= h($formDomain) ?>"
                    autocomplete="off"
                >
                <div class="small muted">
                    Можно вводить без <code>https://</code>, например: <code>testovoe.casino</code>.
                    Путь <code>/...</code> будет отброшен.
                </div>
            </div>

            <div class="panel-card" id="domainStatusBox">
                <div><b>Проверка домена</b></div>
                <div class="small muted mt-8" id="domainStatusText">
                    Введите домен и нажмите «Проверить домен» или просто начните ввод —
                    проверка запустится автоматически.
                </div>
            </div>

            <div class="page-actions">
                <button type="button" class="btn btn-secondary" id="btnCheckDomain">Проверить домен</button>
            </div>

            <div class="field-row">
                <label>Аккаунт регистратора</label>
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
            </div>

            <div class="field-row">
                <label>Шаблон</label>
                <select name="template" required>
                    <?php foreach (($templates ?? []) as $t): ?>
                        <?php $t = (string)$t; ?>
                        <option value="<?= h($t) ?>" <?= ($formTemplate === $t ? 'selected' : '') ?>>
                            <?= h($t) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="page-actions">
                <button type="submit" formaction="/sites/create" class="btn btn-primary">Создать сайт</button>
                <button type="submit" formaction="/sites/check-domain" class="btn btn-secondary">Проверить домен и цену</button>
            </div>
        </form>
    </div>

    <div class="panel-card stack-gap-md">
        <h2 class="section-title">Что будет дальше</h2>

        <ul class="status-list small">
            <li>создаётся карточка сайта;</li>
            <li>сохраняется основной конфиг;</li>
            <li>далее вы переходите в обзор сайта;</li>
            <li>там уже идёте по шагам: домен → поддомены → контент → AI → build → deploy → SSL → webmaster.</li>
        </ul>

        <div class="note">
            Если домен уже куплен, всё равно удобно сначала проверить его на этом экране:
            панель покажет DNS A, guess по IP и наличие домена в системе.
        </div>
    </div>
</div>

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

    function badge(cls, txt){
        return '<span class="badge ' + cls + '">' + esc(txt) + '</span>';
    }

    function render(obj){
        if (!obj) {
            text.innerHTML = '<span class="small muted">—</span>';
            return;
        }

        if (obj.ok !== true) {
            const err = obj.error ? esc(obj.error) : 'unknown';
            text.innerHTML =
                '<div class="stack-gap-sm">' +
                    '<div>' + badge('badge-danger', 'Ошибка проверки') + '</div>' +
                    '<div class="small">' + err + '</div>' +
                '</div>';
            return;
        }

        const exists = obj.exists
            ? badge('badge-warning', 'Уже добавлен в систему') + ' <span class="small muted">id ' + esc(obj.exists_id) + '</span>'
            : badge('badge-success', 'В системе не найден');

        const dnsA = (obj.dns_a && obj.dns_a.length)
            ? esc(obj.dns_a.join(', '))
            : '<span class="small muted">A-запись не найдена</span>';

        const ipGuess = obj.vps_ip_guess ? esc(obj.vps_ip_guess) : '<span class="small muted">нет</span>';
        const sidGuess = (obj.fastpanel_server_id_guess !== null && obj.fastpanel_server_id_guess !== undefined)
            ? esc(obj.fastpanel_server_id_guess)
            : '<span class="small muted">нет</span>';

        text.innerHTML =
            '<div class="stack-gap-sm">' +
                '<div><b>Домен:</b> <code>' + esc(obj.domain || '') + '</code></div>' +
                '<div><b>Статус:</b> ' + exists + '</div>' +
                '<div><b>DNS A сейчас:</b> ' + dnsA + '</div>' +
                '<div><b>vps_ip (guess):</b> ' + ipGuess + '</div>' +
                '<div><b>fastpanel_server_id (guess):</b> ' + sidGuess + '</div>' +
            '</div>';
    }

    async function requestJson(url, opts){
        const r = await fetch(url, Object.assign({credentials:'same-origin'}, opts || {}));
        const ct = (r.headers.get('content-type') || '').toLowerCase();
        let j = null;

        if (ct.includes('application/json')) {
            j = await r.json();
        } else {
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

        text.innerHTML = '<span class="small muted">Проверяю...</span>';

        try {
            const j1 = await requestJson('/sites/check-domain?domain=' + encodeURIComponent(v), {method:'GET'});
            if (j1 && (j1.ok === true || j1.error)) { render(j1); return; }
        } catch(e) {}

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

    if (initial) {
        render(initial);
    } else {
        setTimeout(check, 50);
    }
})();
</script>