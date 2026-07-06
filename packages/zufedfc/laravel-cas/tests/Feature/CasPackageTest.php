<?php

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Zufedfc\LaravelCas\Events\CasAuthenticated;
use Zufedfc\LaravelCas\Events\CasLoggedOut;
use Zufedfc\LaravelCas\Tests\TestCase as PackageTestCase;
use Zufedfc\LaravelCas\Tests\TestUser;

uses(PackageTestCase::class);

it('registers package routes and middleware', function () {
    expect(Route::has('cas.login'))->toBeTrue()
        ->and(Route::has('cas.logout'))->toBeTrue()
        ->and(Route::has('cas.slo'))->toBeTrue()
        ->and(Route::has('cas.user-online-detect'))->toBeTrue();
    expect(app(VerifyCsrfToken::class)->getExcludedPaths())->toContain('sso/slo');

    $this->get('/sso/login?returnUrl=/dashboard')
        ->assertRedirect();

    Route::middleware(['web', 'cas.auth'])->get('/protected-by-cas', fn () => 'ok');
    $this->withSession(['cas_user' => casSession()])
        ->get('/protected-by-cas')
        ->assertOk()
        ->assertSee('ok');
});

it('validates a ticket, syncs the default user, logs in and dispatches an event', function () {
    Event::fake([CasAuthenticated::class]);
    Http::fake([
        'https://cas.example.edu/cas/serviceValidate*' => Http::response(casSuccessXml(), 200),
    ]);

    $this->get('/sso/login?returnUrl=/dashboard&ticket=ST-1-test')
        ->assertRedirect('/dashboard')
        ->assertSessionHas('cas_user.user', 'teacher001');

    $this->assertAuthenticated();
    $this->assertDatabaseHas('users', [
        'cas_username' => 'teacher001',
        'name' => 'Test Teacher',
        'email' => 'teacher001@cas.local',
    ]);
    Event::assertDispatched(CasAuthenticated::class);
});

it('returns safely after failed validation and rejects external return URLs', function () {
    Http::fake([
        'https://cas.example.edu/cas/serviceValidate*' => Http::response(<<<'XML'
<cas:serviceResponse xmlns:cas="http://www.yale.edu/tp/cas">
  <cas:authenticationFailure code="INVALID_TICKET">Ticket expired</cas:authenticationFailure>
</cas:serviceResponse>
XML, 200),
    ]);

    $this->get('/sso/login?returnUrl=//evil.example&ticket=ST-expired')
        ->assertRedirect('/')
        ->assertSessionHas('cas_error', 'Ticket expired');

    $response = $this->get('/sso/login?returnUrl=%2F%5Cevil.example');
    $response->assertRedirect();
    expect(urldecode((string) $response->headers->get('Location')))->not->toContain('evil.example');
});

it('clears both guard and CAS session before global logout', function () {
    Event::fake([CasLoggedOut::class]);
    $user = TestUser::query()->create([
        'cas_username' => 'teacher001',
        'name' => 'Test Teacher',
        'email' => 'teacher001@cas.local',
        'password' => bcrypt('secret'),
    ]);

    $response = $this->actingAs($user)
        ->withSession(['cas_user' => casSession()])
        ->get('/sso/logout?returnUrl=/dashboard');

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toStartWith('https://cas.example.edu/cas/logout?service=');
    $this->assertGuest();
    Event::assertDispatched(CasLoggedOut::class);
});

it('supports post and jsonp single logout callbacks', function () {
    $user = TestUser::query()->create([
        'cas_username' => 'teacher001',
        'name' => 'Test Teacher',
        'email' => 'teacher001@cas.local',
        'password' => bcrypt('secret'),
    ]);

    $this->actingAs($user)
        ->withSession(['cas_user' => casSession()])
        ->post('/sso/slo')
        ->assertOk()
        ->assertJson(['success' => true]);

    $this->actingAs($user)
        ->withSession(['cas_user' => casSession()])
        ->get('/sso/slo?callback=casLogout.done')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/javascript')
        ->assertSee('casLogout.done({"success":true});', false);
});

it('logs out when the CAS online check reports an expired session', function () {
    Http::fake([
        'https://cas.example.edu/cas/login/userOnlineDetect' => Http::response([
            'code' => -1,
            'message' => 'expired',
        ]),
    ]);

    $user = TestUser::query()->create([
        'cas_username' => 'teacher001',
        'name' => 'Test Teacher',
        'email' => 'teacher001@cas.local',
        'password' => bcrypt('secret'),
    ]);

    $this->actingAs($user)
        ->withSession(['cas_user' => casSession()])
        ->post('/sso/userOnlineDetect')
        ->assertOk()
        ->assertJson(['isAlive' => false]);

    $this->assertGuest();
});

function casSuccessXml(): string
{
    return <<<'XML'
<cas:serviceResponse xmlns:cas="http://www.yale.edu/tp/cas">
  <cas:authenticationSuccess>
    <cas:user>teacher001</cas:user>
    <cas:attributes>
      <cas:name>Test Teacher</cas:name>
      <cas:memberOf>staff</cas:memberOf>
      <cas:memberOf>teacher</cas:memberOf>
    </cas:attributes>
  </cas:authenticationSuccess>
</cas:serviceResponse>
XML;
}

function casSession(): array
{
    return [
        'service' => 'http://localhost/sso/login?returnUrl=%2Fdashboard',
        'ticket' => 'ST-1-test',
        'user' => 'teacher001',
        'attributes' => [],
    ];
}
