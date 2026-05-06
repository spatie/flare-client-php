---
title: Stacktrace arguments 
---


When an error occurs in your application, Flare will send the stacktrace of the error to Flare. This stacktrace contains the file and line number where the error occurred and the argument values passed to the function or method that caused the error.

These argument values have been significantly reduced to make them easier to read and reduce the amount of data sent to Flare, which means that the arguments are not always complete. To see the full arguments, you can always use a [glow](/docs/laravel/data-collection/glows) to send the whole parameter to Flare.

For example, let's say you have the following Carbon object:

```php
new DateTime('2020-05-16 14:00:00', new DateTimeZone('Europe/Brussels'))
```

Flare will automatically reduce this to the following:

```
16 May 2020 14:00:00 +02:00
```

It is possible to disable argument reduction by calling `ignoreStackFrameArguments()` on the Flare config:

```php
$config->ignoreStackFrameArguments();
```

When Flare reduces arguments, the stacktrace should contain the arguments used with each frame. This is on some Linux versions of PHP not enabled by default, so Flare automatically sets the `zend.exception_ignore_args` ini setting to `0`.

It is possible to configure how these arguments are reduced. You can even implement your own reducers. By default, the following reducers are used:

- `BaseTypeArgumentReducer`
- `ArrayArgumentReducer`
- `StdClassArgumentReducer`
- `EnumArgumentReducer`
- `ClosureArgumentReducer`
- `DateTimeArgumentReducer`
- `DateTimeZoneArgumentReducer`
- `SymphonyRequestArgumentReducer`

You can use your own set of reducers as such:

```php
$config->collectStackFrameArguments([
    MyCustomArgumentReducer::class,
]);
```

When you want to extend the default set of reducers, you can do the following:

```php
$config->collectStackFrameArguments([
    ...FlareConfig::defaultArgumentReducers(),
    MyCustomArgumentReducer::class,
]);
```

## Implementing your reducer

Each reducer implements `Spatie\Backtrace\Arguments\Reducers\ArgumentReducer`. This interface contains a single method, `execute`, which provides the original argument value:

```php
interface ArgumentReducer
{
    public function execute(mixed $argument): ReducedArgumentContract;
}
```

In the end, three types of values can be returned:

When the reducer could not reduce this type of argument value:

```php
return UnReducedArgument::create();
```

When the reducer could reduce the argument value, but a part was truncated due to the size:

```php
return new TruncatedReducedArgument(
    array_slice($argument, 0, 25), // The reduced value
    'array' // The original type of the argument
);
```

When the reducer could reduce the full argument value:

```php
return new TruncatedReducedArgument(
    $argument, // The reduced value
    'array' // The original type of the argument
);
```

For example, the `DateTimeArgumentReducer` from the example above looks like this:

```php
class DateTimeArgumentReducer implements ArgumentReducer
{
    public function execute(mixed $argument): ReducedArgumentContract
    {
        if (! $argument instanceof \DateTimeInterface) {
            return UnReducedArgument::create();
        }
        
        return new ReducedArgument(
            $argument->format('d M Y H:i:s p'),
            get_class($argument),
        );
    }
}
``` 
