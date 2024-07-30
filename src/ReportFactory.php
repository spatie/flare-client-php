<?php

namespace Spatie\FlareClient;

use Exception;
use Spatie\Backtrace\Arguments\ArgumentReducers;
use Spatie\Backtrace\Arguments\Reducers\ArgumentReducer;
use Spatie\Backtrace\Backtrace;
use Spatie\ErrorSolutions\Contracts\Solution;
use Spatie\FlareClient\Concerns\HasAttributes;
use Spatie\FlareClient\Concerns\HasUserProvidedContext;
use Spatie\FlareClient\Contracts\ProvidesFlareContext;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Spans\SpanEvent;
use Spatie\LaravelFlare\Exceptions\ViewException;
use Spatie\LaravelIgnition\Exceptions\ViewException as IgnitionViewException;
use Throwable;

class ReportFactory
{
    use HasAttributes;
    use HasUserProvidedContext;

    public ?string $applicationPath = null;

    public ?string $version = null;

    /** @var  array<class-string<ArgumentReducer>|ArgumentReducer>|ArgumentReducers|null */
    public null|array|ArgumentReducers $argumentReducers = null;

    public bool $withStackTraceArguments = true;

    public ?string $stage = null;

    /** @var array<Span> */
    public array $spans = [];

    /** @var array<SpanEvent> */
    public array $spanEvents = [];

    /** @var array<Solution> */
    public array $solutions = [];

    public ?string $notifierName = null;

    public ?bool $handled = null;

    public ?string $applicationVersion = null;

    public ?string $languageVersion = null;

    public ?string $frameworkVersion = null;

    protected function __construct(
        public ?Throwable $throwable = null,
        public ?string $message = null,
        public ?string $logLevel = null,
    ) {
    }

    public static function createForMessage(string $message, string $logLevel): ReportFactory
    {
        return new self(message: $message, logLevel: $logLevel);
    }

    public static function createForThrowable(
        Throwable $throwable,
    ): ReportFactory {
        return new self(throwable: $throwable);
    }

    public function stage(?string $stage): self
    {
        $this->stage = $stage;

        return $this;
    }

    public function applicationPath(?string $applicationPath): self
    {
        $this->applicationPath = $applicationPath;

        return $this;
    }

    public function applicationVersion(?string $applicationVersion): self
    {
        $this->applicationVersion = $applicationVersion;

        return $this;
    }

    public function handled(bool $handled = true): self
    {
        $this->handled = $handled;

        return $this;
    }

    public function span(Span ...$span): self
    {
        array_push($this->spans, ...$span);

        return $this;
    }

    public function spanEvent(SpanEvent ...$spanEvent): self
    {
        array_push($this->spanEvents, ...$spanEvent);

        return $this;
    }

    public function notifier(string $name): self
    {
        $this->notifierName = $name;

        return $this;
    }

    public function addSolutions(Solution ...$solution): self
    {
        array_push($this->solutions, ...$solution);

        return $this;
    }

    public function languageVersion(string $languageVersion): self
    {
        $this->languageVersion = $languageVersion;

        return $this;
    }

    public function frameworkVersion(string $frameworkVersion): self
    {
        $this->frameworkVersion = $frameworkVersion;

        return $this;
    }

    public function argumentReducers(null|ArgumentReducers|array $argumentReducers): self
    {
        $this->argumentReducers = $argumentReducers;

        return $this;
    }

    public function withStackTraceArguments(bool $withStackTraceArguments): self
    {
        $this->withStackTraceArguments = $withStackTraceArguments;

        return $this;
    }

    public function build(): Report
    {
        if ($this->throwable === null && ($this->message === null || $this->logLevel === null)) {
            throw new Exception('No throwable or message provided');
        }

        $stackTrace = $this->buildStacktrace();

        $exceptionClass = $this->throwable
            ? $this->getClassForThrowable($this->throwable)
            : $this->logLevel;

        $exceptionContext = $this->throwable instanceof ProvidesFlareContext
            ? $this->throwable->context()
            : [];

        $message = $this->throwable
            ? $this->throwable->getMessage()
            : $this->message;

        $attributes = $this->attributes;

        if(! empty($this->userProvidedContext) || ! empty($exceptionContext)){
            $attributes['context.user'] = array_merge_recursive_distinct(
                $this->userProvidedContext ,
                $exceptionContext,
            );
        }

        return new Report(
            stacktrace: $stackTrace,
            exceptionClass: $exceptionClass,
            message: $message,
            attributes: $attributes,
            solutions: $this->solutions,
            throwable: $this->throwable,
            stage: $this->stage,
            applicationPath: $this->applicationPath,
            applicationVersion: $this->applicationVersion,
            languageVersion: $this->languageVersion,
            frameworkVersion: $this->frameworkVersion,
            openFrameIndex: $this->throwable ? null : $stackTrace->firstApplicationFrameIndex(),
            handled: $this->handled,
            spans: $this->spans,
            spanEvents: $this->spanEvents,
            trackingUuid: $this->generateUuid(),
        );
    }

    protected function buildStacktrace(): Backtrace
    {
        $stacktrace = $this->throwable
            ? Backtrace::createForThrowable($this->throwable)
            : Backtrace::create();

        return $stacktrace
            ->withArguments($this->withStackTraceArguments)
            ->reduceArguments($this->argumentReducers)
            ->applicationPath($applicationPath ?? '');
    }


    protected function getClassForThrowable(Throwable $throwable): string
    {
        // TODO: move to Laravel Client
        /** @phpstan-ignore-next-line */
        if ($throwable::class === IgnitionViewException::class || $throwable::class === ViewException::class) {
            /** @phpstan-ignore-next-line */
            if ($previous = $throwable->getPrevious()) {
                return get_class($previous);
            }
        }

        return get_class($throwable);
    }

    /*
     * Found on https://stackoverflow.com/questions/2040240/php-function-to-generate-v4-uuid/15875555#15875555
     */
    protected function generateUuid(): string
    {
        // Generate 16 bytes (128 bits) of random data or use the data passed into the function.
        $data = random_bytes(16);

        // Set version to 0100
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        // Set bits 6-7 to 10
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        // Output the 36 character UUID.
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
