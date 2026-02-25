<?php

namespace Tests;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use React\Http\Message\Response as ReactResponse;

class Response
{
    public static function make(int $status = 200, string $body = ''): ResponseInterface
    {
        return new ReactResponse($status, ['Content-Type' => 'application/json'], $body);
    }

    public static function ok(string $body = ''): ResponseInterface
    {
        return self::make(200, $body);
    }

    public static function created(string $body = ''): ResponseInterface
    {
        return self::make(201, $body);
    }

    public static function forbidden(string $body = '{"message":"Forbidden"}'): ResponseInterface
    {
        return self::make(403, $body);
    }

    public static function unprocessable(string $body = '{"message":"Validation error"}'): ResponseInterface
    {
        return self::make(422, $body);
    }

    public static function tooManyRequests(string $body = '{"message":"Rate limited"}'): ResponseInterface
    {
        return self::make(429, $body);
    }

    public static function quotaExceeded(string $type = 'errors'): ResponseInterface
    {
        return self::make(429, json_encode(['message' => "Quota exceeded for {$type}"]) ?: '');
    }

    public static function serverError(string $body = '{"message":"Internal server error"}'): ResponseInterface
    {
        return self::make(500, $body);
    }

    public static function usageResponse(
        int $errorsUsed = 0,
        int $errorsLimit = 1000,
        int $tracesUsed = 0,
        int $tracesLimit = 1000,
        int $logsUsed = 0,
        int $logsLimit = 1000,
        string $resetAt = '',
    ): ResponseInterface {
        return self::ok(json_encode([
            'errors_used' => $errorsUsed,
            'errors_limit' => $errorsLimit,
            'traces_used' => $tracesUsed,
            'traces_limit' => $tracesLimit,
            'logs_used' => $logsUsed,
            'logs_limit' => $logsLimit,
            'reset_at' => $resetAt,
        ]) ?: '{}');
    }
}
