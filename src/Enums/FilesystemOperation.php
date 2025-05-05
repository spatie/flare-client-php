<?php

namespace Spatie\FlareClient\Enums;

enum FilesystemOperation: string
{
    case Path = 'path';
    case Exists = 'exists';
    case Get = 'get';
    case Put = 'put';
    case Prepend = 'prepend';
    case Append = 'append';
    case Delete = 'delete';
    case Copy = 'copy';
    case Move = 'move';
    case Size = 'size';
    case Files = 'files';
    case Directories = 'directories';
    case MakeDirectory = 'make_directory';
    case DeleteDirectory = 'delete_directory';
    case Other = 'other';
    case Url = 'url';
}
