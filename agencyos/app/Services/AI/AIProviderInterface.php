<?php

namespace App\Services\AI;

interface AIProviderInterface
{
    public function generate(array $settings, string $apiKey, array $messages): array;

    public function test(array $settings, string $apiKey): bool;
}
