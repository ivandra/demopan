<?php
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES); }

$row = is_array($row ?? null) ? $row : [];
$apiKey = (string)($apiKey ?? '');

$provider = (string)($row['provider'] ?? 'deepseek');
$model = (string)($row['model'] ?? 'deepseek-chat');
$temperature = (string)($row['temperature'] ?? '0.7');
$maxTokens = (string)($row['max_tokens'] ?? '1200');
$promptV1 = (string)($row['prompt_v1'] ?? '');
$promptV2 = (string)($row['prompt_v2'] ?? '');
?>

<h2>AI настройки</h2>

<p>
  <a href="/sites">← К сайтам</a>
</p>

<form method="post" action="/ai/settings" style="max-width:980px;background:#fff;border:1px solid #ddd;border-radius:10px;padding:16px;">
  <div style="margin-bottom:12px;">
    <label><b>Провайдер</b></label><br>
    <input name="provider" value="<?= h($provider) ?>" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;">
  </div>

  <div style="margin-bottom:12px;">
    <label><b>API key</b></label><br>
    <input name="api_key" value="<?= h($apiKey) ?>" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;font-family:monospace;">
  </div>

  <div style="display:flex;gap:12px;align-items:flex-start;margin-bottom:12px;">
    <div style="flex:1;">
      <label><b>Модель</b></label><br>
      <input name="model" value="<?= h($model) ?>" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;">
    </div>
    <div style="width:180px;">
      <label><b>Temperature</b></label><br>
      <input name="temperature" value="<?= h($temperature) ?>" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;">
    </div>
    <div style="width:180px;">
      <label><b>Max tokens</b></label><br>
      <input name="max_tokens" value="<?= h($maxTokens) ?>" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;">
    </div>
  </div>

  <div style="margin-bottom:12px;">
    <label><b>Промпт вариант 1</b></label><br>
    <textarea name="prompt_v1" style="width:100%;height:220px;border:1px solid #ddd;border-radius:8px;padding:10px;font-family:monospace;"><?= h($promptV1) ?></textarea>
  </div>

  <div style="margin-bottom:12px;">
    <label><b>Промпт вариант 2</b></label><br>
    <textarea name="prompt_v2" style="width:100%;height:220px;border:1px solid #ddd;border-radius:8px;padding:10px;font-family:monospace;"><?= h($promptV2) ?></textarea>
  </div>
  
  <div style="margin-top:20px;">
		<label><b>Prompt: meta root</b></label><br>
		<textarea name="meta_prompt_root" rows="8" style="width:100%;"><?= htmlspecialchars((string)($row['meta_prompt_root'] ?? ''), ENT_QUOTES) ?></textarea>
	</div>

	<div style="margin-top:20px;">
		<label><b>Prompt: meta subdomains</b></label><br>
		<textarea name="meta_prompt_sub" rows="8" style="width:100%;"><?= htmlspecialchars((string)($row['meta_prompt_sub'] ?? ''), ENT_QUOTES) ?></textarea>
	</div>

	<div style="margin-top:20px;">
		<label><b>Prompt: text root</b></label><br>
		<textarea name="text_prompt_root" rows="8" style="width:100%;"><?= htmlspecialchars((string)($row['text_prompt_root'] ?? ''), ENT_QUOTES) ?></textarea>
	</div>

	<div style="margin-top:20px;">
		<label><b>Prompt: text subdomains</b></label><br>
		<textarea name="text_prompt_sub" rows="8" style="width:100%;"><?= htmlspecialchars((string)($row['text_prompt_sub'] ?? ''), ENT_QUOTES) ?></textarea>
	</div>

	<div style="margin-top:20px;">
		<label><b>Prompt: page text</b></label><br>
		<textarea name="page_prompt" rows="8" style="width:100%;"><?= htmlspecialchars((string)($row['page_prompt'] ?? ''), ENT_QUOTES) ?></textarea>
	</div>

	<div style="margin-top:20px;">
		<label><b>Prompt: page meta</b></label><br>
		<textarea name="page_meta_prompt" rows="8" style="width:100%;"><?= htmlspecialchars((string)($row['page_meta_prompt'] ?? ''), ENT_QUOTES) ?></textarea>
	</div>

  <div style="display:flex;gap:10px;">
    <button type="submit" style="padding:10px 14px;border-radius:8px;border:0;background:#2f80ed;color:#fff;font-weight:600;">
      Сохранить
    </button>

    <a href="/ai/test" style="display:inline-block;padding:10px 14px;border-radius:8px;border:1px solid #ddd;background:#fff;color:#222;text-decoration:none;">
      Проверить API
    </a>
  </div>
</form>

<div style="margin-top:14px;color:#666;font-size:12px;max-width:980px;">
  Сейчас мы настраиваем только подключение и шаблоны промптов.  
  Следующим этапом добавим страницу генерации для конкретного сайта и поддоменов.
</div>