<?php

namespace Devlense\FilamentAi;

use Devlense\FilamentAi\Contracts\AiProviderInterface;
use Devlense\FilamentAi\Providers\OpenAiProvider;
use Exception;

class AiProviderFactory
{
    public static function make(string $provider): AiProviderInterface
    {
        return match($provider) {
            'openai' => new OpenAiProvider(),
            // Add other providers here
            default => throw new Exception('Invalid AI provider selected.')
        };
    }
}
