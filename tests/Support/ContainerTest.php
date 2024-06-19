<?php

use Spatie\FlareClient\Support\Container;
use Spatie\FlareClient\Support\Exceptions\ContainerEntryNotFoundException;
use Spatie\FlareClient\Tests\stubs\SomeClass;
use Spatie\FlareClient\Tests\stubs\SomeClassExtended;

it('always gets the same container instance', function (){
    $containerA = Container::instance();
    $containerB = Container::instance();

    expect(spl_object_id($containerA))->toBe(spl_object_id($containerB));
});

it('can bind a class to the container', function (){
    $container = Container::instance();

    $container->bind(SomeClass::class, fn() => new SomeClass());

    expect($container->get(SomeClass::class))->toBeInstanceOf(SomeClass::class);
});

it('always returns a new instance when binding a class', function (){
    $container = Container::instance();

    $container->bind(SomeClass::class, fn() => new SomeClass());

    $instanceA = $container->get(SomeClass::class);
    $instanceB = $container->get(SomeClass::class);

    expect(spl_object_id($instanceA))->not->toBe(spl_object_id($instanceB));
});

it('it always returns the same instance when binding a singleton', function (){
    $container = Container::instance();

    $container->singleton(SomeClass::class, fn() => new SomeClass());

    $instanceA = $container->get(SomeClass::class);
    $instanceB = $container->get(SomeClass::class);

    expect(spl_object_id($instanceA))->toBe(spl_object_id($instanceB));
});

it('throws an exception when trying to get a non-existing entry', function (){
    $container = Container::instance();

    $container->get('non-existing-entry');
})->throws(ContainerEntryNotFoundException::class);

it('is possible to rebind a class', function (){
    $container = Container::instance();

    $container->bind(SomeClass::class, fn() => new SomeClass());

    $instanceA = $container->get(SomeClass::class);

    $container->bind(SomeClass::class, fn() => new SomeClassExtended());

    $instanceB = $container->get(SomeClass::class);

    expect($instanceA)->toBeInstanceOf(SomeClass::class);
    expect($instanceB)->toBeInstanceOf(SomeClassExtended::class);
});
