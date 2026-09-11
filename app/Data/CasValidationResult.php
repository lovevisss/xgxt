<?php

namespace App\Data;

final readonly class CasValidationResult
{
    public const ERROR_CONNECTION = 'connection';

    public const ERROR_HTTP = 'http';

    public const ERROR_INVALID_RESPONSE = 'invalid_response';

    public const ERROR_REJECTED = 'rejected';

    public function __construct(
        public bool $successful,
        public ?string $username = null,
        public array $attributes = [],
        public ?string $message = null,
        public ?string $errorCode = null,
    ) {
    }

    public static function success(string $username, array $attributes = []): self
    {
        return new self(true, $username, $attributes);
    }

    public static function failure(string $message, string $errorCode): self
    {
        return new self(false, message: $message, errorCode: $errorCode);
    }
}
