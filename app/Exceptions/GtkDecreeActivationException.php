<?php

namespace App\Exceptions;

use Exception;

class GtkDecreeActivationException extends Exception
{
    /**
     * @param  array<int, mixed>  $errors
     */
    public function __construct(
        string $message,
        protected string $errorType = 'general',
        protected array $errors = [],
        int $code = 422,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getErrorType(): string
    {
        return $this->errorType;
    }

    /**
     * @return array<int, mixed>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public static function invalidStatus(string $message): self
    {
        return new self($message, 'invalid_status');
    }

    public static function futureEffectiveDate(string $message): self
    {
        return new self($message, 'future_date');
    }

    /**
     * @param  array<int, mixed>  $conflicts
     */
    public static function conflict(string $message, array $conflicts = []): self
    {
        return new self($message, 'conflict', $conflicts);
    }

    /**
     * @param  array<int, mixed>  $items
     */
    public static function incomplete(string $message, array $items = []): self
    {
        return new self($message, 'incomplete', $items);
    }
}
