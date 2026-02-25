<?php

namespace Spatie\FlareDaemon\Factories;

use React\Http\Browser as ReactBrowser;
use Spatie\FlareDaemon\Browser;
use Spatie\FlareDaemon\Contracts\Browser as BrowserContract;
use Spatie\FlareDaemon\Loop;

class BrowserFactory
{
    public function __construct(
        private Loop $loop,
    ) {
    }

    public function create(): BrowserContract
    {
        $browser = new ReactBrowser($this->loop->get());

        return new Browser($browser);
    }
}
