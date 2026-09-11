<?php

namespace App\Auth;

use App\Data\CasValidationResult;
use DOMDocument;
use DOMElement;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Log;
use Throwable;

class CasClient
{
    public function __construct(private readonly HttpFactory $http)
    {
    }

    public function loginUrl(string $service): string
    {
        return $this->publicEndpoint('login').'?'.http_build_query(['service' => $service]);
    }

    public function logoutUrl(string $service): string
    {
        return $this->publicEndpoint('logout').'?'.http_build_query(['service' => $service]);
    }

    public function validate(string $service, string $ticket): CasValidationResult
    {
        $endpoint = $this->backchannelEndpoint('serviceValidate');
        $startedAt = hrtime(true);

        try {
            $response = $this->http
                ->connectTimeout($this->connectTimeout())
                ->timeout($this->timeout())
                ->get($endpoint, [
                    'service' => $service,
                    'ticket' => $ticket,
                ]);
        } catch (Throwable $exception) {
            $this->logFailure('ticket_validation', $endpoint, $startedAt, [
                'exception' => $exception::class,
                'code' => $exception->getCode(),
            ]);

            return CasValidationResult::failure('Unable to contact the CAS server.', CasValidationResult::ERROR_CONNECTION);
        }

        if (! $response->successful()) {
            $this->logFailure('ticket_validation', $endpoint, $startedAt, ['status' => $response->status()]);

            return CasValidationResult::failure(
                "CAS validation returned HTTP {$response->status()}.",
                CasValidationResult::ERROR_HTTP,
            );
        }

        return $this->parseValidationResponse($response->body());
    }

    public function isUserOnline(string $service, string $ticket, string $username): bool
    {
        $endpoint = $this->backchannelEndpoint('login/userOnlineDetect');
        $startedAt = hrtime(true);

        try {
            $response = $this->http
                ->asForm()
                ->connectTimeout($this->connectTimeout())
                ->timeout($this->timeout())
                ->post($endpoint, [
                    'service' => $service,
                    'ticket' => $ticket,
                    'username' => $username,
                ]);
        } catch (Throwable $exception) {
            $this->logFailure('online_detection', $endpoint, $startedAt, [
                'exception' => $exception::class,
                'code' => $exception->getCode(),
            ]);

            return false;
        }

        return $response->successful() && (bool) data_get($response->json(), 'data.isAlive', false);
    }

    private function parseValidationResponse(string $xml): CasValidationResult
    {
        $document = new DOMDocument;
        $document->preserveWhiteSpace = false;

        if (! @$document->loadXML($xml, LIBXML_NONET)) {
            return CasValidationResult::failure('CAS returned invalid XML.', CasValidationResult::ERROR_INVALID_RESPONSE);
        }

        $root = $document->documentElement;
        if (! $root || $root->localName !== 'serviceResponse') {
            return CasValidationResult::failure('CAS returned an unexpected response.', CasValidationResult::ERROR_INVALID_RESPONSE);
        }

        $successNodes = $root->getElementsByTagName('authenticationSuccess');
        if ($successNodes->length > 0) {
            $success = $successNodes->item(0);
            $userNodes = $success?->getElementsByTagName('user');
            $username = $userNodes && $userNodes->length > 0 ? trim((string) $userNodes->item(0)->nodeValue) : '';

            if ($username === '' || ! $success instanceof DOMElement) {
                return CasValidationResult::failure('CAS response did not include a username.', CasValidationResult::ERROR_INVALID_RESPONSE);
            }

            return CasValidationResult::success($username, $this->extractAttributes($success));
        }

        $failureNodes = $root->getElementsByTagName('authenticationFailure');
        if ($failureNodes->length > 0) {
            $message = trim((string) $failureNodes->item(0)->nodeValue);

            return CasValidationResult::failure($message ?: 'CAS authentication failed.', CasValidationResult::ERROR_REJECTED);
        }

        return CasValidationResult::failure('CAS returned an unknown response.', CasValidationResult::ERROR_INVALID_RESPONSE);
    }

    private function extractAttributes(DOMElement $success): array
    {
        $attributes = [];
        $containers = $success->getElementsByTagName('attributes');

        if ($containers->length > 0) {
            foreach ($containers->item(0)->childNodes as $child) {
                if ($child instanceof DOMElement) {
                    $this->addAttribute($attributes, $child->localName, trim((string) $child->nodeValue));
                }
            }
        }

        if ($attributes === []) {
            foreach ($success->getElementsByTagName('attribute') as $node) {
                if ($node instanceof DOMElement && $node->hasAttribute('name') && $node->hasAttribute('value')) {
                    $this->addAttribute($attributes, $node->getAttribute('name'), $node->getAttribute('value'));
                }
            }
        }

        return $attributes;
    }

    private function addAttribute(array &$attributes, string $name, string $value): void
    {
        if (array_key_exists($name, $attributes)) {
            $attributes[$name] = is_array($attributes[$name]) ? [...$attributes[$name], $value] : [$attributes[$name], $value];

            return;
        }

        $attributes[$name] = $value;
    }

    private function publicEndpoint(string $path): string
    {
        return rtrim((string) config('cas.server_url'), '/').'/'.ltrim($path, '/');
    }

    private function backchannelEndpoint(string $path): string
    {
        return rtrim((string) config('cas.backchannel_url', config('cas.server_url')), '/').'/'.ltrim($path, '/');
    }

    private function connectTimeout(): int
    {
        return max(1, (int) config('cas.connect_timeout', 3));
    }

    private function timeout(): int
    {
        return max(1, (int) config('cas.http_timeout', 10));
    }

    private function logFailure(string $operation, string $endpoint, int $startedAt, array $context = []): void
    {
        Log::warning('CAS backchannel request failed.', [
            'operation' => $operation,
            'endpoint' => $endpoint,
            'duration_ms' => (int) round((hrtime(true) - $startedAt) / 1_000_000),
            ...$context,
        ]);
    }
}
