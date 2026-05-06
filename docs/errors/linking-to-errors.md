---
title: Linking to errors 
---


Your application will likely display a minimal error page in production when a web request error occurs.

If a user sees this page and wants to report this error to you, the user usually only reports the URL and the time the error was seen.

You can display the UUID of the error sent to Flare to help your users pinpoint the error they saw.

You can do this by displaying the UUID returned by `Flare::sentReports()->latestUuid()` in your view. Optionally, you can use `Flare::sentReports()->latestUrl()` to get a link to the error in Flare. That link isn't publicly accessible; it is only visible to Flare users who can access the project on Flare.

Sometimes, multiple errors can be reported to Flare in a single request. To get a hold of the UUIDs of all sent errors, you can call `Flare::sentReports()->uuids()`. You can get links to all sent errors with `Flare::sentReports()->urls()`.

It is possible to search for specific errors in Flare using the UUID; you can find more information about that [here](/docs/flare/errors/searching-errors). 
