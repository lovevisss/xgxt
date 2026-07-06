<?php

namespace Zufedfc\LaravelCas;

use DOMDocument;
use DOMElement;
use Illuminate\Http\Client\Factory as HttpFactory;
use Zufedfc\LaravelCas\Data\CasValidationResult;

class CasClient
{
    public function __construct(private readonly HttpFactory $http) {}

    public function loginUrl(string $service): string
    {
        return $this->endpoint('login').'?'.http_build_query(['service' => $service]);
    }

    public function logoutUrl(string $service): string
    {
        return $this->endpoint('logout').'?'.http_build_query(['service' => $service]);
    }

    public function validate(string $service, string $ticket): CasValidationResult
    {
        try {
            $response = $this->http
                ->timeout($this->timeout())
                ->get($this->endpoint('serviceValidate'), [
                    'service' => $service,
                    'ticket' => $ticket,
                ]);
        } catch (\Throwable $exception) {
            report($exception);

            return CasValidationResult::failure('Unable to contact the CAS server.');
        }

        if (! $response->successful()) {
            return CasValidationResult::failure("CAS validation returned HTTP {$response->status()}.");
        }

        return $this->parseValidationResponse($response->body());
    }

    public function isUserOnline(string $service, string $ticket, string $username): bool
    {
        try {
            $response = $this->http
                ->asForm()
                ->timeout($this->timeout())
                ->post($this->endpoint('login/userOnlineDetect'), [
                    'service' => $service,
                    'ticket' => $ticket,
                    'username' => $username,
                ]);
        } catch (\Throwable $exception) {
            report($exception);

            return false;
        }

        return $response->successful()
            && (bool) data_get($response->json(), 'data.isAlive', false);
    }

    private function parseValidationResponse(string $xml): CasValidationResult
    {
        $document = new DOMDocument();
        $document->preserveWhiteSpace = false;

        if (! @$document->loadXML($xml, LIBXML_NONET)) {
            return CasValidationResult::failure('CAS returned invalid XML.');
        }

        $root = $document->documentElement;
        if (! $root || $root->localName !== 'serviceResponse') {
            return CasValidationResult::failure('CAS returned an unexpected response.');
        }

        $successNodes = $root->getElementsByTagName('authenticationSuccess');
        if ($successNodes->length > 0) {
            $success = $successNodes->item(0);
            $userNodes = $success?->getElementsByTagName('user');
            $username = $userNodes && $userNodes->length > 0
                ? trim((string) $userNodes->item(0)->nodeValue)
                : '';

            if ($username === '' || ! $success instanceof DOMElement) {
                return CasValidationResult::failure('CAS response did not include a username.');
            }

            return CasValidationResult::success($username, $this->extractAttributes($success));
        }

        $failureNodes = $root->getElementsByTagName('authenticationFailure');
        if ($failureNodes->length > 0) {
            $message = trim((string) $failureNodes->item(0)->nodeValue);

            return CasValidationResult::failure($message ?: 'CAS authentication failed.');
        }

        return CasValidationResult::failure('CAS returned an unknown response.');
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
            $attributes[$name] = is_array($attributes[$name])
                ? [...$attributes[$name], $value]
                : [$attributes[$name], $value];

            return;
        }

        $attributes[$name] = $value;
    }

    private function endpoint(string $path): string
    {
        return rtrim((string) config('cas.server_url'), '/').'/'.ltrim($path, '/');
    }

    private function timeout(): int
    {
        return max(1, (int) config('cas.http_timeout', 10));
    }
}
