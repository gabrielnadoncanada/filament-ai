<?php

namespace Devlense\FilamentAi\Forms\Components;

use Filament\Forms;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Illuminate\Database\Eloquent\Model;

class FilamentAiForm extends Forms\Components\Field
{
    protected string $view = 'filament-forms::components.group';

    protected array|Closure $extraFields = [];

    public function getChildComponents(): array
    {
        return [
            Select::make('type')
                ->label(__('filament-ai::filament-ai.field.name'))
                ->options($this->getTypeOptions())
                ->afterStateUpdated(fn($state, Select $component) => $this->updateAfterState($component))
                ->reactive(),
            $this->getDataGroup(),
            $this->getExtraFieldsGroup(),
        ];
    }

    protected function getTypeOptions(): array
    {
        $itemTypes = $this->getItemTypes();

        return array_combine(array_keys($itemTypes), array_column($itemTypes, 'name'));
    }

    protected function updateAfterState(Select $component): void
    {
        $component->getContainer()->getComponent(fn(Component $component) => $component instanceof Group)
            ?->getChildComponentContainer()
            ->fill();
    }

    protected function getDataGroup(): Group
    {
        return Group::make()
            ->statePath('data')
            ->whenTruthy('type')
            ->schema(fn(Get $get) => $this->getItemTypes()[$get('type')]['fields'] ?? []);
    }

    protected function getExtraFieldsGroup(): Group
    {
        return Group::make()
            ->statePath('data')
            ->visible(fn(Component $component) => !empty($component->evaluate($this->extraFields)))
            ->schema(fn(Component $component) => $this->extraFields);
    }

    public function getItemTypes(): array
    {
        return [
//            'External' => $this->buildItemType('External', TextInput::make('url')
//                ->label(__('filament.menu.attributes.url'))
//                ->required()),
//            'App\Models\Blog\Post' => $this->buildItemType('Blog post', Select::make('url')
//                ->options(BlogPost::pluck('title', 'id')->toArray())
//                ->live()
//                ->label(__('filament.menu.attributes.url'))
//                ->required()),
//            'App\Models\Service\Post' => $this->buildItemType('Service post', Select::make('url')
//                ->options(ServicePost::pluck('title', 'id')->toArray())
//                ->live()
//                ->label(__('filament.menu.attributes.url'))
//                ->required()),
//            'App\Models\Page' => $this->buildItemType('Page', Select::make('url')
//                ->options(Page::pluck('title', 'id')->toArray())
//                ->live()
//                ->label(__('filament.menu.attributes.url'))
//                ->required()),
        ];
    }

    protected function buildItemType(string $name, $urlField): array
    {
        return [
            'name' => $name,
            'fields' => $this->buildLinkFields(fn() => $urlField),
        ];
    }

    protected function buildLinkFields(callable $urlFieldFactory): array
    {
        return [
            TextInput::make('label')
                ->label(__('filament.menu.items-modal.label'))
                ->required(),
            $urlFieldFactory(),
            Select::make('target')
                ->label(__('filament.menu.attributes.target'))
                ->options([
                    '' => __('filament.menu.select-options.same-tab'),
                    '_blank' => __('filament.menu.select-options.new-tab'),
                ])
                ->default('')
                ->selectablePlaceholder(false),
        ];
    }
}
