<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CasClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class CasAuthController extends Controller
{
    public function __construct(private readonly CasClient $casClient) {}

    public function login(Request $request)
    {
        if (! config('cas.enabled')) {
            abort(404);
        }

        $returnUrl = $this->sanitizeReturnUrl((string) $request->query('returnUrl', '/'));
        $service = route('cas.login', ['returnUrl' => $returnUrl], false);
        $serviceUrl = url($service);
        $ticket = (string) $request->query('ticket', '');

        if ($ticket === '') {
            return redirect()->away($this->casClient->buildLoginUrl($serviceUrl));
        }

        $result = $this->casClient->serviceValidate($serviceUrl, $ticket);
        if (! $result['ok']) {
            return redirect($returnUrl)->with('cas_error', $result['message']);
        }

        $sessionKey = config('cas.session_key');
        session()->put($sessionKey, [
            'service' => $serviceUrl,
            'ticket' => $ticket,
            'user' => $result['user'],
            'attributes' => $result['attributes'],
            'logged_in_at' => now()->toIso8601String(),
        ]);

        Auth::login($this->syncCasUser((string) $result['user'], $result['attributes']));

        return redirect($returnUrl);
    }

    public function logout(Request $request)
    {
        if (! config('cas.enabled')) {
            abort(404);
        }

        $returnUrl = $this->sanitizeReturnUrl((string) $request->query('returnUrl', '/'));
        $logout = (string) $request->query('logout', '');
        $this->clearLocalAuthentication($request);

        if ($logout !== 'logout') {
            $callback = route('cas.logout', ['returnUrl' => $returnUrl, 'logout' => 'logout'], false);
            $callbackUrl = url($callback);

            return redirect()->away($this->casClient->buildLogoutUrl($callbackUrl));
        }

        return redirect($returnUrl);
    }

    public function userOnlineDetect(Request $request): Response
    {
        if (! config('cas.enabled')) {
            return response(['isAlive' => true], 200);
        }

        $casUser = session(config('cas.session_key'));
        if (! is_array($casUser)) {
            return response(['isAlive' => false], 200);
        }

        $isAlive = $this->casClient->userOnlineDetect(
            (string) ($casUser['service'] ?? ''),
            (string) ($casUser['ticket'] ?? ''),
            (string) ($casUser['user'] ?? '')
        );

        if (! $isAlive) {
            $this->clearLocalAuthentication($request);
        }

        return response(['isAlive' => $isAlive], 200);
    }

    public function slo(Request $request): Response
    {
        $this->clearLocalAuthentication($request);

        $callback = (string) $request->query('callback', '');
        if ($callback !== '' && preg_match('/^[A-Za-z_$][A-Za-z0-9_.$]*$/', $callback) === 1) {
            return response($callback.'('.json_encode(['success' => true]).');', 200)
                ->header('Content-Type', 'application/javascript');
        }

        return response()->json([
            'success' => true,
            'callback' => $callback ?: null,
        ]);
    }

    private function sanitizeReturnUrl(string $returnUrl): string
    {
        if ($returnUrl === '' || ! str_starts_with($returnUrl, '/')) {
            return '/';
        }

        return $returnUrl;
    }

    private function syncCasUser(string $username, array $attributes): User
    {
        $name = $this->firstAttribute($attributes, ['name', 'xm', 'cn', 'displayName']) ?: $username;
        $departmentCode = $this->firstAttribute($attributes, ['dwbm', 'department_code', 'deptCode', 'orgCode']);
        $departmentName = $this->firstAttribute($attributes, ['dwmc', 'department_name', 'deptName', 'orgName']);

        $values = [
            'name' => $name,
            'email' => "{$username}@cas.local",
            'password' => Hash::make(Str::random(40)),
        ];

        if ($departmentCode !== null) {
            $values['dwbm'] = $departmentCode;
        }

        if ($departmentName !== null) {
            $values['dwmc'] = $departmentName;
        }

        return User::query()->updateOrCreate(['cas_username' => $username], $values);
    }

    private function firstAttribute(array $attributes, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $attributes[$key] ?? null;
            if (is_array($value)) {
                $value = $value[0] ?? null;
            }
            if (filled($value)) {
                return (string) $value;
            }
        }

        return null;
    }

    private function clearLocalAuthentication(Request $request): void
    {
        Auth::logout();
        $request->session()->forget(config('cas.session_key'));
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
