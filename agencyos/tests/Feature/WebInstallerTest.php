<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\User;
use App\Services\WebInstaller;
use App\Providers\AppServiceProvider;
use Illuminate\Cache\RateLimiter as CacheRateLimiter;
use Illuminate\Support\Facades\RateLimiter;
use Dotenv\Dotenv;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Mockery\MockInterface;
use Tests\TestCase;

class WebInstallerTest extends TestCase
{
    private string $temporary;

    private string $token = 'private-test-setup-key';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->temporary = storage_path('framework/testing/installer-'.bin2hex(random_bytes(6)));
        File::ensureDirectoryExists($this->temporary);
        File::put($this->temporary.'/database.sqlite', '');
        File::put($this->temporary.'/setup.json', json_encode(['token_hash' => hash('sha256', $this->token), 'key' => config('app.key')]));
        app()->useEnvironmentPath($this->temporary)->loadEnvironmentFrom('.env');
        config(['agencyos.installation_directory' => $this->temporary, 'database.default' => 'installer_test', 'database.connections.installer_test' => ['driver' => 'sqlite', 'database' => $this->temporary.'/database.sqlite', 'foreign_key_constraints' => true]]);
        $this->partialMock(WebInstaller::class, function (MockInterface $mock): void {
            $mock->shouldReceive('connect')->andReturnNull();
            $mock->shouldReceive('requirements')->andReturn(['Test server ready' => true]);
        });
    }

    protected function tearDown(): void
    {
        DB::purge('installer_test');
        if (isset($this->temporary) && str_starts_with(realpath($this->temporary), realpath(storage_path('framework/testing')).DIRECTORY_SEPARATOR)) {
            File::deleteDirectory($this->temporary);
        }
        parent::tearDown();
    }

    private function database(): array
    {
        return ['setup_token' => $this->token, 'app_url' => 'https://agency.example.com', 'db_host' => 'localhost', 'db_port' => 3306, 'db_database' => 'agencyos', 'db_username' => 'agencyos', 'db_password' => 'Private$DB"password\\with#symbols'];
    }

    private function account(): array
    {
        return ['name' => 'Agency Owner', 'agency_name' => 'A $pecial "Agency"', 'email' => 'owner@example.com', 'password' => 'LongStrongPassword123', 'password_confirmation' => 'LongStrongPassword123', 'timezone' => 'Asia/Kolkata', 'currency' => 'INR', 'support_email' => 'support@example.com', 'address' => "42 Business Road\nMumbai"];
    }

    public function test_two_step_install_creates_real_schema_owner_and_environment_then_locks_setup(): void
    {
        $this->get('/')->assertRedirect('/install');
        $this->get('/install')->assertOk()->assertSee('Step 1 of 2');
        $this->post('/install/database', $this->database())->assertRedirect(route('install.show'))->assertSessionHasNoErrors();
        $this->assertFileDoesNotExist($this->temporary.'/.env');
        $this->get('/install')->assertOk()->assertSee('Step 2 of 2')->assertDontSee($this->database()['db_password']);
        $this->post('/install/finish', $this->account())->assertRedirect('/login?installed=1')->assertSessionHasNoErrors();
        $user = User::firstOrFail();
        $this->assertTrue($user->is_super_admin);
        $this->assertTrue(Hash::check($this->account()['password'], $user->password));
        $this->assertSame(Agency::firstOrFail()->id, $user->agencies()->first()->id);
        $this->assertDatabaseHas('roles', ['name' => 'agency_owner']);
        $environment = Dotenv::parse(File::get($this->temporary.'/.env'));
        $this->assertSame($this->database()['db_password'], $environment['DB_PASSWORD']);
        $this->assertSame($this->account()['agency_name'], $environment['APP_NAME']);
        $this->assertSame('https://agency.example.com', $environment['APP_URL']);
        $this->assertSame('production', $environment['APP_ENV']);
        $this->assertNotSame(config('app.key'), $environment['APP_KEY']);
        $this->assertStringNotContainsString($this->account()['password'], File::get($this->temporary.'/.env'));
        $this->assertFileExists($this->temporary.'/installed.lock');
        $this->assertFileDoesNotExist($this->temporary.'/setup.json');
        $this->get('/install')->assertNotFound();
        $this->post('/install/finish', $this->account())->assertNotFound();
        $this->assertSame(1, User::count());
    }

    public function test_setup_requires_a_private_key_and_does_not_flash_credentials(): void
    {
        $this->post('/install/finish', $this->account())->assertForbidden();
        $this->post('/install/database', array_replace($this->database(), ['setup_token' => 'wrong']))->assertSessionHasErrors('setup_token');
        $this->assertNull(session('_old_input.setup_token'));
        $this->assertNull(session('_old_input.db_password'));
        $this->assertNull(session('installation.database'));
    }

    public function test_setup_throttling_works_before_a_database_or_environment_exists(): void
    {
        config(['cache.default' => 'database', 'database.connections.unconfigured' => ['driver' => 'sqlite', 'database' => $this->temporary.'/does-not-exist.sqlite'], 'database.default' => 'unconfigured']);
        app()->forgetInstance(CacheRateLimiter::class);
        RateLimiter::clearResolvedInstance(CacheRateLimiter::class);
        app(AppServiceProvider::class)->boot();
        $this->assertSame('file', config('cache.limiter'));
        $this->post('/install/database', array_replace($this->database(), ['setup_token' => 'wrong']))->assertSessionHasErrors('setup_token');
        $this->assertFileDoesNotExist($this->temporary.'/.env');
    }

    public function test_existing_database_and_accounts_cannot_be_overwritten(): void
    {
        Schema::create('existing_business_records', function (Blueprint $table): void {
            $table->id();
        });
        $this->post('/install/database', $this->database())->assertSessionHasErrors('db_database');
        $this->assertTrue(Schema::hasTable('existing_business_records'));
        $this->assertFileDoesNotExist($this->temporary.'/.env');
    }

    public function test_domain_and_environment_line_injection_are_rejected(): void
    {
        $this->post('/install/database', array_replace($this->database(), ['app_url' => 'http://example.com', 'db_password' => "secret\nAPP_DEBUG=true"]))->assertSessionHasErrors(['app_url', 'db_password']);
        $this->post('/install/database', array_replace($this->database(), ['app_url' => 'https://example.com/subfolder']))->assertSessionHasErrors('app_url');
        $this->post('/install/database', $this->database())->assertSessionHasNoErrors();
        $this->post('/install/finish', array_replace($this->account(), ['agency_name' => "Agency\nAPP_DEBUG=true", 'password' => 'short', 'mail_password' => "secret\nAPP_DEBUG=true"]))->assertSessionHasErrors(['agency_name', 'password', 'mail_password']);
        $this->assertNull(session('_old_input.mail_password'));
        $this->assertNull(session('_old_input.password'));
    }

    public function test_disabled_and_completed_installers_are_inaccessible(): void
    {
        File::put($this->temporary.'/installed.lock', 'installed');
        $this->get('/install')->assertNotFound();
        $this->post('/install/database', $this->database())->assertNotFound();
    }

    public function test_installation_requires_https_in_production(): void
    {
        app()->instance('env', 'production');
        $this->get('http://example.com/install')->assertForbidden();
        $this->get('https://example.com/install')->assertOk();
    }

    public function test_concurrent_installation_is_rejected_without_changing_the_database(): void
    {
        $this->post('/install/database', $this->database())->assertSessionHasNoErrors();
        $lock = fopen($this->temporary.'/install.mutex', 'c');
        flock($lock, LOCK_EX);
        try {
            $this->post('/install/finish', $this->account())->assertSessionHasErrors('installation');
            $this->assertFalse(Schema::hasTable('users'));
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function test_failed_account_write_rolls_back_and_can_resume_owned_migrations(): void
    {
        $installer = app(WebInstaller::class);
        $installer->shouldReceive('writeEnvironment')->once()->andThrow(new \RuntimeException('Simulated disk failure'));
        $this->post('/install/database', $this->database())->assertSessionHasNoErrors();
        $this->post('/install/finish', $this->account())->assertSessionHasErrors('installation');
        $this->assertDatabaseCount('users', 0);
        $this->assertTrue($installer->enabled());
        $installer->shouldReceive('writeEnvironment')->passthru();
        $this->post('/install/finish', $this->account())->assertRedirect('/login?installed=1')->assertSessionHasNoErrors();
        $this->assertDatabaseCount('users', 1);
    }
}
