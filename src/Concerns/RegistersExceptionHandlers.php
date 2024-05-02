<?php

namespace Spatie\FlareClient\Concerns;

use ErrorException;
use Spatie\FlareClient\Flare;
use Throwable;

/** @mixin Flare */
trait RegistersExceptionHandlers
{
    public static ?string $reservedMemory = null;

    /** @var null|callable */
    protected $previousExceptionHandler = null;

    /** @var null|callable */
    protected $previousErrorHandler = null;

    public function bootstrap(): void
    {
        static::$reservedMemory = str_repeat('0', 10 * 1024 * 1024);

        error_reporting(-1);

        $this->registerFlareHandlers();
    }

    public function registerFlareHandlers(): self
    {
        $this->registerExceptionHandler();

        $this->registerErrorHandler();

        $this->registerShutdownHandler();

        return $this;
    }

    public function registerExceptionHandler(): self
    {
        $this->previousExceptionHandler = set_exception_handler(fn ($e) => $this->handleException($e));

        return $this;
    }

    public function registerErrorHandler(): self
    {
        set_error_handler(function ($level, $message, $file = '', $line = 0) {
            $this->handleError($level, $message, $file, $line);
        });

        return $this;
    }

    public function registerShutdownHandler(): self
    {
        register_shutdown_function(fn () => $this->handleShutdown());

        return $this;
    }

    public function handleException(Throwable $throwable): void
    {
        $this->report($throwable);

        if ($this->previousExceptionHandler
            && is_callable($this->previousExceptionHandler)) {
            call_user_func($this->previousExceptionHandler, $throwable);
        }
    }

    public function handleError(mixed $code, string $message, string $file = '', int $line = 0)
    {
        static::$reservedMemory = null;

        $exception = new ErrorException($message, 0, $code, $file, $line);

        $this->report($exception);

        if ($this->previousErrorHandler) {
            return call_user_func(
                $this->previousErrorHandler,
                $message,
                $code,
                $file,
                $line
            );
        }
    }

    protected function handleShutdown(): void
    {
        self::$reservedMemory = null;

        $error = error_get_last(); // @todo returns null when running from a test file

        if ($error !== null && $error['type'] === E_ERROR) {
            ini_set('memory_limit', '-1');

            $this->handleError($error['type'], $error['message'], $error['file'], $error['line']);
        }
    }
}
