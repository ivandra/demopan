<?php
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$siteId = (int)($site['id'] ?? 0);
$domain = (string)($site['domain'] ?? '');
?>

<?php
$deployFeedback = null;
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
if (!empty($_SESSION['deploy_feedback'][$siteId])) {
    $deployFeedback = $_SESSION['deploy_feedback'][$siteId];
    unset($_SESSION['deploy_feedback'][$siteId]);
}
?>


<div class="page-head">
    <h1 class="page-title">Публикация на VPS</h1>
    <div class="page-actions">
        <a class="btn btn-secondary" href="/sites/overview?id=<?= $siteId ?>">Обзор</a>
        <a class="btn btn-secondary" href="/sites/files?id=<?= $siteId ?>">Файлы build</a>
        <a class="btn btn-secondary" href="/ssl/site?id=<?= $siteId ?>">SSL</a>
    </div>
    <div class="page-subtitle">
        Сайт: <code><?= h($domain) ?></code>
    </div>
</div>

<?php if (!empty($deployFeedback)): ?>
    <div class="alert alert-<?= !empty($deployFeedback['type']) && $deployFeedback['type'] === 'error' ? 'danger' : 'success' ?> mt-16">
        <b><?= h((string)($deployFeedback['message'] ?? '')) ?></b><br>
        <?php if (!empty($deployFeedback['report_url'])): ?>
            <a href="<?= h((string)$deployFeedback['report_url']) ?>">Открыть отчет</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="site-context panel-card">
    <div class="site-context__eyebrow">Что делается на этом экране</div>
    <div class="site-context__title">FastPanel / FTP / загрузка файлов / self-signed SSL</div>
    <div class="site-context__meta">
        Сначала выбирается сервер и IP, потом создается сайт в FastPanel, затем выгружаются актуальные данные сайта на VPS и при необходимости выпускается self-signed SSL.
    </div>
</div>

<div class="alert alert-info mt-16"><b>Что такое build:</b> build — это локальная собранная папка сайта внутри storage/builds. При нажатии на «Выгрузить на VPS» панель берет именно ее содержимое, архивирует и отправляет на сервер. Если вы меняли контент, SEO, файлы или тексты через панель, отдельный build вручную перед выгрузкой обычно не нужен.</div>

<?php if (!empty($ips_error)): ?>
    <div class="alert alert-danger mt-16">
        <b>Ошибка получения IP:</b><br>
        <?= h($ips_error) ?>
    </div>
<?php endif; ?>

<div class="panel-card mt-16 stack-gap-md">
    <h2 class="section-title">Сервер и IP</h2>

    <div class="field-row">
        <label>Сервер FastPanel</label>
        <select id="server_id"
                name="server_id"
                class="mono-input"
                onchange="location.href='/deploy?id=<?= (int)$siteId ?>&server_id='+this.value;">
            <?php foreach ($servers as $srv): ?>
                <option value="<?= (int)$srv['id'] ?>" <?= ((int)$srv['id'] === (int)$serverId ? 'selected' : '') ?>>
                    <?= h($srv['host']) ?> (<?= h($srv['username']) ?>)
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="field-row">
        <label>IP для сайта</label>

        <?php if (!empty($ips)): ?>
            <select id="ip" name="ip" class="mono-input" required>
                <?php foreach ($ips as $oneIp): ?>
                    <option value="<?= h($oneIp) ?>" <?= ($selectedIp !== '' && $oneIp === $selectedIp) ? 'selected' : '' ?>>
                        <?= h($oneIp) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        <?php else: ?>
            <input id="ip"
                   type="text"
                   name="ip"
                   class="mono-input"
                   placeholder="Например: 95.129.234.93"
                   value="<?= h($selectedIp ?? '') ?>"
                   required>
            <div class="small muted">
                Список IP не получен — введите IP вручную.
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="deploy-steps mt-16">
    <div class="deploy-step">
        <div class="deploy-step__num">Шаг 1</div>
        <div class="deploy-step__title">Создать сайт в FastPanel</div>
        <div class="small muted mb-14">
            Создаёт сайт, привязывает выбранный IP и подготавливает инфраструктуру для дальнейшего деплоя.
        </div>

        <form method="get" action="/deploy/create-site">
            <input type="hidden" name="id" value="<?= $siteId ?>">
            <input type="hidden" name="server_id" id="server_id_create" value="<?= (int)$serverId ?>">
            <input type="hidden" name="ip" id="ip_create" value="">
            <button type="submit" class="btn btn-primary" onclick="return fillCreateHidden();">
                Создать сайт в FastPanel
            </button>
        </form>
    </div>

    <div class="deploy-step">
        <div class="deploy-step__num">Шаг 2</div>
        <div class="deploy-step__title">Выгрузить актуальные данные на VPS</div>
        <div class="small muted mb-14">
            Собирает ZIP из текущего build, загружает его на сервер и обновляет файлы сайта на VPS.
        </div>

        <form method="post" action="/deploy/update-files?id=<?= $siteId ?>">
            <input type="hidden" name="server_id" id="server_id_update" value="<?= (int)$serverId ?>">
            <button type="submit" class="btn btn-primary">Выгрузить на VPS</button>
        </form>
    </div>

    <div class="deploy-step">
        <div class="deploy-step__num">Шаг 3</div>
        <div class="deploy-step__title">Выпустить self-signed SSL</div>
        <div class="small muted mb-14">
            Нужен для первичного запуска и технической проверки, пока не применён боевой SSL.
        </div>

        <form method="post" action="/deploy/issue-ssl?id=<?= $siteId ?>" data-confirm="Выпустить self-signed SSL для сайта <?= h($domain) ?>?">
            <button type="submit" class="btn btn-secondary">Выпустить self-signed SSL</button>
        </form>
    </div>

    <div class="deploy-step">
        <div class="deploy-step__num">Сервисный шаг</div>
        <div class="deploy-step__title">Сбросить состояние deploy</div>
        <div class="small muted mb-14">
            Сбрасывает FastPanel / FTP / Files статусы для повторной привязки и повторного деплоя.
        </div>

        <form method="post" action="/deploy/reset?id=<?= $siteId ?>" data-confirm="Сбросить привязку FastPanel/FTP для сайта?">
            <button type="submit" class="btn btn-danger">Сбросить deploy state</button>
        </form>
    </div>
</div>

<script>
function fillCreateHidden() {
    var srv = document.getElementById('server_id');
    var ip  = document.getElementById('ip');

    if (!srv || !ip) return false;

    document.getElementById('server_id_create').value = srv.value;
    document.getElementById('server_id_update').value = srv.value;
    document.getElementById('ip_create').value = ip.value;

    if (!ip.value) {
        alert('IP обязателен для создания сайта');
        return false;
    }
    return true;
}
</script>