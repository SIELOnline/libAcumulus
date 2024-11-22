<?php

declare(strict_types=1);

namespace Siel\Acumulus\WooCommerce\Config;

use SensitiveParameter;
use Siel\Acumulus\Config\ConfigStore as BaseConfigStore;

/**
 * Implements the connection to the WordPress config component.
 */
class ConfigStore extends BaSeConfigStore
{
    public function load(): array
    {
      return get_option($this->configKey, []);
    }

    public function save(#[SensitiveParameter] array $values): bool
    {
        // WP: update_option() also returns false when there are no changes. We
        // want to return true, so we perform the same check as update_option()
        // before calling update_option().
        $oldValues = get_option($this->configKey);
        if ($values === $oldValues || maybe_serialize($values) === maybe_serialize($oldValues)) {
          return true;
        }
        return update_option($this->configKey, $values);
    }
}
