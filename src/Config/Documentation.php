<?php
/**
 * The Config namespace contains configuration related classes.
 *
 * Configuration classes handle settings and features of your webshop and
 * contains the following classes:
 * - {@see Config}: Retrieving and storing settings used by this library.
 * - {@see ConfigStore}: Retrieving and storing the configuration values in the
 *   webshop's config sub system.
 * - {@see ShopCapabilities}: Provides information about the capabilities of
 *   the webshop.
 *
 * When implementing a new extension, you must override:
 * - {@see ConfigStore}
 * - {@see ShopCapabilities}
 */
namespace Siel\Acumulus\Config;
