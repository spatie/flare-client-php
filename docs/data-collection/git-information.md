---
title: Git information 
---


When you're using Git to manage your code, and the git history is available from the server you're running your application on, Flare will automatically collect:

- The commit hash
- The commit message
- The current branch
- The git repository remote URL

In the past this information was collected by calling the `git` command directly from PHP which could lead to performance issues on some systems. Next to the default collected data the legacy method also collected:

- The latest tag
- Whether the repository is dirty (uncommitted changes)

It can still be enabled by changing your config like this:

```php
$config->collectGitInfo(useProcess: true);
```

It is possible to disable this behaviour by adapting your config like this:

```php
$config->ignoreGitInfo();
``` 
