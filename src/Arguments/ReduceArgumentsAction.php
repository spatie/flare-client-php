<?php

namespace Spatie\FlareClient\Arguments;

use ReflectionParameter;
use Spatie\Backtrace\Frame as SpatieFrame;
use Spatie\FlareClient\Arguments\ReducedArgument\VariadicReducedArgument;

class ReduceArgumentsAction
{
    protected ReduceArgumentPayloadAction $reduceArgumentPayloadAction;

    public function __construct(
        protected ?ArgumentReducers $argumentReducers,
    ) {
        $this->reduceArgumentPayloadAction = new ReduceArgumentPayloadAction(
            $argumentReducers->argumentReducers
        );
    }

    public function execute(SpatieFrame $frame): array
    {
        if ($frame->arguments === null) {
            return [];
        }

        $parameters = $this->getParameters($frame);

        if ($parameters === null) {
            $arguments = [];

            foreach ($frame->arguments as $index => $argument) {
                $arguments[$index] = ProvidedArgument::fromNonReflectableParameter($index)->setReducedArgument(
                    $this->reduceArgumentPayloadAction->reduce($argument)
                );
            }

            return $arguments;
        }

        $arguments = array_map(
            fn ($argument) => $this->reduceArgumentPayloadAction->reduce($argument),
            $frame->arguments,
        );

        $argumentsCount = count($arguments);

        foreach ($parameters as $index => $parameter) {
            if ($index + 1 > $argumentsCount) {
                $parameter->defaultValueUsed();
            } else {
                $parameter->setReducedArgument(
                    $parameter->isVariadic
                        ? new VariadicReducedArgument(array_slice($arguments, $index))
                        : $arguments[$index]
                );
            }

            $parameters[$index] = $parameter->toArray();
        }

        return $parameters;
    }

    /** @return null|Array<\Spatie\FlareClient\Arguments\ProvidedArgument> */
    protected function getParameters(SpatieFrame $frame): ?array
    {
        try {
            $reflection = null !== $frame->class
                ? new \ReflectionMethod($frame->class, $frame->method)
                : new \ReflectionFunction($frame->method);
        } catch (\ReflectionException) {
            return null;
        }

        return array_map(
            fn (ReflectionParameter $reflectionParameter) => ProvidedArgument::fromReflectionParameter($reflectionParameter),
            $reflection->getParameters(),
        );
    }
}
