<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'app.url' => 'http://localhost',
        'cas.enabled' => true,
        'cas.server_url' => 'https://cas.paas.zufedfc.edu.cn/cas',
        'cas.session_key' => 'cas_user',
    ]);
});

it('redirects protected pages to CAS login when there is no local CAS session', function () {
    $this->get('/students')
        ->assertRedirect(route('cas.login', ['returnUrl' => '/students']));
});

it('redirects to the CAS server when login starts without a ticket', function () {
    $response = $this->get('/sso/login?returnUrl=/students');

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toStartWith('https://cas.paas.zufedfc.edu.cn/cas/login?service=');
});

it('validates CAS ticket and creates local authenticated session', function () {
    Http::fake([
        'https://cas.paas.zufedfc.edu.cn/cas/serviceValidate*' => Http::response(<<<'XML'
<cas:serviceResponse xmlns:cas="http://www.yale.edu/tp/cas">
  <cas:authenticationSuccess>
    <cas:user>teacher001</cas:user>
    <cas:attributes>
      <cas:name>Test Teacher</cas:name>
      <cas:dwbm>CS</cas:dwbm>
      <cas:dwmc>Computer School</cas:dwmc>
    </cas:attributes>
  </cas:authenticationSuccess>
</cas:serviceResponse>
XML, 200),
    ]);

    $this->get('/sso/login?returnUrl=/students&ticket=ST-1-test')
        ->assertRedirect('/students')
        ->assertSessionHas('cas_user.user', 'teacher001');

    $this->assertAuthenticated();
    $this->assertDatabaseHas('users', [
        'cas_username' => 'teacher001',
        'name' => 'Test Teacher',
        'dwbm' => 'CS',
        'dwmc' => 'Computer School',
    ]);
});

it('logs out from Laravel session before redirecting to CAS logout', function () {
    $user = User::factory()->create(['cas_username' => 'teacher001']);

    $response = $this
        ->actingAs($user)
        ->withSession([
            'cas_user' => [
                'user' => 'teacher001',
                'service' => 'http://localhost/sso/login?returnUrl=%2Fstudents',
                'ticket' => 'ST-1-test',
            ],
        ])
        ->get('/sso/logout?returnUrl=/students');

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toStartWith('https://cas.paas.zufedfc.edu.cn/cas/logout?service=');

    $this->assertGuest();
    $this->get('/students')->assertRedirect(route('cas.login', ['returnUrl' => '/students']));
});

it('clears local authentication when CAS single logout callback arrives', function () {
    $user = User::factory()->create(['cas_username' => 'teacher001']);

    $this
        ->actingAs($user)
        ->withSession([
            'cas_user' => [
                'user' => 'teacher001',
                'service' => 'http://localhost/sso/login?returnUrl=%2Fstudents',
                'ticket' => 'ST-1-test',
            ],
        ])
        ->post('/sso/slo')
        ->assertOk()
        ->assertJson(['success' => true]);

    $this->assertGuest();
    $this->get('/students')->assertRedirect(route('cas.login', ['returnUrl' => '/students']));
});

it('supports CAS single logout jsonp callbacks', function () {
    $user = User::factory()->create(['cas_username' => 'teacher001']);

    $this
        ->actingAs($user)
        ->withSession([
            'cas_user' => [
                'user' => 'teacher001',
                'service' => 'http://localhost/sso/login?returnUrl=%2Fstudents',
                'ticket' => 'ST-1-test',
            ],
        ])
        ->get('/sso/slo?callback=casLogout.done')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/javascript')
        ->assertSee('casLogout.done({"success":true});', false);

    $this->assertGuest();
});
