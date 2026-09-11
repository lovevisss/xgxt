<?php

use App\Http\Middleware\EnsureCasAuthenticated;
use App\Models\User;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

it('rejects a callback without a ticket without contacting CAS', function () {
    Http::fake();
    $this->get('/auth/cas/callback')->assertStatus(401)->assertHeaderMissing('Location');
    Http::assertNothingSent();
});

it('applies guest and auth middleware to the split CAS routes', function () {
    $this->get('/auth/cas/logout')->assertRedirect(route('cas.redirect'));
    $user = User::factory()->create();
    $this->actingAs($user)->get('/auth/cas/redirect')->assertRedirect('/');
    $this->actingAs($user)->get('/auth/cas/callback?ticket=ST-unused')->assertRedirect('/');
});

beforeEach(function () {
    config([
        'app.url' => 'http://localhost',
        'cas.enabled' => true,
        'cas.server_url' => 'https://cas.paas.zufedfc.edu.cn/cas',
        'cas.backchannel_url' => 'https://cas.paas.zufedfc.edu.cn/cas',
        'cas.session_key' => 'cas_user',
        'cas.return_url_session_key' => 'cas_return_url',
        'cas.verify_ssl' => true,
    ]);
});

it('uses a separate backchannel URL without changing the public CAS login URL', function () {
    config(['cas.backchannel_url' => 'https://cas.internal.example/cas']);

    Http::fake([
        'https://cas.internal.example/cas/serviceValidate*' => Http::response(<<<'XML'
<cas:serviceResponse xmlns:cas="http://www.yale.edu/tp/cas">
  <cas:authenticationSuccess><cas:user>teacher-internal</cas:user></cas:authenticationSuccess>
</cas:serviceResponse>
XML, 200),
    ]);

    $this->get('/auth/cas/redirect?returnUrl=/students')
        ->assertRedirectContains('https://cas.paas.zufedfc.edu.cn/cas/login?service=')
        ->assertSessionHas('cas_return_url', '/students');

    $this->get('/auth/cas/callback?ticket=ST-backchannel')
        ->assertRedirect('/students');

    Http::assertSent(function ($request): bool {
        parse_str(parse_url($request->url(), PHP_URL_QUERY) ?? '', $query);

        return $request->method() === 'GET'
            && str_starts_with($request->url(), 'https://cas.internal.example/cas/serviceValidate?')
            && $request->hasHeader('Accept', 'application/xml,text/xml')
            && $query === [
                'service' => 'https://student.zufedfc.edu.cn/auth/cas/callback',
                'ticket' => 'ST-backchannel',
            ];
    });
});

it('uses the backchannel URL for CAS online detection', function () {
    config(['cas.backchannel_url' => 'https://cas.internal.example/cas']);
    $user = User::factory()->create(['cas_username' => 'teacher-online']);

    Http::fake([
        'https://cas.internal.example/cas/login/userOnlineDetect*' => Http::response([
            'data' => ['isAlive' => true],
        ]),
    ]);

    $this->actingAs($user)
        ->withSession([
            'cas_user' => [
                'user' => 'teacher-online',
                'service' => 'https://student.zufedfc.edu.cn/auth/cas/callback',
                'ticket' => 'ST-online',
            ],
        ])
        ->post('/sso/userOnlineDetect')
        ->assertOk()
        ->assertJson(['isAlive' => true]);

    Http::assertSent(function ($request): bool {
        parse_str(parse_url($request->url(), PHP_URL_QUERY) ?? '', $query);

        return $request->method() === 'POST'
            && str_starts_with($request->url(), 'https://cas.internal.example/cas/login/userOnlineDetect?')
            && $query === [
                'service' => 'https://student.zufedfc.edu.cn/auth/cas/callback',
                'ticket' => 'ST-online',
                'username' => 'teacher-online',
            ];
    });
});

it('shows a single service unavailable page when the CAS connection fails and redacts the ticket from logs', function () {
    Log::spy();
    Http::fake(Http::failedConnection('connection failed for ticket=ST-private-ticket'));

    $response = $this->get('/auth/cas/callback?returnUrl=/sync-tasks&ticket=ST-private-ticket');

    $response
        ->assertStatus(502)
        ->assertViewIs('cas-error')
        ->assertHeaderMissing('Location')
        ->assertSee('CAS 服务暂时不可用')
        ->assertSee('重新登录');

    Http::assertSentCount(1);
    Log::shouldHaveReceived('warning')->once()->withArgs(function (string $message, array $context): bool {
        return $message === 'CAS backchannel request failed.'
            && $context['operation'] === 'ticket_validation'
            && $context['endpoint'] === 'https://cas.paas.zufedfc.edu.cn/cas/serviceValidate'
            && ! str_contains(json_encode($context), 'ST-private-ticket');
    });
});

it('shows a readable CAS failure page for upstream and validation errors', function (string $body, int $upstreamStatus, int $expectedStatus, string $text) {
    Http::fake([
        '*' => Http::response($body, $upstreamStatus),
    ]);

    $this->get('/auth/cas/callback?returnUrl=/students&ticket=ST-failure')
        ->assertStatus($expectedStatus)
        ->assertViewIs('cas-error')
        ->assertHeaderMissing('Location')
        ->assertSee($text);

    Http::assertSentCount(1);
})->with([
    'HTTP error' => ['', 503, 502, 'CAS 服务响应异常'],
    'invalid XML' => ['not xml', 200, 502, 'CAS 响应无法识别'],
    'rejected ticket' => [<<<'XML'
<cas:serviceResponse xmlns:cas="http://www.yale.edu/tp/cas">
  <cas:authenticationFailure code="INVALID_TICKET">Ticket expired</cas:authenticationFailure>
</cas:serviceResponse>
XML, 200, 401, '登录验证失败'],
]);

it('registers the application CAS middleware on protected routes', function () {
    $middleware = Route::getRoutes()->getByName('students.page')?->gatherMiddleware() ?? [];

    expect(app(EnsureCasAuthenticated::class))->toBeInstanceOf(EnsureCasAuthenticated::class)
        ->and($middleware)->toContain('cas.auth');
});

it('redirects protected pages to CAS login when there is no local CAS session', function () {
    $this->get('/students')
        ->assertRedirect(route('cas.redirect', ['returnUrl' => '/students']));
});

it('redirects to the CAS server when login starts without a ticket', function () {
    $response = $this->get('/auth/cas/redirect?returnUrl=/students');

    $response->assertRedirect();
    parse_str(parse_url((string) $response->headers->get('Location'), PHP_URL_QUERY) ?? '', $query);

    expect($query['service'] ?? null)->toBe('https://student.zufedfc.edu.cn/auth/cas/callback');
    $response->assertSessionHas('cas_return_url', '/students');
});

it('validates CAS ticket and creates local authenticated session', function () {
    Http::fake([
        'https://cas.paas.zufedfc.edu.cn/cas/serviceValidate*' => Http::response(<<<'XML'
<cas:serviceResponse xmlns:cas="http://www.yale.edu/tp/cas">
  <cas:authenticationSuccess>
    <cas:user>teacher001</cas:user>
    <cas:attributes>
      <cas:name>Test Teacher</cas:name>
      <cas:organizationCode>CS</cas:organizationCode>
      <cas:organizationName>Computer School</cas:organizationName>
    </cas:attributes>
  </cas:authenticationSuccess>
</cas:serviceResponse>
XML, 200),
    ]);

    $this->get('/auth/cas/redirect?returnUrl=/students')->assertRedirect();
    $response = $this->get('/auth/cas/callback?ticket=ST-1-test');

    $response
        ->assertRedirect('/students')
        ->assertSessionHas('cas_user.user', 'teacher001')
        ->assertSessionMissing('cas_return_url');

    $recaller = app(AuthFactory::class)->guard('web')->getRecallerName();
    $response->assertCookie($recaller);
    $rememberCookie = $response->getCookie($recaller)?->getValue();

    $this->assertAuthenticated();
    $this->assertDatabaseHas('users', [
        'cas_username' => 'teacher001',
        'name' => 'Test Teacher',
        'dwbm' => 'CS',
        'dwmc' => 'Computer School',
    ]);
    $this->assertDatabaseHas('user_login_logs', [
        'cas_username' => 'teacher001',
        'name' => 'Test Teacher',
    ]);

    $this->app->make(AuthFactory::class)->forgetGuards();
    $this->flushSession();

    $this->withCookie($recaller, $rememberCookie)
        ->get('/students')
        ->assertOk()
        ->assertSessionHas('cas_user.user', 'teacher001');
});

it('logs out from Laravel session before redirecting to CAS logout', function () {
    $user = User::factory()->create(['cas_username' => 'teacher001']);

    $response = $this
        ->actingAs($user)
        ->withSession([
            'cas_user' => [
                'user' => 'teacher001',
                'service' => 'https://student.zufedfc.edu.cn/auth/cas/callback',
                'ticket' => 'ST-1-test',
            ],
        ])
        ->get('/auth/cas/logout?returnUrl=/students');

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toStartWith('https://cas.paas.zufedfc.edu.cn/cas/logout?service=');

    $this->assertGuest();
    $this->get('/students')->assertRedirect(route('cas.redirect', ['returnUrl' => '/students']));
});

it('clears local authentication when CAS single logout callback arrives', function () {
    $user = User::factory()->create(['cas_username' => 'teacher001']);

    $this
        ->actingAs($user)
        ->withSession([
            'cas_user' => [
                'user' => 'teacher001',
                'service' => 'https://student.zufedfc.edu.cn/auth/cas/callback',
                'ticket' => 'ST-1-test',
            ],
        ])
        ->post('/sso/slo')
        ->assertOk()
        ->assertJson(['success' => true]);

    $this->assertGuest();
    $this->get('/students')->assertRedirect(route('cas.redirect', ['returnUrl' => '/students']));
});

it('supports CAS single logout jsonp callbacks', function () {
    $user = User::factory()->create(['cas_username' => 'teacher001']);

    $this
        ->actingAs($user)
        ->withSession([
            'cas_user' => [
                'user' => 'teacher001',
                'service' => 'https://student.zufedfc.edu.cn/auth/cas/callback',
                'ticket' => 'ST-1-test',
            ],
        ])
        ->get('/sso/slo?callback=casLogout.done')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/javascript')
        ->assertSee('casLogout.done({"success":true});', false);

    $this->assertGuest();
});
