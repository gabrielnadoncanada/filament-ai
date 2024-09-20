<?php

namespace Devlense\FilamentAi\Filament\Fields;

use Filament\Forms\Components\Section;

use Filament\Forms\Components\Field;
use Filament\Forms;
use Closure;
use Devlense\FilamentAi\FilamentAi;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Infolists\Concerns\InteractsWithInfolists;

class FilamentAiField extends Section
{
    private Closure|bool $disableModelSelection;
    private Closure|string $eloquentModel;
    private string|Closure $defaultModel;
    private string|Closure $fieldLabel;
    private string|Closure $fieldId;
    private Closure|array $selectedColumns;
    private Closure|array $predefinedPrompts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->defaultModel = config('filament-ai.default_openai_model');
        $this->eloquentModel = config('filament-ai.eloquent_model');
        $this->fieldLabel = config('filament-ai.field_label');
        $this->fieldId = config('filament-ai.field_id');
        $this->predefinedPrompts = config('filament-ai.predefined_prompts', []);
        $this->selectedColumns = config('filament-ai.selected_columns');
        $this->disableModelSelection = config('filament-ai.disable_openai_selection');

        $this->configureSchema();
        $this->configureActions();
    }

    public function defaultOpenAiModel(string | Closure $defaultModel ): static
    {
        $this->defaultModel = $defaultModel;

        return $this;
    }

    public function eloquentModel(string | Closure $eloquentModel): static
    {
        $this->eloquentModel = $eloquentModel;

        return $this;
    }

    public function fieldLabel(string | Closure $fieldLabel): static
    {
        $this->fieldLabel = $fieldLabel;

        return $this;
    }

    public function fieldId(string | Closure $fieldId): static
    {
        $this->fieldId = $fieldId;

        return $this;
    }

    public function predefinedPrompts(array | Closure $predefinedPrompts): static
    {
        $this->predefinedPrompts = $predefinedPrompts;

        return $this;
    }

    public function selectedColumns(array | Closure $selectedColumns): static
    {
        $this->selectedColumns = $selectedColumns;

        return $this;
    }

    public function disableModelSelection(bool | Closure $disableModelSelection): static
    {
        $this->disableModelSelection = $disableModelSelection;

        return $this;
    }

    protected function configureSchema(): void
    {

        $this->schema([
            Forms\Components\Select::make('item')
                ->label(__('filament-ai::filament-ai.form.item'))
                ->searchable()
                ->getSearchResultsUsing(fn (string $search) => $this->eloquentModel::where($this->fieldLabel, 'like', "%{$search}%")->limit(50)->pluck($this->fieldLabel, $this->fieldId)->toArray())
                ->afterStateUpdated(function (Closure $set, $state)  {
                    $data = $this->eloquentModel::select($this->selectedColumns)->where($this->fieldId, $state)->first();
                    $set('context_data', $data->toJson(JSON_PRETTY_PRINT));
                })
                ->required(),

            Forms\Components\Select::make('openai_model')
                ->label(__('filament-ai::filament-ai.form.openai_model'))
                ->options(fn () => $this->disableModelSelection ? [$this->defaultModel => $this->defaultModel] : FilamentAi::chat()->listModels())
                ->disabled($this->disableModelSelection)
                ->default($this->defaultModel)
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
                ->required()
                ->helperText(__('filament-ai::filament-ai.form.prompt_helper_text'))
                ->columnSpanFull(),

            Forms\Components\Fieldset::make(__('filament-ai::filament-ai.form.predefined_prompts_fieldset'))
                ->hidden(empty($this->predefinedPrompts))
                ->schema([
                    Forms\Components\Actions::make($this->createPredefinedPromptActions($this->predefinedPrompts))->columnSpanFull(),
                ]),
        ]);
    }

    protected function configureActions(): void
    {
        $this->footerActions([
            Forms\Components\Actions\Action::make('generate')
                ->label(__('filament-ai::filament-ai.form.submit_prompt'))
                ->icon('heroicon-o-sparkles')
                ->action('submitPrompt'),
        ]);
    }

    protected function createPredefinedPromptActions(array $prompts): array
    {
        return array_map(fn ($prompt) => Forms\Components\Actions\Action::make($prompt['name'])
            ->label($prompt['name'])
            ->action(fn (Closure $set) => $set('prompt', $prompt['prompt'])), $prompts);
    }

    public function submitPrompt(): void
    {
        $this->state(['question' => true, 'finished' => false, 'ai_response' => '']);
        $this->generate();
    }

    public function generate(): void
    {
        $data = $this->getState();
        $data['openai_model'] = $data['openai_model'] ?? config('filament-ai.default_openai_model');
        $systemPrompt = config('filament-ai.system_prompt');

        $stream = FilamentAi::chat()->stream(
            $data['prompt'],
            $systemPrompt,
            $data['context_data'],
            $data['openai_model'],
        );

        $aiResponse = '';

        foreach ($stream as $response) {
            $content = $response->choices[0]->delta->content ?? '';
            $aiResponse .= $content;
            $this->state(['ai_response' => $aiResponse]);

            if ($content === '') {
                break;
            }
        }

        $this->state([
            'ai_response' => str($aiResponse)->markdown(),
            'finished' => true,
        ]);
    }
}
