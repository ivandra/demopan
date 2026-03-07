<?php

class DeepseekClient
{
    private string $apiKey;
    private string $baseUrl;
    private int $timeout;

    public function __construct(string $apiKey, string $baseUrl = 'https://api.deepseek.com', int $timeout = 120)
    {
        $this->apiKey = trim($apiKey);
        $this->baseUrl = rtrim(trim($baseUrl), '/');
        $this->timeout = $timeout;

        if ($this->apiKey === '') {
            throw new RuntimeException('DeepSeek API key is empty');
        }
    }

    public function chat(array $messages, string $model = 'deepseek-chat', float $temperature = 0.7, int $maxTokens = 1200): array
    {
        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
        ];

        $url = $this->baseUrl . '/chat/completions';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 20,
        ]);

        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $err !== '') {
            throw new RuntimeException('DeepSeek curl error: ' . $err);
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new RuntimeException('DeepSeek bad JSON response: ' . mb_substr($raw, 0, 500));
        }

        if ($http >= 400) {
            $msg = (string)($data['error']['message'] ?? ('HTTP ' . $http));
            throw new RuntimeException('DeepSeek API error: ' . $msg);
        }

        return $data;
    }

    public function simpleText(string $systemPrompt, string $userPrompt, string $model = 'deepseek-chat', float $temperature = 0.7, int $maxTokens = 1200): string
    {
        $res = $this->chat([
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ], $model, $temperature, $maxTokens);

        $content = (string)($res['choices'][0]['message']['content'] ?? '');
        if ($content === '') {
            throw new RuntimeException('DeepSeek returned empty content');
        }

        return $content;
    }
}