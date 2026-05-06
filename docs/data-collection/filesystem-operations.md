---
title: Filesystem operations 
---


Flare can collect information about the filesystem operations that your application executes.

You can disable this behaviour by calling `ignoreFilesystem()` on the Flare config:

```php
$config->ignoreFilesystemOperations();
```

You can configure the maximum number of filesystem operations tracked while collecting data in the case of an error as such:

```php
$config->collectFilesystemOperations(maxItemsWithErrors: 10);
```

## Collecting filesystem operations

We cannot automatically collect filesystem operations in the framework-agnostic version of the package. You can manually add filesystem operations as such:

```php
use Spatie\FlareClient\Enums\FilesystemOperation;

$flare->filesystem()->recordOperationStart(
    operation: FilesystemOperation::Exists,
    attributes:  [  
        'filesystem.path' => '/path/to/file.txt',
        
    ]
);
```

When the operation has finished, you should call the `recordOperationEnd` method:

```php
$flare->filesystem()->recordOperationEnd(
    operation: FilesystemOperation::Exists,
    attributes:  [  
        'filesystem.exists' => true, 
    ]
);
```

To make things a bit easier, we've provided a few helper methods to make it easier to record common filesystem operations:

### Path

Gets the full filesystem path of a local path:

```php
$flare->filesystem()->recordPath('/path/to/file.txt');
```

When the operation has finished, you should call the `recordOperationEnd` method, you can pass a `filesystem.full_path` attribute with the full path of the file.

### Exists

Checks if a file exists:

```php
$flare->filesystem()->recordExists('/path/to/file.txt');
```

When the operation has finished, you should call the `recordOperationEnd` method. You can pass a `filesystem.exists` attribute with the operation's result.

### Get

Gets the contents of a file:

```php
$flare->filesystem()->recordGet('/path/to/file.txt');
```

When the operation has finished, you should call the `recordOperationEnd` method, you can pass a `filesystem.contents.size` attribute with the size of the contents.

### Put

Writes the contents to a file:

```php
$flare->filesystem()->recordPut('/path/to/file.txt', 'Hello World');
``` 


When the operation has finished, you should call the `recordOperationEnd` method. You can pass a `filesystem.operation.success` attribute with the operation's result.

### Prepend

Writes the contents to a file at the beginning:

```php
$flare->filesystem()->recordPrepend('/path/to/file.txt', 'Hello World');
```

When the operation has finished, you should call the `recordOperationEnd` method. You can pass a `filesystem.operation.success` attribute with the operation's result.

### Append

Writes the contents to a file at the end:

```php
$flare->filesystem()->recordAppend('/path/to/file.txt', 'Hello World');
```

When the operation has finished, you should call the `recordOperationEnd` method. You can pass a `filesystem.operation.success` attribute with the operation's result.

### Delete

Deletes a file:

```php
$flare->filesystem()->recordDelete('/path/to/file.txt');
```

When the operation has finished, you should call the `recordOperationEnd` method. You can pass a `filesystem.operation.success` attribute with the operation's result.

### Copy

Copies a file:

```php
$flare->filesystem()->recordCopy('/path/to/file.txt', '/path/to/file-copy.txt');
```

When the operation has finished, you should call the `recordOperationEnd` method. You can pass a `filesystem.operation.success` attribute with the operation's result.

### Move

Moves a file:

```php
$flare->filesystem()->recordMove('/path/to/file.txt', '/path/to/file-move.txt');
```

When the operation has finished, you should call the `recordOperationEnd` method. You can pass a `filesystem.operation.success` attribute with the operation's result.

### Size

Gets the size of a file:

```php
$flare->filesystem()->recordSize('/path/to/file.txt');
```

When the operation has finished, you should call the `recordOperationEnd` method, you can pass a `filesystem.contents.size` attribute with the file size.

### Files

Gets the files in a directory:

```php
$flare->filesystem()->recordFiles('/path/to/directory');
```

When getting the files recursively, you can pass a `recursive` parameter to the `recordFiles` method:

```php
$flare->filesystem()->recordFiles('/path/to/directory', recursive: true);
```

When the operation has finished, you should call the `recordOperationEnd` method, and you can pass a `filesystem.found_paths` attribute with the paths of the files.

### Directories

Gets the directories in a directory:

```php
$flare->filesystem()->recordDirectories('/path/to/directory');
```

When getting the directories recursively, you can pass a `recursive` parameter to the `recordDirectories` method:

```php
$flare->filesystem()->recordDirectories('/path/to/directory', recursive: true);
```

When the operation has finished, you should call the `recordOperationEnd` method, and you can pass a `filesystem.found_paths` attribute with the paths of the directories.

### Make Directory

Creates a directory:

```php
$flare->filesystem()->recordMakeDirectory('/path/to/directory');
```

When the operation has finished, you should call the `recordOperationEnd` method. You can pass a `filesystem.operation.success` attribute with the operation's result.

### Delete Directory

Deletes a directory:

```php
$flare->filesystem()->recordDeleteDirectory('/path/to/directory');
```

When the operation has finished, you should call the `recordOperationEnd` method. You can pass a `filesystem.operation.success` attribute with the operation's result.