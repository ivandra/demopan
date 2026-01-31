<h2>Build: <?= htmlspecialchars($site['domain'] ?? '') ?></h2>

<p style="font-size:13px;opacity:.85;">
    config.php генерируется в: <code><?= htmlspecialchars($configTargetPath) ?></code>
    | <a href="/sites/files/edit?id=<?= (int)($site['id'] ?? 0) ?>&file=config.php">открыть в Files</a>
</p>

<p>
    <a href="/">← назад</a> |
    <a href="/sites/edit?id=<?= (int)($site['id'] ?? 0) ?>">Редактировать</a> |
    <a href="/sites/pages?id=<?= (int)($site['id'] ?? 0) ?>">Pages</a> |
    <a href="/sites/texts?id=<?= (int)($site['id'] ?? 0) ?>">Texts</a> |
    <a href="/sites/files?id=<?= (int)($site['id'] ?? 0) ?>">Files</a>
</p>

<hr>

<?php if (!empty($report['ok'])): ?>
    <h3 style="color:green;">OK</h3>
<?php else: ?>
    <h3 style="color:red;">FAILED</h3>
<?php endif; ?>

<?php if (!empty($report['errors'])): ?>
    <h4>Ошибки</h4>
    <ul>
        <?php foreach ($report['errors'] as $e): ?>
            <li><?= htmlspecialchars($e) ?></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php if (!empty($report['warnings'])): ?>
    <h4>Предупреждения</h4>
    <ul>
        <?php foreach ($report['warnings'] as $w): ?>
            <li><?= htmlspecialchars($w) ?></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php if (!empty($report['created_texts'])): ?>
    <h4>Созданные тексты</h4>
    <ul>
        <?php foreach ($report['created_texts'] as $f): ?>
            <li><code><?= htmlspecialchars($f) ?></code></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php if (!empty($report['unused_texts'])): ?>
    <h4>Неиспользуемые texts</h4>
    <ul>
        <?php foreach ($report['unused_texts'] as $f): ?>
            <li><code><?= htmlspecialchars($f) ?></code></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
