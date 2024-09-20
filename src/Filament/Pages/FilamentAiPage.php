<?php

namespace Devlense\FilamentAi\Filament\Pages;

use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Form;

class FilamentAiPage extends \Filament\Pages\Page
{
    use InteractsWithForms;

    public ?array $data = [];

    public static function getSlug(): string
    {
        return config('filament-ai.slug');
    }

    public function getTitle(): string | \Illuminate\Contracts\Support\Htmlable
    {
        return __('filament-ai::filament-ai.title');
    }

    public static function getNavigationLabel(): string
    {
        return __('filament-ai::filament-ai.title');
    }

    public static function getNavigationGroup(): ?string
    {
        return config('filament-ai.use_navigation_group') ? __('filament-ai::filament-ai.navigation_group') : null;
    }

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static string $view = 'filament-ai::filament.pages.filament-ai';

    private array $selected_columns;

    public $ai_response;

    public bool $finished = false;

    public bool $question = false;

    public function mount(): void
    {
        $this->form->fill([
            'filament_ai' => config('filament-ai.default_openfilament_ai'),
        ]);
    }

    public function form(Form $form): Form
    {

        $default_openfilament_ai = config('filament-ai.default_openfilament_ai');
        $eloquent_model = config('filament-ai.eloquent_model');
        $field_label = config('filament-ai.field_label');
        $field_id = config('filament-ai.field_id');
        $predefined_prompts_actions = $this->predefinedPromptsToActions(config('filament-ai.predefined_prompts', []));
        $selected_columns = config('filament-ai.selected_columns');

        $disabled_openfilament_ai_selection = config('filament-ai.disable_openfilament_ai_selection');

        return $form
            ->schema([

                Forms\Components\Select::make('item')
                    ->label(__('filament-ai::filament-ai.form.item'))
                    ->live(false, 500)
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => $eloquent_model::where($field_label, 'like', "%{$search}%")->limit(50)->pluck($field_label, $field_id)->toArray())
                    ->afterStateUpdated(function (Forms\Set $set, $state) use ($eloquent_model, $selected_columns, $field_id) {
                        $data = $eloquent_model::select($selected_columns)->where($field_id, $state)->first();
                        $set('context_data', $data->toJson(JSON_PRETTY_PRINT));
                    })
                    ->required(),

                Forms\Components\Select::make('filament_ai')
                    ->label(__('filament-ai::filament-ai.form.filament_ai'))
                    ->options(function () use ($default_openfilament_ai, $disabled_openfilament_ai_selection) {
                        if ($disabled_openfilament_ai_selection) {
                            return [$default_openfilament_ai => $default_openfilament_ai];
                        }

                        return \Devlense\FilamentAi\FilamentAi::chat()->listModels();
                    })
                    ->disabled($disabled_openfilament_ai_selection)
                    ->required(),

                Forms\Components\Textarea::make('context_data')
                    ->label(__('filament-ai::filament-ai.form.context_data'))
                    ->hidden(fn (Forms\Get $get) => ! $get('item'))
                    ->rows(5)
                    ->columnSpanFull()
                    ->required(),

                Forms\Components\Textarea::make('prompt')
                    ->label(__('filament-ai::filament-ai.form.prompt'))
                    ->rows(3)
                    ->placeholder(__('filament-ai::filament-ai.form.prompt_placeholder'))
                    ->live()
                    ->required()
                    ->helperText(__('filament-ai::filament-ai.form.prompt_helper_text'))
                    ->columnSpanFull(),

                Forms\Components\Fieldset::make(__('filament-ai::filament-ai.form.predefined_prompts_fieldset'))
                    ->hidden(count($predefined_prompts_actions) == 0)
                    ->schema([
                        Forms\Components\Actions::make($predefined_prompts_actions)->columnSpanFull(),
                    ]),

            ])
            ->columns(2)
            ->statePath('data');
    }

    protected function predefinedPromptsToActions($prompts = []): array
    {
        return array_map(function ($prompt) {
            return Forms\Components\Actions\Action::make($prompt['name'])
                ->badge()
                ->label($prompt['name'])
                ->action(fn (Forms\Set $set) => $set('prompt', $prompt['prompt']));
        }, $prompts);
    }

    public function submitPrompt()
    {
        $data = $this->form->getState();

        $this->question = true;
        $this->finished = false;
        $this->ai_response = '';

        // Con questo trick funziona bene, al posto di fare $this->generate()
        $this->js('$wire.generate()');
    }

    /**
     * @throws \Exception
     */
    public function generate(): void
    {
        $data = $this->form->getState();

        // Se è disabilitata la selezione del modello, sempre quello di default
        if (config('filament-ai.disable_openfilament_ai_selection')) {
            $data['filament_ai'] = config('filament-ai.default_openfilament_ai');
        }

        $system_prompt = config('filament-ai.system_prompt');

        $stream = \Devlense\FilamentAi\FilamentAi::chat()->stream(
            $data['prompt'],
            $system_prompt,
            $data['context_data'],
            $data['filament_ai'],
        );

        $this->ai_response = '';
        $this->finished = false;

        foreach ($stream as $response) {

            $content = $response->choices[0]->delta->content;

            $this->ai_response .= $content;

            if (is_null($content)) {
                // exit loop
                break;
            }

            $this->stream(to: 'ai_response', content: $content);
        }

        // Alla fine converte l'eventuale markdown in html
        $this->ai_response = str($this->ai_response)->markdown();
        $this->finished = true;
    }

    protected function getFormActions(): array
    {
        return [
            Forms\Components\Actions\Action::make('generate')
                ->label(__('filament-ai::filament-ai.form.submit_prompt'))
                ->icon('heroicon-o-sparkles')
                ->action('submitPrompt'),
        ];
    }
}
