<?php

function h($v){
    return htmlspecialchars($v, ENT_QUOTES);
}

$siteId = (int)$site['id'];

?>

<h2>AI генерация</h2>

<p>
<b>Сайт:</b> <?=h($site['domain'])?>
</p>

<p>
<a href="/sites/subcfg?id=<?=$siteId?>">Метаданные</a> |
<a href="/sites/texts?id=<?=$siteId?>">Тексты</a>
</p>

<hr>

<h3>Генерация</h3>

<form method="post" action="/ai/generate-meta?id=<?=$siteId?>">

<button type="submit" style="padding:10px 16px;">
Сгенерировать мета для основного домена
</button>

</form>

<br>

<form method="post" action="/ai/generate-subdomains?id=<?=$siteId?>">

<button type="submit" style="padding:10px 16px;background:#2f80ed;color:#fff;border:0;border-radius:6px;">
Сгенерировать мета для всех поддоменов
</button>

</form>

<p>
После генерации откроется страница метаданных.
</p>