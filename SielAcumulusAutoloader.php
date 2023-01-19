<?php

declare(strict_types=1);

/**
 * Registers an autoloader for the Siel\Acumulus namespace.
 *
 * Thanks to https://gist.github.com/mageekguy/8300961
 */
class SielAcumulusAutoloader
{
  /**
   * Registers an autoloader for the Siel\Acumulus namespace.
   *
   * As not all web shops support autoloading based on namespaces or have
   * other glitches, e.g. expecting lower cased file names, we define our own
   * autoloader. If the module cannot use the autoloader of the web shop, this
   * method should be called when bootstrapping the module.
   *
   * Thanks to https://gist.github.com/mageekguy/8300961
   */
    public static function register(): void
    {
        // In some shops (OpenCart1) there's not one central entry point, and
        // we may risk registering twice.
        static $hasBeenRegistered = false;

        if (!$hasBeenRegistered) {
            $dir = __DIR__ . '/src/';
            $ourNamespace = 'Siel\\Acumulus\\';
            $ourNamespaceLen = strlen($ourNamespace);
            $autoloadFunction = function ($class) use ($ourNamespace, $ourNamespaceLen, $dir) {
                if (strncmp($class, $ourNamespace, $ourNamespaceLen) === 0) {
                    $fileName = $dir . str_replace('\\', DIRECTORY_SEPARATOR, substr($class, $ourNamespaceLen)) . '.php';
                    if (is_readable($fileName)) {
                        include($fileName);
                    }
                }
            };
            // Prepend this autoloader: it will not throw, nor warn, while the
            // shop specific autoloader might do so.
            $hasBeenRegistered = spl_autoload_register($autoloadFunction, true, true);
        }
    }
}
