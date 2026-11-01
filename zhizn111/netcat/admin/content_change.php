<?php

require_once "../connect_io.php";
require_once "../admin/lang/Russian_utf8.php";

/** @var nc_core $nc_core */
$nc_core->modules->load_env();
$instruction_url = defined("NC_INSTRUCTION_URL") ? NC_INSTRUCTION_URL : AI_SITE_CONTENT_INSTRUCTIONS_URL;

if (!$instruction_url) {
    exit();
}

$http = new nc_http();
$client_id = $nc_core->get_copy_id();

header("Content-Type: application/json; encoding=utf8;");

// Доступ: подпись панели (HMAC-SHA256 по секрету OAuth-клиента, он же Settings.AuthSecret)
// либо сессия супервизора этого сайта (кнопка в собственной админке).
// Подпись: sign = hash_hmac('sha256', "{client_id}|{ts}", AuthSecret), окно ±10 минут.
$auth_secret = (string)$nc_core->get_settings('AuthSecret');
$sign_ts = (int)$nc_core->input->fetch_get_post('ts');
$sign = (string)$nc_core->input->fetch_get_post('sign');
$signed_ok = $auth_secret !== '' && $sign !== '' && abs(time() - $sign_ts) <= 600
    && hash_equals(hash_hmac('sha256', $client_id . '|' . $sign_ts, $auth_secret), $sign);
$admin_ok = false;
if (!$signed_ok) {
    // connect_io.php сам не авторизует ($perm = null): поднимаем сессию так же, как
    // скрипты без function.inc.php (Authorize() → nc_user::attempt_to_authorize()).
    // Ветка меняет контент сайта по GET, поэтому кроме сессии нужен nc_token — иначе
    // чужая страница могла бы запустить применение инструкций руками супервизора (CSRF).
    Authorize();
    $admin_ok = $perm instanceof Permission && $perm->isSupervisor() && $nc_core->token->verify();
}

if (!$signed_ok && !$admin_ok) {
    header("HTTP/1.1 403 Forbidden");
    echo json_encode(array(
        "status" => "error",
        "message" => "forbidden: signature required",
    ));
    exit();
}

// URL для подтверждения batch'а (тот же базовый URL + /confirm)
$confirm_url = rtrim($instruction_url, '/') . '/confirm';

$allowed_classes = nc_content_changer_instruction::allowed_classes();

// Список отказов в ответе ограничен объёмом, а не 20 строками: счётчики всегда точные,
// список усекается только на аномальных прогонах (results_truncated — сколько не вошло)
$results_limit = 2000;

/**
 * Строка для JSON: json_encode() без флагов (так кодирует тело nc_http) на невалидном UTF-8
 * возвращает false, и подтверждение ушло бы с пустым телом. mb_scrub() — PHP 7.2+.
 *
 * @param mixed $value
 * @param int $max_length
 * @return string
 */
$json_safe_string = function ($value, $max_length = 800) {
    return mb_substr(mb_scrub((string)$value, 'UTF-8'), 0, $max_length, 'UTF-8');
};

/**
 * Подтверждение порции: POST с отказами (и предупреждениями) по Instruction_ID в JSON-теле.
 * Панель пишет их к инструкциям до подтверждения, поэтому исход применения известен ей и при
 * обрыве итогового ответа. client_id/batch_id дублируются в query string: панель, которая
 * читает подтверждение как прежний GET (из $_GET), получает их на том же месте.
 */
$confirm = function ($batch_id, array $failures, array $warnings = array()) use ($http, $confirm_url, $client_id) {
    $query = http_build_query(array("client_id" => $client_id, "batch_id" => $batch_id), "", "&");
    $url = $confirm_url . (strpos($confirm_url, "?") === false ? "?" : "&") . $query;

    return $http->make_post_request($url, array(
        "client_id" => $client_id,
        "batch_id" => $batch_id,
        "results" => $failures,
        "warnings" => $warnings,
    ));
};

try {
    $has_more = true;
    $batch_id = null;
    $total_processed = 0;
    $total_succeeded = 0;
    $results = array();
    $results_truncated = 0;
    $warnings = array();
    $warnings_truncated = 0;
    $batch_failures = array();
    $batch_warnings = array();
    $all_subdivision_reindex_rules = array();
    $site_id = null;

    while ($has_more) {
        if ($batch_id === null) {
            // Первый запрос — получаем первую порцию
            $response = $http->make_get_request($instruction_url, array(
                "client_id" => $client_id,
                "protocol" => "batch",
            ));
        } else {
            // Подтверждаем предыдущий batch (с его отказами) и получаем следующую порцию
            $response = $confirm($batch_id, $batch_failures, $batch_warnings);
            $batch_failures = array();
            $batch_warnings = array();
        }

        // Проверяем ответ
        if (empty($response["data"])) {
            break;
        }

        // Получаем данные batch'а
        $batch_data = nc_array_value($response["data"], "batch", array());
        $batch_id = nc_array_value($batch_data, "batch_id");
        $has_more = (bool)nc_array_value($batch_data, "has_more", false);

        $raw_instructions = array_values((array)nc_array_value($response["data"], "instructions", array()));
        // Instruction_ID в порядке порции; панель без поддержки их не шлёт — отказ уйдёт без id
        $raw_ids = array_values((array)nc_array_value($response["data"], "instruction_ids", array()));

        // Если инструкций нет — выходим
        if (empty($raw_instructions)) {
            // Если batch_id есть, но инструкций нет — нужно подтвердить последний batch
            if ($batch_id && !$has_more) {
                $confirm($batch_id, array());
            }
            break;
        }

        // Сохраняем site_id из первого ответа
        if ($site_id === null) {
            $site_id = nc_array_value($response["data"], "site_id");
        }

        // Десериализуем и выполняем инструкции
        $instructions = array();
        $instruction_ids = array();
        $subdivision_reindex_rules = array();
        $failures = array();
        $batch_warnings = array();

        foreach ($raw_instructions as $i => $raw_instruction) {
            $instruction_id = isset($raw_ids[$i]) ? (int)$raw_ids[$i] : null;
            $instruction = unserialize($raw_instruction, array('allowed_classes' => $allowed_classes));
            if ($instruction instanceof nc_content_changer_instruction) {
                $subdivision_id = $instruction->get_meta('subdivision_id');
                if ($subdivision_id) {
                    $subdivision_reindex_rules[] = "sub$subdivision_id*";
                }
                $instructions[] = $instruction;
                $instruction_ids[] = $instruction_id;
            } else {
                // Раньше такая инструкция пропускалась молча и считалась применённой
                $failures[] = array(
                    "instruction_id" => $instruction_id,
                    "instruction" => "?",
                    "error" => "instruction cannot be unserialized (unknown or disallowed class)",
                );
            }
        }
        $total_processed += count($failures);

        // Выполняем batch инструкций
        if (!empty($instructions)) {
            $runner = new nc_content_changer_runner($instructions, $site_id);
            $runner->run();
            $total_processed += count($instructions);
            $total_succeeded += $runner->get_succeeded_count();
            foreach ($runner->get_results() as $result) {
                $result_instruction_id = isset($instruction_ids[$result['index']]) ? $instruction_ids[$result['index']] : null;
                if ($result['status'] !== 'ok') {
                    $failures[] = array(
                        "instruction_id" => $result_instruction_id,
                        "instruction" => $json_safe_string($result['instruction'], 200),
                        "error" => $json_safe_string($result['error']),
                    );
                }
                // Инструкция применена, но по пути были ошибки SQL — не отказ, а предупреждение
                foreach ((array)nc_array_value($result, 'warnings', array()) as $warning) {
                    $batch_warnings[] = array(
                        "instruction_id" => $result_instruction_id,
                        "instruction" => $json_safe_string($result['instruction'], 200),
                        "warning" => $json_safe_string($warning),
                    );
                }
            }
        }

        foreach ($failures as $failure) {
            if (count($results) < $results_limit) {
                $results[] = $failure;
            } else {
                $results_truncated++;
            }
        }
        foreach ($batch_warnings as $warning) {
            if (count($warnings) < $results_limit) {
                $warnings[] = $warning;
            } else {
                $warnings_truncated++;
            }
        }
        $batch_failures = $failures;

        // Собираем правила переиндексации
        $all_subdivision_reindex_rules = array_merge($all_subdivision_reindex_rules, $subdivision_reindex_rules);
    }

    // Финальное подтверждение последнего batch'а (если есть)
    if ($batch_id !== null) {
        $confirm($batch_id, $batch_failures, $batch_warnings);
    }

    // Переиндексация поиска (один раз в конце)
    if ($total_processed > 0 && nc_module_check_by_keyword('search')) {
        $all_subdivision_reindex_rules = array_unique($all_subdivision_reindex_rules);
        $areas_to_reindex = trim(($site_id ? "site$site_id " : "") . implode(" ", $all_subdivision_reindex_rules));
        nc_search::index_area($areas_to_reindex);
    }

    // Честный статус: success только когда все инструкции прошли (предупреждения статус не
    // меняют); иначе partial. results — каждый отказ с Instruction_ID порции; warnings — ошибки
    // SQL у применённых инструкций. errors — устаревший краткий вид первых 20 отказов
    // ({instruction, error}) для ранних версий панели, не полная копия results.
    $failed = $total_processed - $total_succeeded;
    $legacy_errors = array();
    foreach (array_slice($results, 0, 20) as $failure) {
        $legacy_errors[] = array("instruction" => $failure["instruction"], "error" => $failure["error"]);
    }
    echo json_encode(array(
        "status" => $failed > 0 ? "partial" : "success",
        "processed" => $total_processed,
        "succeeded" => $total_succeeded,
        "failed" => $failed,
        "results" => $results,
        "results_truncated" => $results_truncated,
        "warnings" => $warnings,
        "warnings_truncated" => $warnings_truncated,
        "errors" => $legacy_errors,
    ), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);

} catch (Error $e) {
    echo json_encode(array(
        "status" => "error",
        "message" => $e->getMessage(),
    ), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
} catch (Exception $e) {
    echo json_encode(array(
        "status" => "error",
        "message" => $e->getMessage(),
    ), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
}
