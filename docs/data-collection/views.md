---
title: Views 
---


Flare can collect information about the views being rendered in your application. This includes:

- The view name
- The view path
- The view data

This functionality is enabled by default, but you can disable it by calling `ignoreViews()` on the Flare config:

```php
$config->ignoreViews();
```

You can configure the maximum number of views tracked while collecting data in the case of an error as such:

```php
$config->collectViews(maxItemsWithErrors: 10);
```

## Collecting views

We cannot automatically collect views in the framework-agnostic version of the package. You can manually add views as such:

```php
$flare->view()->recordRendering(
    name: 'my-view',
    data: [
        'name' => 'Spatie',
    ],
    file: '/path/to/view.blade.php',
);

// Render your view

$flare->view()->recordRendered();
``` 