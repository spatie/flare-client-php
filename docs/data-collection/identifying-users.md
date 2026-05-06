---
title: Identifying users 
---


When a user is logged into your application and an error/trace occurs, helpful information about the user can be sent to Flare.

To do this, a `UserAttributesProvider` should be created as such:

```php
use Spatie\FlareClient\AttributesProviders\UserAttributesProvider;

class CustomUserAttributesProvider extends UserAttributesProvider
{
    public function id(mixed $user): string|int|null
    {
        return $user->id;
    }

    public function fullName(mixed $user): string|null
    {
        return "{$user->first_name} {$user->last_name}";
    }

    public function email(mixed $user): string|null
    {
        return $user->email;
    }

    public function attributes(mixed $user): array
    {
        return [
            'team_id' => $user->team_id
        ];
    }
}
```

Implementing all these methods is not required; you can mix and match the ones you need.

The `attributes` method can return a key-value array with extra information about the user other than its name, email, or ID.

The custom provider then should be registered within the Flare config as such:

```php
$config->userAttributesProvider(CustomUserAttributesProvider::class)
```

In order to provide the currently logged-in user to Flare from the request, you should also create a custom `RequestAttributesProvider`:

```php
use Spatie\FlareClient\AttributesProviders\RequestAttributesProvider;

class CustomRequestAttributesProvider extends RequestAttributesProvider
{
    public function getUser(mixed $request): mixed
    {
        return $request->user();
    }
}
```

This custom request provider should also be registered within the Flare config:

```php
$config->requestAttributesProvider(CustomRequestAttributesProvider::class)
```
