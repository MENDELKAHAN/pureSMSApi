<?php

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Http\Client\Factory;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Puresms\Laravel\Facades\PureSms;
use Puresms\Laravel\Models\SmsLog;
use Puresms\Laravel\PureSmsService;
use Puresms\Laravel\PureSmsServiceProvider;

class PureSmsServiceTest extends TestCase
{
    private Application $app;

    private Capsule $database;

    private array $originalEnvironment = [];

    protected function setUp(): void
    {
        parent::setUp();
        // No application bootstrap or .env is loaded. These are synthetic values.
        foreach ([
            'PURESMS_API_KEY' => 'fixture-only-env-key',
            'PURESMS_ENDPOINT' => 'https://sms.example.test',
            'PURESMS_SENDER' => 'EnvSender',
            'PURESMS_NUMBER_MODEL' => 'App\\Models\\User',
            'PURESMS_MOBILE_NUMBER' => 'sms_number',
        ] as $name => $value) {
            $this->originalEnvironment[$name] = [getenv($name), $_ENV[$name] ?? null, $_SERVER[$name] ?? null];
            putenv($name.'='.$value);
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }

        $this->app = new Application(dirname(__DIR__));
        Container::setInstance($this->app);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->app);
        $this->app->instance('config', new Repository);
        $this->app->instance('files', new Filesystem);
        (new PureSmsServiceProvider($this->app))->register();
        $this->database = new Capsule($this->app);
        $this->database->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $this->database->setEventDispatcher(new Dispatcher($this->app));
        $this->database->setAsGlobal();
        $this->database->bootEloquent();
        $this->app->instance('db', $this->database->getDatabaseManager());
        $this->app->bind('db.schema', fn () => $this->database->schema());
        (require dirname(__DIR__).'/database/migrations/2025_03_03_000000_create_sms_logs_table.php')->up();
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Log::swap(new NullLogger);
    }

    protected function tearDown(): void
    {
        $this->database->getDatabaseManager()->disconnect();
        Facade::clearResolvedInstances();
        Container::setInstance(null);
        foreach ($this->originalEnvironment as $name => [$processValue, $envValue, $serverValue]) {
            putenv($processValue === false ? $name : $name.'='.$processValue);
            if ($envValue === null) {
                unset($_ENV[$name]);
            } else {
                $_ENV[$name] = $envValue;
            }
            if ($serverValue === null) {
                unset($_SERVER[$name]);
            } else {
                $_SERVER[$name] = $serverValue;
            }
        }
        parent::tearDown();
    }

    public function test_original_env_config_and_facade_send_work_on_laravel_13(): void
    {
        $this->assertStringStartsWith('13.', Application::VERSION);
        $this->assertSame('fixture-only-env-key', config('puresms.api_key'));
        $this->assertSame('https://sms.example.test', config('puresms.endpoint'));
        $this->assertSame(0, (new ReflectionMethod(PureSmsService::class, '__construct'))->getNumberOfParameters());
        Http::fake(['*' => Http::response(['id' => 'fixture-message-1'])]);

        $result = PureSms::sendSms('+447700900001', 'Synthetic test message');

        $this->assertSame('sent', $result['status']);
        $this->assertSame('fixture-message-1', $result['message_id']);
        $this->assertSame(['id' => 'fixture-message-1'], $result['body']);
        $this->assertArrayHasKey('headers', $result);
        Http::assertSent(fn ($request) => $request->url() === 'https://sms.example.test/sms/send'
            && $request->hasHeader('X-API-Key', 'fixture-only-env-key')
            && $request['sender'] === 'EnvSender'
            && $request['recipient'] === '+447700900001');
        Http::assertSentCount(1);
        $this->assertSame('sent', SmsLog::sole()->status);
    }

    public function test_original_five_argument_send_signature_and_failure_result_are_preserved(): void
    {
        Http::fake(['*' => Http::response(['error' => 'Synthetic rejection'], 401)]);

        $result = (new PureSmsService)->sendSms('+447700900001', 'Synthetic test message', 'Explicit', 42, 7);

        $this->assertSame(['error' => true, 'message' => 'API call failed', 'status' => 401], $result);
        Http::assertSent(fn ($request) => $request['sender'] === 'Explicit');
        Http::assertSentCount(1);
        $log = SmsLog::sole();
        $this->assertSame('failed', $log->status);
        $this->assertSame(42, $log->recipient_id);
        $this->assertSame(7, $log->sender_id);
        $this->assertSame('401', $log->error_code);
    }

    public function test_original_provider_routes_and_webhook_macro_are_preserved(): void
    {
        Route::swap(new Router(new Dispatcher($this->app), $this->app));
        Notification::swap(new ChannelManager($this->app));
        (new PureSmsServiceProvider($this->app))->boot();

        $routes = Route::getRoutes()->getRoutes();
        $this->assertCount(2, $routes);
        $this->assertSame('send-sms', $routes[0]->uri());
        $this->assertSame(['GET', 'HEAD'], $routes[0]->methods());
        $this->assertSame('puresms-webhook', $routes[1]->uri());
        $this->assertSame(['POST'], $routes[1]->methods());
        Route::puresmsWebhooks('custom-callback');
        $this->assertCount(3, Route::getRoutes());
        $this->assertSame('custom-callback', Route::getRoutes()->getRoutes()[2]->uri());
        Http::assertNothingSent();
    }
}
