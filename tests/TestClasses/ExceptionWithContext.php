<?php

namespace Spatie\FlareClient\Tests\TestClasses;

use Spatie\FlareClient\Contracts\ProvidesFlareContext;

class ExceptionWithContext extends \Exception implements ProvidesFlareContext
{
    public function context(): array
    {
        return [
            'another key' => 'another value',
        ];
    }
}
