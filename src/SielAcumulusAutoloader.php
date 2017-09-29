<?php
/**
 * @file Registers an autoloader for the Siel\Acumulus namespace.
 *
 * As not all web shops support auto-loading based on namespaces or have other
 * glitches, eg. expecting lower cased file names, we define our own
 * autoloader. If the module cannot use the autoloader of the web shop, this
 * file should be loaded during bootstrapping of the module.
 *
 * Thanks to https://gist.github.com/mageekguy/8300961
 */
namespace Siel\Acumulus;

spl_autoload_register(
    function ($class) {
        if (strpos($class, __NAMESPACE__ . '\\') === 0) {
            $fileName = __DIR__ . str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen(__NAMESPACE__))) . '.php';
            // Checking if the file exists prevent warnings in OpenCart1 where
            // using just @include(...) did not help prevent them.
            if (is_readable($fileName)) {
                include($fileName);
            }
        }
    },
	// Do not throw an exception.
    false,
    // Prepend this autoloader: it will not throw, nor warn, while the shop
    // specific autoloader might do so.
    true);
