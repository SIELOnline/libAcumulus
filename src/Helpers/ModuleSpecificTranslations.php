<?php

declare(strict_types=1);

namespace Siel\Acumulus\Helpers;

/**
 * Contains plugin specific overrides.
 *
 * This base class, obviously, does not override any translation, but override
 * this class, e.g. to use terminology specific for your webshop environment.
 *
 * A typical example is the word for an extension: plugin, module, extension, or
 * whatever it is called in your environment.
 *
 * @noinspection PhpUnused
 */
class ModuleSpecificTranslations extends TranslationCollection
{
}
