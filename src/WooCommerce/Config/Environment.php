<?php

declare(strict_types=1);

namespace Siel\Acumulus\WooCommerce\Config;

use Acumulus;
use Siel\Acumulus\Config\Environment as EnvironmentBase;
use WP_Debug_Data;

/**
 * Defines the WooCommerce web shop specific environment.
 */
class Environment extends EnvironmentBase
{
    protected function setShopEnvironment(): void
    {
        /** @var \WooCommerce $woocommerce */
        global $wp_version, $woocommerce;
        $this->data['moduleVersion'] = Acumulus::create()->getVersionNumber();
        $this->data['shopVersion'] = isset($woocommerce) ? $woocommerce->version : 'unknown';
        $this->data['cmsName'] = 'WordPress';
        $this->data['cmsVersion'] = $wp_version;
    }

    protected function getDbVariables(): array
    {
        if ( ! class_exists( 'WP_Debug_Data' ) ) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-debug-data.php';
        }
        $variables['version_comment'] = WP_Debug_Data::get_mysql_var('version_comment');
        $variables['version'] = WP_Debug_Data::get_mysql_var('version');
        return $variables;
    }
}
