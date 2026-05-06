---
title: Adding custom context 
---


When you send an error to Flare, we might not collect essential application information.

To provide more information, you can add custom context to your application, which will be sent along with every error that occurs in your application. This can be very useful if you want to provide key-value-related information that further helps you debug a possible error.

The Flare client allows you to set custom context items like this:

```php
// Get access to your registered Flare client instance
$flare->context('tenant', 'My-Tenant-Identifier');
```

So, the next time an exception occurs, this value will be sent along to Flare, and you can find it in the "Context" section.

It is also possible to send multiple context items at once:

```php
$flare->context([
    'tenant_id' => 'My-Tenant-Identifier',
    'tenant_name' => 'My-Tenant-Name'
]); 