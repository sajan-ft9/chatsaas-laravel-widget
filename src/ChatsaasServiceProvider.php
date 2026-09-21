<?php

namespace Chatsaas\LaravelWidget;

use Chatsaas\LaravelWidget\Console\InstallCommand;
use Chatsaas\LaravelWidget\Http\Middleware\AssistantServiceAuth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class ChatsaasServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/chatsaas.php', 'chatsaas');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/chatsaas.php' => config_path('chatsaas.php'),
        ], 'chatsaas-config');

        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/chatsaas'),
        ], 'chatsaas-views');

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'chatsaas');
        $this->loadRoutesFrom(__DIR__ . '/../routes/chatsaas.php');

        $this->app['router']->aliasMiddleware('chatsaas.service', AssistantServiceAuth::class);

        // Default-deny safety net: only registered if the host hasn't defined this ability
        // themselves. A package handed to many different clients must never ship "wide open"
        // as its out-of-the-box state.
        if (!Gate::has(config('chatsaas.gate_ability'))) {
            Gate::define(config('chatsaas.gate_ability'), fn () => false);
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
            ]);
        }
    }
}
