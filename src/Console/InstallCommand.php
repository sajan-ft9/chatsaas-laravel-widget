<?php

namespace Chatsaas\LaravelWidget\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class InstallCommand extends Command
{
    protected $signature = 'chatsaas:install {--user-model= : Fully-qualified Eloquent user class}';

    protected $description = 'Publish the Chatsaas config/views and generate the required .env secrets';

    public function handle(): int
    {
        $this->call('vendor:publish', ['--tag' => 'chatsaas-config']);
        $this->call('vendor:publish', ['--tag' => 'chatsaas-views']);

        $this->ensureEnvSecret('CHATSAAS_IDENTITY_SECRET');
        $this->ensureEnvSecret('CHATSAAS_SERVICE_KEY');

        $userModel = $this->option('user-model')
            ?? $this->ask('Which Eloquent model represents your "User"?', \App\Models\User::class);

        $this->writeUserModelToConfig($userModel);

        $this->newLine();
        $this->info('Chatsaas installed. Two secrets were generated in your .env:');
        $this->line('  CHATSAAS_IDENTITY_SECRET');
        $this->line('  CHATSAAS_SERVICE_KEY');
        $this->line('Paste both into your Chatsaas dashboard under Settings → this app\'s integration.');

        $this->newLine();
        $this->comment('The assistant is denied for everyone until you define its Gate ability in your own AppServiceProvider:');
        $this->line("    Gate::define('" . config('chatsaas.gate_ability', 'use-chatsaas') . "', fn (\$user) => \$user->is_admin);");

        return self::SUCCESS;
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

    protected function writeUserModelToConfig(string $userModel): void
    {
        $configPath = config_path('chatsaas.php');

        if (!file_exists($configPath)) {
            return;
        }

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
