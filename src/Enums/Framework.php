<?php

namespace Spatie\FlareClient\Enums;

enum Framework: string
{
    case Laravel = 'laravel';
    case Lumen = 'lumen';
    case Symfony = 'symfony';
    case WordPress = 'wordpress';
    case Magento = 'magento';
    case Drupal = 'drupal';
    case Joomla = 'joomla';
    case Craft = 'craft';
}
