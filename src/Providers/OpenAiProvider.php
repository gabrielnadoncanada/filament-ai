<?php

namespace Devlense\FilamentAi\Providers;

use Devlense\FilamentAi\Contracts\AiProviderInterface;
use OpenAI\Client;

class OpenAiProvider implements AiProviderInterface
{
    protected Client $client;

    public function __construct()
    {
        $this->client = OpenAI::client(config('filament-ai.openai_api_key'));
    }

    public function chat(array $payload): array
    {
        return $this->client->chat()->create($payload);
    }

    public function listModels(): array
    {
        return collect($this->client->models()->list()->data)
            ->filter(fn ($model) => str_starts_with($model->id, 'gpt-'))
            ->pluck('id')
            ->toArray();
    }

    public function stream(array $payload)
    {
        return $this->client->chat()->createStreamed($payload);
    }
}
