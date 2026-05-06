---
title: Console commands 
---


Flare can collect information about the console commands that are being executed. Whether an error happens during a command or you want to trace a long-running command, Flare will collect the following information:

- The command name
- The command arguments
- The exit code

It is possible to disable this behaviour by ignoring commands in the Flare config:

```php
$config->ignoreCommands();
```

You can configure the maximum number of commands tracked while collecting data in the case of an error as such:

```php
$config->collectCommands(maxItemsWithErrors: 3);
```

## Collecting commands

When you've enabled collecting command information and an error happens, the Flare client will automatically collect the command name and arguments.

During traces where multiple commands may be executed, you can manually add commands as such:

```php
$flare->command()->recordStart('my:command', ['--option' => 'value']);
```

If you want to keep track of the class of the command that was executed:

```php
$flare->command()->recordStart('my:command', ['--option' => 'value'], entryPointClass: MyCommand::class);
```

It is also possible to add extra attributes to the command:

```php
$flare->command()->recordStart('my:command', ['--option' => 'value'], attributes: [
    'process.pid' => 80
]);
```

When the command ends (or fails), you can call the `recordEnd` method:

```php
$flare->command()->recordEnd();
```

It is possible to provide the command exit code as such:

```php
$flare->command()->recordEnd(exitCode: 0);
```

Extra attributes can be added when the command ends as well:

```php
$flare->command()->recordEnd(attributes: [
    'process.pid' => 80
]);
``` 