<h2>Редактировать FASTPANEL сервер</h2>

<p>
    <a href="/servers">← назад</a>
    | <a href="/servers/test?id=<?= (int)$server['id'] ?>" target="_blank">Test connection</a>
</p>

<form method="post" action="/servers/edit?id=<?= (int)$server['id'] ?>">
    <p>
        <label>Название</label><br>
        <input name="title" style="width:100%" value="<?= htmlspecialchars($server['title']) ?>">
    </p>

    <p>
        <label>Host панели</label><br>
        <input name="host" style="width:100%" value="<?= htmlspecialchars($server['host']) ?>">
        <small>Без https:// (можно и с ним — нормализуем)</small>
    </p>

    <p>
        <label>Логин</label><br>
        <input name="username" style="width:100%" value="<?= htmlspecialchars($server['username']) ?>">
    </p>

    <p>
        <label>Пароль (оставь пустым, если не менять)</label><br>
        <input type="password" name="password" style="width:100%" value="">
    </p>

    <p>
        <label>Проверять TLS сертификат</label><br>
        <select name="verify_tls">
            <option value="0" <?= ((int)$server['verify_tls'] === 0 ? 'selected' : '') ?>>0 (self-signed, не проверять)</option>
            <option value="1" <?= ((int)$server['verify_tls'] === 1 ? 'selected' : '') ?>>1 (проверять)</option>
        </select>
    </p>

    <button type="submit">Сохранить</button>
</form>
