---
title: Censoring collected data 
---

The Flare client collects a large amount of data within your application. It is possible to configure this by configuring the Flare client:

```php
$config = FlareConfig::make('YOUR_API_KEY')->useDefaults();

Flare::make($config)->registerFlareHandlers();
```

We've initialised the config with the Flare defaults, but you can mix and match your own config.

## Anonymising IP's

By default, the Flare client collects information about your application users' IP addresses. To disable this information, call the `censorClientIps()` method on your Flare config instance.

```php
$config->censorClientIps();
```

## Censoring request/response body fields

When Flare collects information about a web request or response, the Flare client passes on any request/response fields present in the body.

Sometimes, such as on a login page, these request fields may contain a password you don't want to send to Flare.

To censor specific fields' values, you can call `censorBodyFields`. You should pass the names of the fields you wish to censor:

```php
$config->censorBodyFields('password');
```

This will replace the value of any body fields named "password" with the value "<CENSORED>".

By default, Flare will censor the password and password_confirmation fields.


### Censoring nested body fields

If you have nested body fields that you want to censor, you can use dot notation to specify the fields:

```php
$config->censorBodyFields('user.password');
```

You can also use an asterisk (*) as a wildcard to censor multiple fields at once:

```php
$config->censorBodyFields('users.*.password');
```

## Censoring request/response headers

When Flare collects information about a web request or response, the Flare client passes on any request/response headers present.

Just like with the body fields, these headers can be censored. You can do this by calling `censorHeaders` on the Flare Config:

```php
$config->censorHeaders('X-API-KEY');
```

When doing so, the value of the headers will be changed to "<CENSORED>" when sent to Flare.

By default, Flare will censor the following headers:

- API-KEY
- Authorization
- Cookie
- Set-Cookie
- X-CSRF-TOKEN
- X-XSRF-TOKEN

## Censoring cookies

When Flare collects information about a web request or response, the Flare client passes on any cookies present.

To censor all cookies, you can call `censorCookies` on the Flare Config:

```php
$config->censorCookies();
```

## Censoring the current request session

When Flare collects information about a web request, the Flare client passes on any session data present.

To censor all session data, you can call `censorSession` on the Flare Config:

```php
$config->censorSession();
```
