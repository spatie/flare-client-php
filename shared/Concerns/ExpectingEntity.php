<?php

namespace Spatie\FlareClient\Tests\Shared\Concerns;

trait ExpectingEntity
{
    protected function getIndentPrefix(int $indent): string
    {
        $prefix = str_repeat('  ', $indent);

        if ($indent > 0) {
            $prefix .= '├─ ';
        }

        return $prefix;
    }
}
