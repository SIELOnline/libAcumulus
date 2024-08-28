<?php

declare(strict_types=1);

namespace Siel\Acumulus\Completors\Line;

use Siel\Acumulus\Api;
use Siel\Acumulus\Completors\BaseCompletorTask;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Data\AcumulusObject;
use Siel\Acumulus\Data\Line;
use Siel\Acumulus\Data\LineType;
use Siel\Acumulus\Meta;

/**
 * CompleteByConfig adds configuration based values to item lines.
 */
class CompleteByConfig extends BaseCompletorTask
{
    /**
     * Adds some values based on configuration.
     *
     * The corresponding value from config is added to the following fields:
     * - nature (string, Api::Nature_Product, Api::Nature_Service).
     *
     * @param \Siel\Acumulus\Data\Line $acumulusObject
     */
    public function complete(AcumulusObject $acumulusObject, ...$args): void
    {
        $this->completeNature($acumulusObject);
    }

    protected function completeNature(Line $line): void
    {
        // @todo: for now only item lines are handled as this is a straight "copy" of code
        //    from the legacy Creator. Probably another completor exists for "other" lines
        //    which eventually might be merged here.
        if (empty($line->nature) && ($line->metadataGet(Meta::SubType) === LineType::Item)) {
            switch ($this->configGet('nature_shop')) {
                case Config::Nature_Products:
                    $line->nature = Api::Nature_Product;
                    break;
                case Config::Nature_Services:
                    $line->nature = Api::Nature_Service;
                    break;
            }
        }
    }
}
