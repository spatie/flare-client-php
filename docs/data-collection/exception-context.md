---
title: Exception context 
---


Flare can collect extra context information added to an exception as such:

```php
use Exception;
use Spatie\FlareClient\Contracts\ProvidesFlareContext;

class ExceptionWithContext extends Exception implements ProvidesFlareContext
{
    public function context(): array
    {
        return [
            'key' => 'value',
        ];
    }
}
```

The context will be collected automatically when the exception is thrown. 