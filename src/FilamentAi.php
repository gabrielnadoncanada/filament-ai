<?php

namespace Devlense\FilamentAi;

use Filament\Notifications\Notification;
use Illuminate\Support\Str;
use OpenAI;

class FilamentAi
{
    public OpenAI\Client $openai_client;

    public array $eloquent_model = [];

    public string $openai;

    public $system_prompt;

    public $user_prompt;

    public $function;

    public function __construct()
    {
        // Inizializza il client OpenAI
        $this->openai_client = OpenAI::client(config('filament-ai.openai_api_key'));

        // Inizializza il modello OpenAI
        $this->openai = config('filament-ai.default_openai');
    }

    public static function chat(): self
    {
        return new self();
    }

    // Metodo per imdevlense il prompt del sistema
    public function system(string $system_prompt): self
    {
        $this->system_prompt = $system_prompt;

        return $this;
    }

    // Metodo per imdevlense una funzione
    public function function(array $function): self
    {
        $this->function = $function;

        return $this;
    }

    // Metodo per imdevlense il modello OpenAI
    public function openai(string $model): self
    {
        $this->openai = $model;

        return $this;
    }

    /**
     * Set the Eloquent model to use.
     *
     * @param  string  $eloquent_model Nome della classe del modello Eloquent da utilizzare
     * @param  int  $id ID del record da utilizzare
     * @param  array  $select_data Dati da selezionare
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

    // Metodo chat modificato per supportare catenazione
    public function prompt(string $prompt): self
    {
        $this->user_prompt = $prompt;

        return $this;
    }

    // Metodo per eseguire la richiesta
    public function send()
    {
        // Preparazione del payload
        $payload = [
            'model' => $this->openai,
            'messages' => [],
        ];

        // Se è impostato il prompt del sistema
        if ($this->system_prompt) {
            $payload['messages'][] = [
                'role' => 'system',
                'content' => $this->system_prompt,
            ];
        }

        // Se è impostata la funzione
        if ($this->function) {
            $payload['functions'] = [$this->function];
        }

        if (! empty($this->eloquent_model)) {
            $model = $this->eloquent_model['class'];
            $id = $this->eloquent_model['id'];
            $select_data = $this->eloquent_model['select_data'];

            // Seleziona i dati dal modello Eloquent
            $model_data = $model::select($select_data)->where('id', $id)->first()->toJson(JSON_PRETTY_PRINT);

            // aggiungi i dati del modello al payload
            $payload['messages'][] = [
                'role' => 'system',
                'content' => "Consider the data at the end of this message as context and answer the questions I will ask you later. ``` $model_data ```",
            ];
        }

        // Se è impostato il prompt dell'utente
        if ($this->user_prompt) {
            $payload['messages'][] = [
                'role' => 'user',
                'content' => $this->user_prompt,
            ];
        }

        // Esegui la richiesta al servizio OpenAI
        try {
            $response = $this->openai_client->chat()->create($payload);

            // Check if there's a function call response
            if (isset($response->choices[0]->message->functionCall->arguments)) {
                return json_decode($response->choices[0]->message->functionCall->arguments);
            }

            // If not, then return the standard message content
            return $response->choices[0]->message->content
                ? json_decode($response->choices[0]->message->content)
                : null;
        } catch (\Exception $e) {
            $this->handleException($e);

            return null;
        }

    }

    /**
     * Return list of models from OpenAI API. Only GPT* models are returned.
     */
    public function listModels(): array
    {
        return collect($this->openai_client->models()->list()->data)
            ->sortByDesc('created')
            ->pluck('id')
            ->filter(fn ($id) => Str::startsWith($id, 'gpt-'))
            ->mapWithKeys(function ($id) {
                return [$id => $id];
            })
            ->toArray();
    }

    /**
     * Stream response from OpenAI API.
     */
    public function stream(
        string $prompt,
        string $system_prompt,
        string $model_data_json,
        string $model = 'gpt-3.5-turbo'
    ): ?object {
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

        try {

            return $this->openai_client->chat()->createStreamed($payload);

        } catch (\Exception $e) {
            $this->handleException($e);

            return null;
        }
    }

    /**
     * Handle exception from OpenAI API call.
     */
    protected function handleException(\Exception $e): void
    {
        Notification::make()
            ->title('OpenAI Error:')
            ->body($e->getMessage())
            ->danger()
            ->persistent()
            ->send();
    }
}
