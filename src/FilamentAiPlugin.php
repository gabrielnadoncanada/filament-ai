<?php

namespace Devlense\FilamentAi;

use Filament\Contracts\Plugin;
use Filament\Panel;

class FilamentAiPlugin implements Plugin
{
    public function boot(Panel $panel): void
    {
    }

    public function getId(): string
    {
        return 'filament-ai';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([
            \Devlense\FilamentAi\Filament\Pages\FilamentAiPage::class,
        ]);
    }

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }
}
