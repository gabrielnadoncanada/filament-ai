<?php

namespace Devlense\FilamentAi\Contracts;

interface AiProviderInterface
{
    public function chat(array $payload): array;
    public function listModels(): array;
    public function stream(array $payload);
}
