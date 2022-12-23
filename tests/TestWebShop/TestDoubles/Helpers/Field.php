<?php
/**
 * @noinspection PhpMissingDocCommentInspection
 * @noinspection PhpFuncGetArgCanBeReplacedWithParamInspection
 */

declare(strict_types=1);

namespace Siel\Acumulus\Tests\TestWebShop\TestDoubles\Helpers;

use Siel\Acumulus\Helpers\Field as BaseField;
use Siel\Acumulus\Helpers\Log;

/**
 * Extends the real variable field expander to allow to inspect intermediate
 * results.
 */
class Field extends BaseField
{
    public string $stopAt;
    public array $trace;

    public function __construct(Log $log, string $stopAt = '')
    {
        parent::__construct($log);
        $this->stopAt = $stopAt;
        $this->trace = [];
    }

    protected function variableFieldMatch(array $matches): string
    {
        $this->trace[__FUNCTION__] ??= [];
        $this->trace[__FUNCTION__][] = $matches[1];
        if ($this->stopAt === __FUNCTION__) {
            return end($this->trace[__FUNCTION__]);
        }
        return parent::variableFieldMatch($matches);
    }

    protected function expandVariableField(string $variableField)
    {
        $this->trace[__FUNCTION__] ??= [];
        $this->trace[__FUNCTION__][] = func_get_arg(0);
        if ($this->stopAt === __FUNCTION__) {
            return end($this->trace[__FUNCTION__]);
        }
        return parent::expandVariableField($variableField);
    }

    protected function expandAlternative(string $propertyAlternative)
    {
        $this->trace[__FUNCTION__] ??= [];
        $this->trace[__FUNCTION__][] = func_get_arg(0);
        if ($this->stopAt === __FUNCTION__) {
            return null; // Ensures that all alternatives are processed.
        }
        return parent::expandAlternative($propertyAlternative);
    }

    protected function expandSpaceConcatenatedProperty(string $spaceConcatenatedProperty): array
    {
        $this->trace[__FUNCTION__] ??= [];
        $this->trace[__FUNCTION__][] = func_get_arg(0);
        if ($this->stopAt === __FUNCTION__) {
            return ['type' => static::TypeProperty, 'value' => end($this->trace[__FUNCTION__])];
        }
        return parent::expandSpaceConcatenatedProperty($spaceConcatenatedProperty);
    }

    protected function expandSingleProperty(string $singleProperty): array
    {
        $this->trace[__FUNCTION__] ??= [];
        $this->trace[__FUNCTION__][] = func_get_arg(0);
        if ($this->stopAt === __FUNCTION__) {
            return ['type' => static::TypeProperty, 'value' => end($this->trace[__FUNCTION__])];
        }
        return parent::expandSingleProperty($singleProperty);
    }

    protected function expandLiteral(string $singleProperty): string
    {
        $this->trace[__FUNCTION__] ??= [];
        $this->trace[__FUNCTION__][] = func_get_arg(0);
        if ($this->stopAt === __FUNCTION__) {
            return end($this->trace[__FUNCTION__]);
        }
        return parent::expandLiteral($singleProperty);
    }

    protected function expandPropertyInObject(string $propertyInObject)
    {
        $this->trace[__FUNCTION__] ??= [];
        $this->trace[__FUNCTION__][] = func_get_arg(0);
        if ($this->stopAt === __FUNCTION__) {
            return end($this->trace[__FUNCTION__]);
        }
        return parent::expandPropertyInObject($propertyInObject);
    }

    protected function expandProperty(string $propertyName)
    {
        $this->trace[__FUNCTION__] ??= [];
        $this->trace[__FUNCTION__][] = func_get_arg(0);
        if ($this->stopAt === __FUNCTION__) {
            return end($this->trace[__FUNCTION__]);
        }
        return parent::expandProperty($propertyName);
    }
}
