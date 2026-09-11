<?php

namespace App\Http\Controllers;

use App\Auth\CasClient;
use App\Contracts\CasUserResolver;
use App\Data\CasValidationResult;
use App\Events\CasAuthenticated;
use App\Events\CasLoggedOut;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CasAuthController extends Controller
{
    public function __construct(
        private readonly CasClient $client,
        private readonly CasUserResolver $users,
        private readonly AuthFactory $auth,
    ) {
    }

    public function redirect(Request $request): Response
    {
        $this->ensureEnabled();
        $returnUrl = $this->safeReturnUrl((string) $request->query('returnUrl', '/'));
        $request->session()->put($this->returnUrlSessionKey(), $returnUrl);

        return redirect()->away($this->client->loginUrl(route($this->routeName('callback'))));
    }

    public function callback(Request $request): Response
    {
        $this->ensureEnabled();

        $ticket = trim((string) $request->query('ticket', ''));
        $service = route($this->routeName('callback'));

        if ($ticket === '') {
            return $this->validationFailureResponse(
                CasValidationResult::failure('Missing ticket.', CasValidationResult::ERROR_REJECTED),
                $this->pendingReturnUrl($request),
            );
        }

        $returnUrl = $this->pendingReturnUrl($request);
        $result = $this->client->validate($service, $ticket);
        if (! $result->successful || $result->username === null) {
            return $this->validationFailureResponse($result, $returnUrl);
        }

        $user = $this->users->resolve($result->username, $result->attributes);
        $guard = $this->auth->guard((string) config('cas.guard', 'web'));
        if (method_exists($guard, 'setRememberDuration')) {
            $guard->setRememberDuration(max(1, (int) config('cas.remember_minutes', 120)));
        }
        $guard->login($user, true);
        $request->session()->regenerate();
        $request->session()->put(config('cas.session_key'), [
            'service' => $service,
            'ticket' => $ticket,
            'user' => $result->username,
            'attributes' => $result->attributes,
            'logged_in_at' => now()->toIso8601String(),
        ]);

        event(new CasAuthenticated($user, $result->username, $result->attributes, $ticket, $request));

        $request->session()->forget($this->returnUrlSessionKey());

        return redirect($returnUrl);
    }

    public function logout(Request $request): RedirectResponse
    {
        $this->ensureEnabled();

        $returnUrl = $this->safeReturnUrl((string) $request->query('returnUrl', '/'));
        $username = $this->sessionUsername($request);
        $isCallback = $request->query('logout') === 'logout';
        $this->clearAuthentication($request);

        event(new CasLoggedOut($username, $request));

        if (! $isCallback) {
            $callback = url($returnUrl);

            return redirect()->away($this->client->logoutUrl($callback));
        }

        return redirect($returnUrl);
    }

    public function userOnlineDetect(Request $request): Response
    {
        if (! config('cas.enabled')) {
            return response()->json(['isAlive' => true]);
        }

        $casUser = $request->session()->get(config('cas.session_key'));
        if (! is_array($casUser)) {
            return response()->json(['isAlive' => false]);
        }

        $isAlive = $this->client->isUserOnline(
            (string) ($casUser['service'] ?? ''),
            (string) ($casUser['ticket'] ?? ''),
            (string) ($casUser['user'] ?? ''),
        );

        if (! $isAlive) {
            $username = $this->sessionUsername($request);
            $this->clearAuthentication($request);
            event(new CasLoggedOut($username, $request, 'online_check_failed'));
        }

        return response()->json(['isAlive' => $isAlive]);
    }

    public function slo(Request $request): Response
    {
        $username = $this->sessionUsername($request);
        $this->clearAuthentication($request);
        event(new CasLoggedOut($username, $request, 'single_logout'));

        $callback = (string) $request->query('callback', '');
        if ($callback !== '' && preg_match('/^[A-Za-z_$][A-Za-z0-9_.$]*$/', $callback) === 1) {
            return response($callback.'('.json_encode(['success' => true]).');')->header('Content-Type', 'application/javascript');
        }

        return response()->json(['success' => true]);
    }

    private function clearAuthentication(Request $request): void
    {
        $this->auth->guard((string) config('cas.guard', 'web'))->logout();
        $request->session()->forget(config('cas.session_key'));
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    private function sessionUsername(Request $request): ?string
    {
        $value = data_get($request->session()->get(config('cas.session_key')), 'user');

        return filled($value) ? (string) $value : null;
    }

    private function safeReturnUrl(string $returnUrl): string
    {
        $returnUrl = str_replace('\\', '/', $returnUrl);

        if ($returnUrl === '' || ! str_starts_with($returnUrl, '/') || str_starts_with($returnUrl, '//')) {
            return '/';
        }

        return $returnUrl;
    }

    private function pendingReturnUrl(Request $request): string
    {
        $returnUrl = $request->session()->get($this->returnUrlSessionKey());

        if (! is_string($returnUrl) || $returnUrl === '') {
            $returnUrl = (string) $request->query('returnUrl', '/');
        }

        return $this->safeReturnUrl($returnUrl);
    }

    private function returnUrlSessionKey(): string
    {
        return (string) config('cas.return_url_session_key', 'cas_return_url');
    }

    private function routeName(string $route): string
    {
        return (string) config("cas.routes.names.{$route}");
    }

    private function ensureEnabled(): void
    {
        abort_unless(config('cas.enabled'), 404);
    }

    private function validationFailureResponse(CasValidationResult $result, string $returnUrl): Response
    {
        [$status, $title, $message] = match ($result->errorCode) {
            CasValidationResult::ERROR_REJECTED => [401, '登录验证失败', '统一认证未接受本次登录凭证，票据可能已经过期或被使用。'],
            CasValidationResult::ERROR_HTTP => [502, 'CAS 服务响应异常', '统一认证服务器暂时无法完成登录验证，请稍后重试。'],
            CasValidationResult::ERROR_INVALID_RESPONSE => [502, 'CAS 响应无法识别', '统一认证服务器返回了无法识别的数据，请联系系统管理员。'],
            default => [502, 'CAS 服务暂时不可用', '系统暂时无法连接统一认证服务器，请稍后重试。'],
        };

        return response()->view('cas-error', [
            'title' => $title,
            'message' => $message,
            'retryUrl' => route($this->routeName('redirect'), ['returnUrl' => $returnUrl]),
        ], $status);
    }
}
