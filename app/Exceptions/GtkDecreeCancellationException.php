<?php

namespace App\Exceptions;

use Exception;

class GtkDecreeCancellationException extends Exception
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

    /**
     * @param  array<int, mixed>  $manualItems
     */
    public static function manualReviewRequired(string $message, array $manualItems = []): self
    {
        return new self($message, 'manual_review', $manualItems);
    }
}
