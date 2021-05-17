<?php

namespace Spatie\FlareClient\Context;

interface ContextDetectorInterface
{
    public function detectCurrentContext(): ContextInterface;
}
