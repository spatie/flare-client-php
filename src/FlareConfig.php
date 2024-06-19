<?php

namespace Spatie\FlareClient;

use Closure;
use Spatie\Backtrace\Arguments\ArgumentReducers;
use Spatie\Backtrace\Arguments\Reducers\ArgumentReducer;
use Spatie\FlareClient\Context\ContextProviderDetector;
use Spatie\FlareClient\FlareMiddleware\FlareMiddleware;

class FlareConfig
{
    /**
     * @param array<class-string<FlareMiddleware>> $middleware
     * @param array<class-string> $recorders
     * @param class-string<ContextProviderDetector>|null $contextProviderDetector
     * @param array<class-string<ArgumentReducer>|ArgumentReducer>|ArgumentReducers|null $argumentReducers
     */
    public function __construct(
        public string $apiToken,
        public string $baseUrl = 'https://reporting.flareapp.io/api',
        public bool $sendReportsImmediately = false,
        public array $middleware = [],
        public array $recorders = [],
        public ?string $applicationPath = null,
        public ?string $contextProviderDetector = null,
        public ?Closure $determineVersionCallable = null,
        public ?int $reportErrorLevels = null,
        public ?Closure $filterExceptionsCallable = null,
        public ?Closure $filterReportsCallable = null,
        public ?string $stage = null,
        public null|array|ArgumentReducers $argumentReducers = [],
        public bool $withStackFrameArguments = true,
    ) {
    }

    public static function make(string $apiToken): self
    {
        return new self($apiToken);
    }

    public function withMiddleware()
    {

    }
}
