<?php

declare(strict_types=1);

namespace Iceberg\Subzero\Exceptions;

class SubzeroApiException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $statusCode = null,
        public readonly ?string $reason = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}

final class AuthenticationException extends SubzeroApiException
{
}

final class AuthorizationException extends SubzeroApiException
{
}

final class PolicyDeniedException extends SubzeroApiException
{
}

final class NotFoundException extends SubzeroApiException
{
}

final class ConflictException extends SubzeroApiException
{
}

final class SubzeroNotReadyException extends SubzeroApiException
{
}
