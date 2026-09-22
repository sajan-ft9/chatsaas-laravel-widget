<?php

namespace Chatsaas\LaravelWidget\Console;

use Composer\InstalledVersions;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class InstallCommand extends Command
{
    protected $signature = 'chatsaas:install
        {--user-model= : Fully-qualified Eloquent user class, e.g. App\\Models\\User}
        {--allow-by-default : Allow every authenticated user until you configure real access control}';

    protected $description = 'Publish the Chatsaas config/views and generate the required .env secrets';

    public function handle(): int
    {
        $this->call('vendor:publish', ['--tag' => 'chatsaas-config']);
        $this->call('vendor:publish', ['--tag' => 'chatsaas-views']);

        $this->ensureEnvSecret('CHATSAAS_IDENTITY_SECRET');
        $this->ensureEnvSecret('CHATSAAS_SERVICE_KEY');

        $userModel = $this->option('user-model')
            ?? $this->ask(
                'Enter the fully-qualified class name of your Eloquent User model (e.g. App\\Models\\User), so the exact import can be resolved',
                \App\Models\User::class
            );

        $this->writeUserModelToConfig($userModel);
        $this->configureDefaultAccess();

        $this->newLine();
        $this->info('Chatsaas installed. Two secrets were generated in your .env:');
        $this->line('  CHATSAAS_IDENTITY_SECRET');
        $this->line('  CHATSAAS_SERVICE_KEY');
        $this->line('Paste both into your Chatsaas dashboard under Settings → this app\'s integration.');

        return self::SUCCESS;
    }

    /**
     * The package denies everyone until a Gate is defined for `chatsaas.gate_ability`. If the
     * host has no permission package at all, ask (once, interactively) whether to allow every
     * authenticated user instead of leaving the assistant unusable until they wire up access
     * control themselves. Never flips this on silently or non-interactively (e.g. CI) — a
     * client we can't see into must never end up wide-open without asking for it explicitly.
     */
    protected function configureDefaultAccess(): void
    {
        if ($this->option('allow-by-default')) {
            $this->setEnvValue('CHATSAAS_ALLOW_BY_DEFAULT', 'true');
            $this->comment('CHATSAAS_ALLOW_BY_DEFAULT=true — every authenticated user can use the assistant until you configure something stricter.');

            return;
        }

        if (!$this->input->isInteractive()) {
            $this->printGateReminder();

            return;
        }

        if (class_exists(InstalledVersions::class) && InstalledVersions::isInstalled('spatie/laravel-permission')) {
            $this->printGateReminder();

            return;
        }

        $this->newLine();
        $this->warn('No permission package (e.g. spatie/laravel-permission) detected.');

        if ($this->confirm('Allow every authenticated user to use the assistant by default, until you configure something stricter?', false)) {
            $this->setEnvValue('CHATSAAS_ALLOW_BY_DEFAULT', 'true');
            $this->comment('Set CHATSAAS_ALLOW_BY_DEFAULT=true in .env. Change this once you have real access control.');

            return;
        }

        $this->printGateReminder();
    }

    protected function printGateReminder(): void
    {
        $this->newLine();
        $this->comment('The assistant is denied for everyone until you define its Gate ability in your own AppServiceProvider:');
        $this->line("    Gate::define('" . config('chatsaas.gate_ability', 'use-chatsaas') . "', fn (\$user) => \$user->is_admin);");
    }

    protected function ensureEnvSecret(string $key): void
    {
        $envPath = base_path('.env');

        if (!file_exists($envPath)) {
            return;
        }

        $contents = file_get_contents($envPath);

        if (preg_match('/^' . preg_quote($key, '/') . '=/m', $contents)) {
            return;
        }

        file_put_contents($envPath, rtrim($contents) . "\n{$key}=" . Str::random(32) . "\n");
    }

    protected function setEnvValue(string $key, string $value): void
    {
        $envPath = base_path('.env');

        if (!file_exists($envPath)) {
            return;
        }

        $contents = file_get_contents($envPath);

        if (preg_match('/^' . preg_quote($key, '/') . '=/m', $contents)) {
            $contents = preg_replace('/^' . preg_quote($key, '/') . '=.*/m', "{$key}={$value}", $contents);
        } else {
            $contents = rtrim($contents) . "\n{$key}={$value}\n";
        }

        file_put_contents($envPath, $contents);
    }

    protected function writeUserModelToConfig(string $userModel): void
    {
        $configPath = config_path('chatsaas.php');

        if (!file_exists($configPath)) {
            return;
        }

        $userModel = ltrim($userModel, '\\');

        $contents = file_get_contents($configPath);
        $contents = preg_replace(
            "/'user_model'\\s*=>\\s*.*?,/",
            "'user_model' => \\{$userModel}::class,",
            $contents,
            1
        );

        file_put_contents($configPath, $contents);
    }
}
