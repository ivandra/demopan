<h2>Deploy report #<?= (int)$deploy['id'] ?></h2>

<p><a href="/">← к сайтам</a></p>

<p>
    status: <b><?= htmlspecialchars($deploy['status']) ?></b><br>
    created_at: <?= htmlspecialchars($deploy['created_at']) ?><br>
</p>

<?php if (!empty($deploy['last_error'])): ?>
    <h3 style="color:#b00">Ошибка</h3>
    <pre style="white-space:pre-wrap"><?= htmlspecialchars($deploy['last_error']) ?></pre>
<?php endif; ?>

<h3>Payload</h3>
<pre style="white-space:pre-wrap"><?= htmlspecialchars($deploy['payload'] ?? '') ?></pre>

<h3>Response</h3>
<pre style="white-space:pre-wrap"><?= htmlspecialchars($deploy['response'] ?? '') ?></pre>
