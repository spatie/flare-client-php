---
title: Limits 
---


When tracing an application, the amount of data can grow very large. We have a couple of limits in place to limit the amount of data sent to Flare.

- max spans per trace: 512
- max span events per span: 128
- max attributes per span: 128
- max attributes per span event: 128

It is possible to lower or raise these limits as such:

```php
$config->traceLimits(new TraceLimits(maxSpans: 1024));
``` 