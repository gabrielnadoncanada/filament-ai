<?php

namespace Devlense\FilamentAi;

use Devlense\FilamentAi\Contracts\AiProviderInterface;

class FilamentAi
{
    protected AiProviderInterface $aiProvider;
    protected string $provider;

    public array $eloquent_model = [];
    public string $system_prompt;
    public string $user_prompt;
    public array $function;

    public function __construct(string $provider = null)
    {
        $this->provider = $provider ?? config('filament-ai.default_provider', 'openai');
        $this->aiProvider = AiProviderFactory::make($this->provider);
    }

    /**
     * Initialize a chat session.
     */
    public static function chat(string $provider = null): self
    {
        return new self($provider);
    }

    /**
     * Set the AI provider (OpenAI, Azure, etc.).
     */
    public function setProvider(string $provider): self
    {
        $this->provider = $provider;
        $this->aiProvider = AiProviderFactory::make($provider);
        return $this;
    }

    /**
     * Set the system prompt.
     */
    public function system(string $system_prompt): self
    {
        $this->system_prompt = $system_prompt;
        return $this;
    }

    /**
     * Set functions to pass to the AI provider.
     */
    public function function(array $function): self
    {
        $this->function = $function;
        return $this;
    }

    /**
     * Set Eloquent model to use as context.
     */
    public function model(string $eloquent_model, int $id, array $select_data = ['*']): self
    {
        $this->eloquent_model = [
            'class' => $eloquent_model,
            'id' => $id,
            'select_data' => $select_data,
        ];
        return $this;
    }

    /**
     * Set the user prompt.
     */
    public function prompt(string $prompt): self
    {
        $this->user_prompt = $prompt;
        return $this;
    }

    /**
     * Build the payload for the AI request.
     */
    protected function buildPayload(): array
    {
        $payload = [
            'messages' => [],
        ];

        if ($this->system_prompt) {
            $payload['messages'][] = [
                'role' => 'system',
                'content' => $this->system_prompt,
            ];
        }

        if (!empty($this->eloquent_model)) {
            $model = $this->eloquent_model['class'];
            $id = $this->eloquent_model['id'];
            $select_data = $this->eloquent_model['select_data'];

            $model_data = $model::select($select_data)->where('id', $id)->first()->toJson(JSON_PRETTY_PRINT);

            $payload['messages'][] = [
                'role' => 'system',
                'content' => "Consider the following data: ``` $model_data ```",
            ];
        }

        if ($this->user_prompt) {
            $payload['messages'][] = [
                'role' => 'user',
                'content' => $this->user_prompt,
            ];
        }

        if (!empty($this->function)) {
            $payload['functions'] = [$this->function];
        }

        return $payload;
    }

    /**
     * Send the chat request to the selected AI provider.
     */
    public function send()
    {
        $payload = $this->buildPayload();
        return $this->aiProvider->chat($payload);
    }

    /**
     * Stream a chat response from the selected AI provider.
     */
    public function stream(string $prompt, string $system_prompt, string $model_data_json, string $model = null)
    {
        $model = $model ?? config('filament-ai.default_model', 'gpt-3.5-turbo');

        $payload = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => "$system_prompt ``` $model_data_json ```",
                ],
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
        ];

        return $this->aiProvider->stream($payload);
    }

    /**
     * Get a list of available AI models from the provider.
     */
    public function listModels(): array
    {
        return $this->aiProvider->listModels();
    }

    /**
     * Handle exception from AI provider.
     */
    protected function handleException(\Exception $e): void
    {
        \Filament\Notifications\Notification::make()
            ->title('AI Error:')
            ->body($e->getMessage())
            ->danger()
            ->persistent()
            ->send();
    }
}
