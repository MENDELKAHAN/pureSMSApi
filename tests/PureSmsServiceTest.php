<?php

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Application;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Request;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Puresms\Laravel\Models\SmsLog;
use Puresms\Laravel\PureSmsService;
use Puresms\Laravel\PureSmsServiceProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PureSmsServiceTest extends TestCase
{
    private Application $app;

    private Capsule $database;

    private array $options;

    private array $applicationLogs = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->app = new Application(dirname(__DIR__));
        Container::setInstance($this->app);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->app);
        $this->options = [
            'api_key' => 'test-only-secret',
            'endpoint' => 'https://sms.example.test',
            'sender' => 'TestSender',
            'webhook_secret' => 'test-only-webhook-secret',
        ];
        $this->app->instance('config', new Repository(['puresms' => $this->options]));
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
        $this->applicationLogs = [];
        $sink = function ($message, $context) {
            $this->applicationLogs[] = [$message, $context];
        };
        Log::swap(new class($sink) extends AbstractLogger
        {
            public function __construct(private Closure $sink) {}

            public function log($level, string|Stringable $message, array $context = []): void
            {
                ($this->sink)($message, $context);
            }
        });
    }

    protected function tearDown(): void
    {
        SmsLog::flushEventListeners();
        $this->database->getDatabaseManager()->disconnect();
        Facade::clearResolvedInstances();
        Container::setInstance(null);
        parent::tearDown();
    }

    public function test_single_send_uses_instance_credentials_and_config_sender_without_global_mutation(): void
    {
        Http::fake(['*' => Http::response(['id' => 'message-1'])]);
        $result = (new PureSmsService(['api_key' => 'another-test-key', 'sender' => 'Another']))
            ->sendSms('+447700900001', 'Private test body', null, 42, 7);

        $this->assertSame('sent', $result['status']);
        $this->assertSame('message-1', $result['message_id']);
        Http::assertSent(fn ($request) => $request->url() === 'https://sms.example.test/sms/send'
            && $request->hasHeader('X-API-Key', 'another-test-key')
            && $request['sender'] === 'Another' && $request['recipient'] === '+447700900001');
        Http::assertSentCount(1);
        $this->assertSame('test-only-secret', config('puresms.api_key'));
        $log = SmsLog::sole();
        $this->assertSame('+447700900001', $log->recipient);
        $this->assertSame('Another', $log->sender);
        $this->assertSame(42, $log->recipient_id);
        $this->assertSame(7, $log->sender_id);
        $this->assertSame('message-1', $log->message_id);
        $this->assertStringNotContainsString('Private test body', json_encode($this->applicationLogs));
        $this->assertStringNotContainsString('another-test-key', json_encode($this->applicationLogs));
    }

    public function test_timeouts_and_redirect_policy_apply_to_the_real_pending_request(): void
    {
        $options = null;
        Http::fake(function ($request, $requestOptions) use (&$options) {
            $options = $requestOptions;

            return Http::response(['id' => 'message-1']);
        });
        (new PureSmsService)->sendSms('+447700900001', 'Test');
        $this->assertSame(20, $options['timeout']);
        $this->assertSame(5, $options['connect_timeout']);
        $this->assertFalse($options['allow_redirects']);
    }

    public function test_provider_rejection_is_failed(): void
    {
        Http::fake(['*' => Http::response(['error' => 'private upstream error'], 401)]);
        $result = (new PureSmsService)->sendSms('+447700900001', 'Test');
        $this->assertSame('failed', $result['status']);
        $this->assertSame(401, $result['http_status']);
        $this->assertSame('failed', SmsLog::sole()->status);
        $this->assertStringNotContainsString('private upstream error', json_encode($this->applicationLogs));
    }

    public function test_ambiguous_responses_are_unknown_and_never_retried(): void
    {
        foreach ([[500, ['id' => 'maybe']], [408, []], [200, []], [200, ['id' => '']], [200, ['id' => []]], [302, []]] as [$status, $body]) {
            Http::swap(new Factory);
            Http::preventStrayRequests();
            Http::fake(['*' => Http::response($body, $status)]);
            $result = (new PureSmsService)->sendSms('+447700900001', 'Test');
            $this->assertSame('unknown', $result['status']);
            $this->assertNull($result['message_id']);
            Http::assertSentCount(1);
        }
        $this->assertSame(6, SmsLog::where('status', 'unknown')->count());
    }

    public function test_connection_error_is_unknown_and_does_not_leak_the_exception(): void
    {
        Http::fake(['*' => Http::failedConnection('private transport detail')]);
        $result = (new PureSmsService)->sendSms('+447700900001', 'Test');
        $this->assertSame('unknown', $result['status']);
        $this->assertSame('unknown', SmsLog::sole()->status);
        $this->assertStringNotContainsString('private transport detail', json_encode($result));
        $this->assertStringNotContainsString('private transport detail', json_encode($this->applicationLogs));
    }

    public function test_no_http_request_when_pre_send_log_cannot_be_created(): void
    {
        SmsLog::creating(fn () => throw new RuntimeException('database unavailable'));
        $result = (new PureSmsService)->sendSms('+447700900001', 'Test');
        $this->assertSame('failed', $result['status']);
        $this->assertSame('log_unavailable', $result['error_code']);
        Http::assertNothingSent();
    }

    public function test_accepted_send_is_not_reported_failed_when_saving_result_fails(): void
    {
        SmsLog::updating(fn () => throw new RuntimeException('database unavailable'));
        Http::fake(['*' => Http::response(['id' => 'accepted-id'])]);
        $result = (new PureSmsService)->sendSms('+447700900001', 'Test');
        $this->assertSame('sent', $result['status']);
        $this->assertSame('accepted-id', $result['message_id']);
        $this->assertFalse($result['log_saved']);
        Http::assertSentCount(1);
    }

    public function test_missing_key_or_insecure_endpoint_is_rejected_without_http(): void
    {
        foreach ([['api_key' => ''], ['endpoint' => 'http://sms.example.test'], ['endpoint' => 'https://user:pass@sms.example.test']] as $override) {
            $result = (new PureSmsService($override))->sendSms('+447700900001', 'Test');
            $this->assertSame('failed', $result['status']);
        }
        Http::assertNothingSent();
        $this->assertSame(0, SmsLog::count());
    }

    public function test_signed_callback_updates_delivery_and_delivered_cannot_regress(): void
    {
        SmsLog::create(['message_id' => 'message-1', 'content' => 'Private body', 'status' => 'sent']);
        $service = new PureSmsService;
        $response = $service->handleWebhook($this->signedRequest(['data' => ['MessageId' => 'message-1', 'DeliveryStatus' => '7']]));
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('delivered', SmsLog::sole()->status);
        $service->handleWebhook($this->signedRequest(['data' => ['MessageId' => 'message-1', 'DeliveryStatus' => 1]]));
        $this->assertSame('delivered', SmsLog::sole()->status);
        $this->assertSame([], $this->applicationLogs);
    }

    public function test_unsigned_wrongly_signed_stale_and_unconfigured_callbacks_are_rejected_before_processing(): void
    {
        $payload = ['data' => ['MessageId' => 'message-1', 'DeliveryStatus' => 7]];
        SmsLog::create(['message_id' => 'message-1', 'content' => 'Private body', 'status' => 'sent']);
        $unsigned = $this->signedRequest($payload);
        $unsigned->headers->remove('X-Webhook-Signature');
        $wrong = $this->signedRequest($payload);
        $wrong->headers->set('X-Webhook-Signature', 'wrong-signature');
        foreach ([[$unsigned, []], [$wrong, []], [$this->signedRequest($payload, time() - 600), []], [$this->signedRequest($payload), ['webhook_secret' => '']]] as [$request, $config]) {
            try {
                (new PureSmsService($config))->handleWebhook($request);
                $this->fail('Invalid callback should be rejected.');
            } catch (HttpException $e) {
                $this->assertSame(403, $e->getStatusCode());
            }
        }
        $this->assertSame('sent', SmsLog::sole()->status);
        $this->assertSame([], $this->applicationLogs);
    }

    public function test_provider_boot_registers_no_routes_until_application_opts_in(): void
    {
        Route::swap(new Router(new Dispatcher($this->app), $this->app));
        Notification::swap(new ChannelManager($this->app));
        $provider = new PureSmsServiceProvider($this->app);
        $provider->register();
        $provider->boot();
        $this->assertCount(0, Route::getRoutes());
        Route::puresmsWebhooks('signed-sms-callback');
        $this->assertCount(1, Route::getRoutes());
        $route = Route::getRoutes()->getRoutes()[0];
        $this->assertSame(['POST'], $route->methods());
        $this->assertSame('signed-sms-callback', $route->uri());
    }

    private function signedRequest(array $payload, ?int $timestamp = null): Request
    {
        $body = json_encode($payload);
        $timestamp = (string) ($timestamp ?? time());
        $request = Request::create('/puresms-webhook', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], $body);
        $request->headers->set('X-Webhook-Timestamp', $timestamp);
        $request->headers->set('X-Webhook-Signature', base64_encode(hash_hmac('sha256', $timestamp.$body, $this->options['webhook_secret'], true)));

        return $request;
    }
}
