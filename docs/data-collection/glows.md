---
title: Glows 
---


Glows allow you to add little pieces of information that can later be found in chronological order in the "Debug" tab of your application when you debug an error or as events on a span when viewing a trace in performance monitoring.

Glows are like breadcrumbs that help you track down which parts of your code were executed.

The Flare PHP client allows you to add glows to your application like this:

```php
use Spatie\FlareClient\Enums\MessageLevels;

// Get access to your registered Flare client instance
$flare->record()->glow('This is a message from glow!', MessageLevels::Debug, func_get_args());
``` 