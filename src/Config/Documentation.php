<?php
/**
 * The Config namespace contains configuration related classes.
 *
 * Configuration classes handle settings and features of your web shop and
 * contains the following classes:
 * - {@see Config}: Retrieving and storing settings used by this library.
 * - {@see ConfigUpgrade}: Upgrades stored settings to a newer version.
 * - {@see ConfigStore}: Retrieving and storing the configuration values in the
 *   web shop's config sub system.
 * - {@see Environment}: Provides information about the environment of the
 *   library: shop version, cms version, database version, etc.
 * - {@see ShopCapabilities}: Provides information about the capabilities of
 *   the web shop.
 *
 * When implementing a new extension, you must override:
 * - {@see ConfigStore}
 * - {@see Environment}
 * - {@see ShopCapabilities}
 */
namespace Siel\Acumulus\Config;
