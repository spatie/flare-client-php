<?php

namespace Spatie\FlareClient\Tests\Shared;

class ExpectStackTraceFrame
{
    public function __construct(
        public array $frame
    ) {
    }

    public function expectFile(string $file): self
    {
        expect($this->frame['file'])->toBe($file);

        return $this;
    }

    public function expectLineNumber(int $lineNumber): self
    {
        expect($this->frame['lineNumber'])->toBe($lineNumber);

        return $this;
    }

    public function expectMethod(?string $method): self
    {
        expect($this->frame['method'])->toBe($method);

        return $this;
    }

    public function expectClass(?string $class): self
    {
        expect($this->frame['class'])->toBe($class);

        return $this;
    }

    public function expectCodeSnippet(array $codeSnippet): self
    {
        expect($this->frame['codeSnippet'])->toBe($codeSnippet);

        return $this;
    }

    public function expectHasCodeSnippet(): self
    {
        expect($this->frame['codeSnippet'])->toBeArray();
        expect($this->frame['codeSnippet'])->not->toBeEmpty();

        return $this;
    }

    public function expectArgumentCount(int $count): self
    {
        expect($this->frame['arguments'])->toHaveCount($count);

        return $this;
    }

    public function expectArgument(int $index): ExpectStackTraceArgument
    {
        return new ExpectStackTraceArgument($this->frame['arguments'][$index]);
    }

    public function expectHasArguments(): self
    {
        expect($this->frame['arguments'])->toBeArray();
        expect($this->frame['arguments'])->not->toBeEmpty();

        return $this;
    }

    public function expectNoArguments(): self
    {
        expect($this->frame['arguments'])->toBeNull();

        return $this;
    }

    public function expectIsApplicationFrame(bool $isApplicationFrame = true): self
    {
        expect($this->frame['isApplicationFrame'])->toBe($isApplicationFrame);

        return $this;
    }
}