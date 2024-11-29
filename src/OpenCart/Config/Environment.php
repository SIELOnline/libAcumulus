<?php
/**
 * @noinspection PhpMultipleClassDeclarationsInspection OC3 has many double class definitions
 * @noinspection PhpUndefinedClassInspection Mix of OC4 and OC3 classes
 * @noinspection PhpUndefinedNamespaceInspection Mix of OC4 and OC3 classes
 */

declare(strict_types=1);

namespace Siel\Acumulus\OpenCart\Config;

use Siel\Acumulus\Config\Environment as EnvironmentBase;
use Siel\Acumulus\OpenCart\Helpers\Registry;

use function is_object;

use const Siel\Acumulus\Version;

/**
 * Defines the OpenCart web shop specific environment.
 */
class Environment extends EnvironmentBase
{
    protected function setShopEnvironment(): void
    {
        // Module has same version as library.
        $this->data['moduleVersion'] = Version;
        // Defined in [admin/]index.php
        $this->data['shopVersion'] = VERSION;
    }

    /**
     * @noinspection PhpMissingParentCallCommonInspection
     */
    protected function executeQuery(string $query): array
    {
        $result = $this->getDb()->query($query);
        return is_object($result) ? $result->rows : [];
    }

    /**
     * Helper method to get the db object.
     *
     * @return \Opencart\System\Library\DB|\DB
     */
    protected function getDb()
    {
        return Registry::getInstance()->db;
    }
}
