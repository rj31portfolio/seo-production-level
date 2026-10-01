<?php

namespace App\Services\AI;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class DeepSeekProvider implements AIProviderInterface
{
    private function endpoint(array $settings): string
    {
        $base = rtrim($settings['base_url'], '/');
        if (! in_array($base, ['https://api.deepseek.com', 'https://api.deepseek.com/v1'], true)) {
            throw new \RuntimeException('AI provider URL is not permitted.');
        }

return $base;
    }

    public function generate(array $settings, string $apiKey, array $messages): array
    {
        try {
            $response = Http::withToken($apiKey)->acceptJson()->timeout($settings['timeout'])->connectTimeout(10)->withOptions(['allow_redirects' => false])->post($this->endpoint($settings).'/chat/completions', ['model' => $settings['model'], 'temperature' => (float) $settings['temperature'], 'max_tokens' => (int) $settings['max_tokens'], 'stream' => false, 'thinking' => ['type' => 'disabled'], 'response_format' => ['type' => 'json_object'], 'messages' => $messages]);
            if (! $response->successful()) {
                throw new \RuntimeException('Integration unavailable. Provider returned HTTP '.$response->status().'.');
            }
            if (strlen($response->body()) > 1048576 || $response->json('choices.0.finish_reason') !== 'stop') {
                throw new \RuntimeException('AI response was incomplete or exceeded the response size limit.');
            }
            $data = json_decode((string) $response->json('choices.0.message.content'), true, 32, JSON_THROW_ON_ERROR);
            $recommendations = $data['recommendations'] ?? null;
            if (! is_array($recommendations) || ! $recommendations || count($recommendations) > 20) {
                throw new \RuntimeException('AI provider returned an invalid recommendation format.');
            }
            foreach ($recommendations as $text) {
                if (! is_string($text) || mb_strlen($text) > 5000) {
                    throw new \RuntimeException('AI recommendation exceeded its format limits.');
                }
            }
            $usage = [];
            foreach (['prompt_tokens', 'completion_tokens', 'total_tokens'] as $field) {
                $value = $response->json('usage.'.$field);
                $usage[$field] = is_int($value) && $value >= 0 ? $value : null;
            }

            return ['recommendations' => array_values($recommendations), 'usage' => $usage];
        } catch (ConnectionException) {
            throw new \RuntimeException('Integration unavailable. Provider request failed or timed out.');
        } catch (\JsonException) {
            throw new \RuntimeException('Integration unavailable. Provider returned invalid JSON.');
        }
    }

    public function test(array $settings, string $apiKey): bool
    {
        try {
            return Http::withToken($apiKey)->acceptJson()->timeout($settings['timeout'])->withOptions(['allow_redirects' => false])->get($this->endpoint($settings).'/models')->successful();
        } catch (ConnectionException) {
            return false;
        }
    }
}
