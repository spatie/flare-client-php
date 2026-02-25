<?php

if (file_exists($autoload = __DIR__.'/vendor/autoload.php')) {
    require $autoload;
} elseif (file_exists($autoload = 'phar://flare-daemon.phar/vendor/autoload.php')) {
    require $autoload;
}
