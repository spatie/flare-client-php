<?php

namespace Spatie\FlareClient\Spans;

use Spatie\FlareClient\Enums\SpanStatusCode;

class SpanStatus
{
    public function __construct(
        public SpanStatusCode $code = SpanStatusCode::Unset,
        public ?string $message = null
    ) {
        if ($code !== SpanStatusCode::Error && $this->message !== null) {
            throw new \InvalidArgumentException('Message can only be set for error status codes');
        }
    }

    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'message' => $this->message,
        ];
    }

    public static function default(): array
    {
        return [
            'code' => SpanStatusCode::Unset,
        ];
    }
}
