<?php

namespace Spatie\FlareClient\Recorders\FilesystemRecorder;

use Spatie\FlareClient\Enums\FilesystemOperation;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\Recorders\SpansRecorder;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Support\Humanizer;

class FilesystemRecorder extends SpansRecorder
{
    public static function type(): string|RecorderType
    {
        return RecorderType::Filesystem;
    }

    public function recordOperationStart(
        string|FilesystemOperation $operation,
        ?string $description = null,
        array $attributes = [],
    ): ?Span {
        $operationName = $operation instanceof FilesystemOperation
            ? $operation->value
            : $operation;

        return $this->startSpan(
            name: $description ?? "Filesystem - {$operationName}",
            attributes: [
                'flare.span_type' => SpanType::Filesystem,
                'filesystem.operation' => $operation,
                ...$attributes,
            ],
        );
    }

    public function recordOperationEnd(
        array $attributes = [],
    ): ?Span {
        return $this->endSpan(additionalAttributes:  [
            ...$attributes,
        ]);
    }

    public function recordPath(string|array $path, array $attributes = []): ?Span
    {
        return $this->recordOperationStart(
            operation: FilesystemOperation::Get,
            attributes: [
                'filesystem.path' => static::humanizerClass()::filesystemPaths($path),
                ...$attributes,
            ]
        );
    }

    public function recordExists(string|array $path, array $attributes = []): ?Span
    {
        return $this->recordOperationStart(
            operation: FilesystemOperation::Exists,
            attributes: [
                'filesystem.path' => static::humanizerClass()::filesystemPaths($path),
                ...$attributes,
            ]
        );
    }

    public function recordGet(string $path, array $attributes = []): ?Span
    {
        return $this->recordOperationStart(
            operation: FilesystemOperation::Get,
            attributes: [
                'filesystem.path' => static::humanizerClass()::filesystemPaths($path),
                ...$attributes,
            ]
        );
    }

    public function recordPut(string $path, mixed $contents = null, array $attributes = []): ?Span
    {
        return $this->recordOperationStart(
            operation: FilesystemOperation::Put,
            attributes: [
                'filesystem.path' => static::humanizerClass()::filesystemPaths($path),
                'filesystem.contents.size' => static::humanizerClass()::contentSize($contents),
                ...$attributes,
            ]
        );
    }

    public function recordPrepend(string $path, mixed $data = null, array $attributes = []): ?Span
    {
        return $this->recordOperationStart(
            operation: FilesystemOperation::Prepend,
            attributes: [
                'filesystem.path' => static::humanizerClass()::filesystemPaths($path),
                'filesystem.contents.size' => static::humanizerClass()::contentSize($data),
                ...$attributes,
            ]
        );
    }

    public function recordAppend(string $path, mixed $data, array $attributes = []): ?Span
    {
        return $this->recordOperationStart(
            operation: FilesystemOperation::Append,
            attributes: [
                'filesystem.path' => static::humanizerClass()::filesystemPaths($path),
                'filesystem.contents.size' => static::humanizerClass()::contentSize($data),
                ...$attributes,
            ]
        );
    }

    public function recordDelete(string|array $paths, array $attributes = []): ?Span
    {
        return $this->recordOperationStart(
            operation: FilesystemOperation::Delete,
            attributes: [
                'filesystem.paths' => static::humanizerClass()::filesystemPaths($paths),
                ...$attributes,
            ]
        );
    }

    public function recordCopy(string $from, string $to, array $attributes = []): ?Span
    {
        return $this->recordOperationStart(
            operation: FilesystemOperation::Copy,
            attributes: [
                'filesystem.from_path' => static::humanizerClass()::filesystemPaths($from),
                'filesystem.to_path' => static::humanizerClass()::filesystemPaths($to),
                ...$attributes,
            ]
        );
    }

    public function recordMove(string $from, string $to, array $attributes = []): ?Span
    {
        return $this->recordOperationStart(
            operation: FilesystemOperation::Move,
            attributes: [
                'filesystem.from_path' => static::humanizerClass()::filesystemPaths($from),
                'filesystem.to_path' => static::humanizerClass()::filesystemPaths($to),
                ...$attributes,
            ]
        );
    }

    public function recordSize(string $path, array $attributes = []): ?Span
    {
        return $this->recordOperationStart(
            operation: FilesystemOperation::Size,
            attributes: [
                'filesystem.path' => static::humanizerClass()::filesystemPaths($path),
                ...$attributes,
            ]
        );
    }

    public function recordFiles(string $directory, bool $recursive = false, array $attributes = []): ?Span
    {
        return $this->recordOperationStart(
            operation: FilesystemOperation::Files,
            attributes: [
                'filesystem.path' => static::humanizerClass()::filesystemPaths($directory, 'files'),
                'filesystem.recursive' => $recursive,
                ...$attributes,
            ]
        );
    }

    public function recordDirectories(string $directory, bool $recursive = false, array $attributes = []): ?Span
    {
        return $this->recordOperationStart(
            operation: FilesystemOperation::Directories,
            attributes: [
                'filesystem.path' => static::humanizerClass()::filesystemPaths($directory),
                'filesystem.recursive' => $recursive,
                ...$attributes,
            ]
        );
    }

    public function recordMakeDirectory(string $path, array $attributes = []): ?Span
    {
        return $this->recordOperationStart(
            operation: FilesystemOperation::MakeDirectory,
            attributes: [
                'filesystem.path' => static::humanizerClass()::filesystemPaths($path),
                ...$attributes,
            ]
        );
    }

    public function recordDeleteDirectory(string $directory, array $attributes = []): ?Span
    {
        return $this->recordOperationStart(
            operation: FilesystemOperation::DeleteDirectory,
            attributes: [
                'filesystem.path' => static::humanizerClass()::filesystemPaths($directory),
                ...$attributes,
            ]
        );
    }

    public function recordUrl(string|array $path, array $attributes = []): ?Span
    {
        return $this->recordOperationStart(
            operation: FilesystemOperation::Url,
            attributes: [
                'filesystem.path' => static::humanizerClass()::filesystemPaths($path),
                ...$attributes,
            ]
        );
    }

    /** @return class-string<Humanizer> */
    protected function humanizerClass(): string
    {
        return Humanizer::class;
    }
}
