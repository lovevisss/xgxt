<?php

namespace Zufedfc\LaravelCas\Data;

final readonly class CasValidationResult
{
    public function __construct(
        public bool $successful,
        public ?string $username = null,
        public array $attributes = [],
        public ?string $message = null,
    ) {}

    public static function success(string $username, array $attributes = []): self
    {
        return new self(true, $username, $attributes);
    }

    public static function failure(string $message): self
    {
        return new self(false, message: $message);
    }
}
