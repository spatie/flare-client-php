<?php

namespace Spatie\FlareClient\Concerns\Recorders;

/**
 * Pause tracing within a recorder while a single ignored event runs.
 *
 * The first `recordEnd` after `pauseTrace()` calls `resumeTrace()`, on the
 * assumption that no other `recordStart`/`recordEnd` fires on this recorder
 * between the two. That assumption holds for the recorders we have today
 * (a single ignored job/command/queue dispatch with nothing nested under it).
 *
 * Known limitation — events nesting under a pause:
 *   This trait breaks if either of these fires between `pauseTrace()` and
 *   the matching `recordEnd`:
 *   1. A normal (non-ignored) `recordStart`/`recordEnd` pair — the inner
 *      end will hit the `pausedTrace()` check first, call `resumeTrace()`,
 *      and leave the trace running while the outer ignored event is still
 *      logically paused.
 *   2. A second ignored `recordStart` — `pauseTrace()` is idempotent so the
 *      trace stays paused, but the inner end resumes the trace and the
 *      outer ignored event no longer has a matching resume.
 *
 * Upgrade path when this becomes a real scenario:
 *   - Replace `$pausedTrace` (bool) with a depth counter that increments on
 *     every `recordStart` fired while paused and decrements on every
 *     `recordEnd`. Resume the trace only when the counter returns to zero.
 *   - For the "normal nested under ignored" case, that's not enough on its
 *     own: each `recordStart` must also push a small marker (SPAN, PAUSED,
 *     or NOOP) onto a parallel frame stack so the matching `recordEnd`
 *     knows whether to pop a real span, resume the pause, or do nothing.
 *     `startSpan()` only pushes to `$stack` when `shouldTrace || shouldReport`,
 *     so the frame stack is the only reliable source of truth about what
 *     each `recordStart` actually did.
 */
trait PausableRecorder
{
    private bool $pausedTrace = false;

    private bool $ourTracePause = false;

    protected function pauseTrace(): void
    {
        $this->pausedTrace = true;

        if ($this->tracer->isSamplingPaused()) {
            return;
        }

        $this->tracer->pauseSampling();
        $this->ourTracePause = true;
    }

    protected function resumeTrace(): void
    {
        $this->pausedTrace = false;

        if (! $this->ourTracePause) {
            return;
        }

        $this->tracer->resumeSampling();
        $this->ourTracePause = false;
    }

    protected function pausedTrace(): bool
    {
        return $this->pausedTrace;
    }
}
