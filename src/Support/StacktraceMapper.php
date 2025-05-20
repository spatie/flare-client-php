<?php

namespace Spatie\FlareClient\Support;

use ErrorException;
use Spatie\Backtrace\Frame;
use Throwable;

class StacktraceMapper
{
    /**
     * @param array<Frame> $frames
     *
     * @return array<array{file: string, lineNumber: int, method: string|null, class: string|null, codeSnippet: array<string>, arguments: array|null, isApplicationFrame: bool}>
     */
    public function map(array $frames, ?Throwable $throwable): array
    {
        if ($throwable) {
            $frames = $this->cleanupStackTraceForError($frames, $throwable);
        }

        return array_map(
            fn (Frame $frame) => $this->mapFrame($frame),
            $frames
        );
    }

    protected function cleanupStackTraceForError(
        array $frames,
        Throwable $throwable,
    ): array {
        if ($throwable::class !== ErrorException::class) {
            return $frames;
        }

        $firstErrorFrameIndex = null;

        $restructuredFrames = array_values(array_slice($frames, 1)); // remove the first frame where error was created

        foreach ($restructuredFrames as $index => $frame) {
            if ($frame->file === $throwable->getFile()) {
                $firstErrorFrameIndex = $index;

                break;
            }
        }

        if ($firstErrorFrameIndex === null) {
            return $frames;
        }

        $restructuredFrames[$firstErrorFrameIndex]->arguments = null; // Remove error arguments

        return array_values(array_slice($restructuredFrames, $firstErrorFrameIndex));
    }

    /**
     * @return array{file: string, lineNumber: int, method: string|null, class: string|null, codeSnippet: array<string>, arguments: array|null, isApplicationFrame: bool}
     */
    protected function mapFrame(Frame $frame): array
    {
        return [
            'file' => $frame->file,
            'lineNumber' => $frame->lineNumber,
            'method' => $frame->method,
            'class' => $frame->class,
            'codeSnippet' => $frame->getSnippet(30),
            'arguments' => $frame->arguments,
            'isApplicationFrame' => $frame->applicationFrame,
        ];
    }
}
