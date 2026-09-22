<?php

namespace Chatsaas\LaravelWidget\Tests\Feature;

use Chatsaas\LaravelWidget\Tests\TestCase;

class InstallCommandTest extends TestCase
{
    public function test_it_writes_secrets_to_env_and_is_idempotent_on_a_second_run(): void
    {
        $envPath = base_path('.env');
        file_put_contents($envPath, "APP_NAME=Testbench\n");

        $this->artisan('chatsaas:install', ['--user-model' => 'App\\Models\\User', '--no-interaction' => true])
            ->assertExitCode(0);

        $firstRun = file_get_contents($envPath);
        $this->assertSame(1, substr_count($firstRun, 'CHATSAAS_IDENTITY_SECRET='));
        $this->assertSame(1, substr_count($firstRun, 'CHATSAAS_SERVICE_KEY='));

        $this->artisan('chatsaas:install', ['--user-model' => 'App\\Models\\User', '--no-interaction' => true])
            ->assertExitCode(0);

        $secondRun = file_get_contents($envPath);
        $this->assertSame(1, substr_count($secondRun, 'CHATSAAS_IDENTITY_SECRET='));
        $this->assertSame(1, substr_count($secondRun, 'CHATSAAS_SERVICE_KEY='));

        // Idempotent: the secret VALUES generated on the first run must survive the second.
        preg_match('/CHATSAAS_IDENTITY_SECRET=(\S+)/', $firstRun, $first);
        preg_match('/CHATSAAS_IDENTITY_SECRET=(\S+)/', $secondRun, $second);
        $this->assertSame($first[1], $second[1]);

        unlink($envPath);
    }

    public function test_it_leaves_allow_by_default_unset_unless_the_flag_is_passed(): void
    {
        $envPath = base_path('.env');
        file_put_contents($envPath, "APP_NAME=Testbench\n");

        $this->artisan('chatsaas:install', ['--user-model' => 'App\\Models\\User', '--no-interaction' => true])
            ->assertExitCode(0);

        $this->assertStringNotContainsString('CHATSAAS_ALLOW_BY_DEFAULT', file_get_contents($envPath));

        unlink($envPath);
    }

    public function test_the_allow_by_default_flag_writes_it_true(): void
    {
        $envPath = base_path('.env');
        file_put_contents($envPath, "APP_NAME=Testbench\n");

        $this->artisan('chatsaas:install', [
            '--user-model' => 'App\\Models\\User',
            '--allow-by-default' => true,
        ])->assertExitCode(0);

        $this->assertStringContainsString('CHATSAAS_ALLOW_BY_DEFAULT=true', file_get_contents($envPath));

        unlink($envPath);
    }

    public function test_interactively_opting_in_writes_allow_by_default_true(): void
    {
        $envPath = base_path('.env');
        file_put_contents($envPath, "APP_NAME=Testbench\n");

        $this->artisan('chatsaas:install', ['--user-model' => 'App\\Models\\User'])
            ->expectsConfirmation(
                'Allow every authenticated user to use the assistant by default, until you configure something stricter?',
                'yes'
            )
            ->assertExitCode(0);

        $this->assertStringContainsString('CHATSAAS_ALLOW_BY_DEFAULT=true', file_get_contents($envPath));

        unlink($envPath);
    }

    public function test_declining_the_interactive_prompt_leaves_it_denied(): void
    {
        $envPath = base_path('.env');
        file_put_contents($envPath, "APP_NAME=Testbench\n");

        $this->artisan('chatsaas:install', ['--user-model' => 'App\\Models\\User'])
            ->expectsConfirmation(
                'Allow every authenticated user to use the assistant by default, until you configure something stricter?',
                'no'
            )
            ->assertExitCode(0);

        $this->assertStringNotContainsString('CHATSAAS_ALLOW_BY_DEFAULT', file_get_contents($envPath));

        unlink($envPath);
    }

    public function test_the_user_model_option_strips_a_leading_backslash(): void
    {
        $this->artisan('chatsaas:install', ['--user-model' => '\\App\\Models\\User', '--no-interaction' => true])
            ->assertExitCode(0);

        $this->assertStringContainsString(
            "'user_model' => \\App\\Models\\User::class,",
            file_get_contents(config_path('chatsaas.php'))
        );
    }
}
