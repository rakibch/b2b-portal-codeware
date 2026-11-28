<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * A violated business rule (MOQ, KYC gate, credit limit, invalid state transition...).
 * Message is a translation key resolved in the active locale; rendered as JSON with a stable `code`.
 */
class BusinessRuleException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        public readonly array $replace = [],
        public readonly int $status = 422,
        public readonly array $context = [],
    ) {
        parent::__construct(__('messages.errors.'.$errorCode, $replace));
    }

    public static function make(string $errorCode, array $replace = [], int $status = 422, array $context = []): self
    {
        return new self($errorCode, $replace, $status, $context);
    }

    public function render(): JsonResponse
    {
        return response()->json(array_filter([
            'message' => $this->getMessage(),
            'code' => $this->errorCode,
            'context' => $this->context ?: null,
        ]), $this->status);
    }
}
