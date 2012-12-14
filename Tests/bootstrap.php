<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

// composer autoloader
if (file_exists($loader = __DIR__.'/../../../../../autoload.php')) {
    require_once $loader;
}


if (!class_exists('Stamina\\PhpQuery\\Sanitizer')) {
    spl_autoload_register(function ($class) {
        if (0 === strpos(ltrim($class, '/'), 'Stamina\\PhpQuery')) {
            if (file_exists($file = __DIR__.'/../'.substr(strtr($class, '\\_', '//'), strlen('Stamina\\PhpQuery')).'.php')) {
                require_once $file;
            }
        }
    });
}
