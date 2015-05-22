<?php
/**
 * @file This file registers an autoloader for the Siel namespace library.
 *
 * As not all web shops support autoloading based on namespaces or have other
 * glitches, like expecting lowercased fie names, we define our own autoloader.
 * This file should be loaded during bootstrapping of the extension that uses
 * this library.
 *
 * Thanks to https://gist.github.com/mageekguy/8300961
 */
namespace Siel;

// Prepend this autoloader: it will not throw, nor warn, while the shop specific
// autoloader might do so.
spl_autoload_register(function($class) {
    if (strpos($class, __NAMESPACE__) === 0) {
      @include(__DIR__ . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen(__NAMESPACE__))) . '.php');
    }
  }, FALSE, TRUE);
