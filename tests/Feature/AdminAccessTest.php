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

it('forbids non-admin users from protected pages', function () {
    User::factory()->create([
        'cas_username' => 'staff001',
        'role' => User::ROLE_STAFF,
    ]);

    $this->withSession([
        'cas_user' => ['user' => 'staff001'],
    ])->get('/students')
        ->assertForbidden()
        ->assertSee('权限不足');
});

it('allows admin users to access protected pages', function () {
    User::factory()->create([
        'cas_username' => 'admin001',
        'role' => User::ROLE_ADMIN,
    ]);

    $this->withSession([
        'cas_user' => ['user' => 'admin001'],
    ])->get('/students')
        ->assertOk()
        ->assertSee('data-page="students"', false);
});

it('allows only super admins to manage user roles', function () {
    $superAdmin = User::factory()->create([
        'cas_username' => 'root001',
        'role' => User::ROLE_SUPER_ADMIN,
    ]);

    $normalAdmin = User::factory()->create([
        'cas_username' => 'admin002',
        'role' => User::ROLE_ADMIN,
    ]);

    $targetUser = User::factory()->create([
        'cas_username' => 'staff002',
        'role' => User::ROLE_STAFF,
    ]);

    $this->withSession([
        'cas_user' => ['user' => $normalAdmin->cas_username],
    ])->get('/admin/users')->assertForbidden();

    $this->withSession([
        'cas_user' => ['user' => $superAdmin->cas_username],
    ])->putJson("/admin/users/{$targetUser->id}/role", [
        'role' => User::ROLE_ADMIN,
    ])->assertOk();

    $this->assertDatabaseHas('users', [
        'id' => $targetUser->id,
        'role' => User::ROLE_ADMIN,
    ]);
});

it('sets first CAS-synced user as super admin', function () {
    Http::fake([
        'https://cas.paas.zufedfc.edu.cn/cas/serviceValidate*' => Http::response(<<<'XML'
<cas:serviceResponse xmlns:cas="http://www.yale.edu/tp/cas">
  <cas:authenticationSuccess>
    <cas:user>teacher-first</cas:user>
    <cas:attributes>
      <cas:name>First Teacher</cas:name>
    </cas:attributes>
  </cas:authenticationSuccess>
</cas:serviceResponse>
XML, 200),
    ]);

    $this->get('/sso/login?returnUrl=/students&ticket=ST-1-first')
        ->assertRedirect('/students')
        ->assertSessionHas('cas_user.user', 'teacher-first');

    $this->assertDatabaseHas('users', [
        'cas_username' => 'teacher-first',
        'role' => User::ROLE_SUPER_ADMIN,
    ]);
});

