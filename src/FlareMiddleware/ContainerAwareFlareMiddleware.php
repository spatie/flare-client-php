<?php

namespace Spatie\FlareClient\FlareMiddleware;

use Psr\Container\ContainerInterface;
use Spatie\FlareClient\Support\Container;

interface ContainerAwareFlareMiddleware
{
    public function register(Container|ContainerInterface $container): void;

    public function boot(Container|ContainerInterface $container): void;
}
