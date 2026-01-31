<h2>Добавить FASTPANEL сервер</h2>

<p>
    <a href="/servers">← назад</a>
</p>

<form method="post" action="/servers/create">
    <p>
        <label>Название</label><br>
        <input name="title" style="width:100%" placeholder="VPS #1">
    </p>

    <p>
        <label>Host панели</label><br>
        <input name="host" style="width:100%" placeholder="95.129.234.20:8888">
        <small>Вводи без https:// (можно и с ним — нормализуем)</small>
    </p>

    <p>
        <label>Логин</label><br>
        <input name="username" style="width:100%">
    </p>

    <p>
        <label>Пароль</label><br>
        <input type="password" name="password" style="width:100%">
    </p>

    <p>
        <label>Проверять TLS сертификат</label><br>
        <select name="verify_tls">
            <option value="0" <?= ((int)($defaultVerifyTls ?? 0) === 0 ? 'selected' : '') ?>>0 (self-signed, не проверять)</option>
            <option value="1" <?= ((int)($defaultVerifyTls ?? 0) === 1 ? 'selected' : '') ?>>1 (проверять)</option>
        </select>
    </p>

    <button type="submit">Сохранить</button>
</form>
