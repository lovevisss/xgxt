<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class CasClient
{
    public function buildLoginUrl(string $service): string
    {
        return rtrim(config('cas.server_url'), '/').'/login?service='.urlencode($service);
    }

    public function buildLogoutUrl(string $service): string
    {
        return rtrim(config('cas.server_url'), '/').'/logout?service='.urlencode($service);
    }

    public function serviceValidate(string $service, string $ticket): array
    {
        $url = rtrim(config('cas.server_url'), '/').'/serviceValidate';
        $response = Http::timeout(10)->get($url, [
            'service' => $service,
            'ticket' => $ticket,
        ]);

        if (! $response->ok()) {
            return ['ok' => false, 'user' => null, 'attributes' => [], 'message' => 'CAS validate request failed'];
        }

        return $this->parseValidateResponse($response->body());
    }

    public function userOnlineDetect(string $service, string $ticket, string $username): bool
    {
        $url = rtrim(config('cas.server_url'), '/').'/login/userOnlineDetect';
        $response = Http::asForm()->timeout(10)->post($url, [
            'service' => $service,
            'ticket' => $ticket,
            'username' => $username,
        ]);

        if (! $response->ok()) {
            return false;
        }

        $data = $response->json();

        return (bool) data_get($data, 'data.isAlive', false);
    }

    private function parseValidateResponse(string $xml): array
    {
        $doc = new \DOMDocument();
        $doc->preserveWhiteSpace = false;

        if (! @$doc->loadXML($xml)) {
            return ['ok' => false, 'user' => null, 'attributes' => [], 'message' => 'Invalid CAS XML'];
        }

        $root = $doc->documentElement;
        if (! $root || $root->localName !== 'serviceResponse') {
            return ['ok' => false, 'user' => null, 'attributes' => [], 'message' => 'Unexpected CAS response'];
        }

        $successNodes = $root->getElementsByTagName('authenticationSuccess');
        if ($successNodes->length > 0) {
            $success = $successNodes->item(0);
            $userNodes = $success?->getElementsByTagName('user');
            $user = $userNodes && $userNodes->length > 0 ? trim((string) $userNodes->item(0)->nodeValue) : '';

            if ($user === '') {
                return ['ok' => false, 'user' => null, 'attributes' => [], 'message' => 'Missing CAS user'];
            }

            return [
                'ok' => true,
                'user' => $user,
                'attributes' => $this->extractAttributes($success),
                'message' => null,
            ];
        }

        $failureNodes = $root->getElementsByTagName('authenticationFailure');
        if ($failureNodes->length > 0) {
            $failure = $failureNodes->item(0);

            return [
                'ok' => false,
                'user' => null,
                'attributes' => [],
                'message' => trim((string) $failure->nodeValue) ?: 'CAS authentication failed',
            ];
        }

        return ['ok' => false, 'user' => null, 'attributes' => [], 'message' => 'Unknown CAS response'];
    }

    private function extractAttributes(\DOMElement $successNode): array
    {
        $attributes = [];
        $attributeSets = $successNode->getElementsByTagName('attributes');
        if ($attributeSets->length > 0) {
            foreach ($attributeSets->item(0)->childNodes as $child) {
                if (! $child instanceof \DOMElement) {
                    continue;
                }
                $this->addAttribute($attributes, $child->localName, trim((string) $child->nodeValue));
            }
        }

        if ($attributes === []) {
            $nameValueNodes = $successNode->getElementsByTagName('attribute');
            foreach ($nameValueNodes as $node) {
                if (! $node instanceof \DOMElement) {
                    continue;
                }
                if ($node->hasAttribute('name') && $node->hasAttribute('value')) {
                    $this->addAttribute($attributes, $node->getAttribute('name'), $node->getAttribute('value'));
                }
            }
        }

        return $attributes;
    }

    private function addAttribute(array &$attributes, string $name, string $value): void
    {
        if (array_key_exists($name, $attributes)) {
            if (! is_array($attributes[$name])) {
                $attributes[$name] = [$attributes[$name]];
            }
            $attributes[$name][] = $value;

            return;
        }

        $attributes[$name] = $value;
    }
}

