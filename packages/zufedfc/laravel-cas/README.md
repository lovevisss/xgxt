# Laravel CAS

Internal CAS authentication package for Laravel 12 and PHP 8.2+.

## Install

Add the private GitLab repository to the application:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://gitlab.example.edu/zufedfc/laravel-cas.git"
        }
    ]
}
```

Then install and configure:

```bash
composer require zufedfc/laravel-cas
php artisan cas:install
```

```dotenv
CAS_ENABLED=true
CAS_SERVER_URL=https://cas.paas.zufedfc.edu.cn/cas
CAS_SESSION_KEY=cas_user
CAS_HTTP_TIMEOUT=10
```

Protect application routes with the middleware:

```php
Route::middleware('cas.auth')->group(function (): void {
    // Protected routes...
});
```

## Custom user resolver

Implement `Zufedfc\LaravelCas\Contracts\CasUserResolver` and configure its class:

```php
'user' => [
    'resolver' => App\Auth\CasUserResolver::class,
],
```

Listen for `CasAuthenticated` and `CasLoggedOut` to add application-specific logging or auditing.

## CAS platform URLs

- Login callback: `https://your-app.example/sso/login`
- Logout callback: `https://your-app.example/sso/logout`
- Single logout URL: `https://your-app.example/sso/slo`
