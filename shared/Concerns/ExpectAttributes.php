<?php

namespace Spatie\FlareClient\Tests\Shared\Concerns;

use Closure;

trait ExpectAttributes
{
    use ExpectingEntity;

    abstract public function attributes(): array;

    public function expectAttributesCount(int $count): self
    {
        expect($this->attributes())->toHaveCount($count);

        return $this;
    }

    public function expectAttribute(string $key, mixed $value): self
    {
        if ($value instanceof Closure) {
            $value($this->attributes()[$key]);

            return $this;
        }

        expect($this->attributes()[$key])->toBe($value);

        return $this;
    }

    public function expectHasAttribute(string $key): self
    {
        expect($this->attributes())->toHaveKey($key);

        return $this;
    }

    public function expectMissingAttribute(string $key): self
    {
        expect($this->attributes())->not->toHaveKey($key);

        return $this;
    }

    public function expectAttributes(array $attributes, bool $exact = false): self
    {
        if ($exact) {
            expect($this->attributes())->toEqualCanonicalizing($attributes);

            return $this;
        }

        foreach ($attributes as $key => $value) {
            expect($this->attributes()[$key])->toBe($value);
        }

        return $this;
    }

    protected function attributesToStrings(
        int $indent,
        string $prefix = '•'
    ): array {
        $filteredAttributes = array_filter(
            $this->attributes(),
            fn ($key) => ! in_array($key, ['flare.span_type', 'flare.span_event_type']),
            ARRAY_FILTER_USE_KEY
        );

        if (empty($filteredAttributes)) {
            return [];
        }

        $indentPrefix = $this->getIndentPrefix($indent);

        $output = [];

        foreach ($filteredAttributes as $key => $value) {
            $valueStr = is_array($value) ? json_encode($value) : (string) $value;

            $output[] = "{$indentPrefix}{$prefix} {$key}: {$valueStr}";
        }

        return $output;
    }
}
