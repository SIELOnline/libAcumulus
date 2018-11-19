<?php
/**
 * The Helpers namespace contains helper classes.
 *
 * Helper classes provide useful general features that do not belong to more
 * specific namespaces.
 *
 * Roughly, the features can be divided into these categories:
 * - Dependency injection or object instantiation:
 *     - {@see Container}
 * - Translation:
 *     - {@see Translator}
 *     - {@see TranslationCollection}
 * - Logging:
 *     - {@see Log}
 * - E-mail:
 *     - {@see Mailer}
 * - Form handling:
 *     - {@see Form}
 *     - {@see FormMapper}
 *     - {@see FormRenderer}
 * - Utilities:
 *     - {@see Countries}: countries, country codes, and fiscal EU countries.
 *     - {@see Number}: Comparing floats for equality given an error margin.
 *     - {@see Requirements}: Checks for additional (to most webshop)
 *       requirements.
 *     - {@see Token}: Replaces token definitions in string with their value.
 *
 * When implementing a new extension, you need to override:
 * - {@see Mailer}
 * - Either {@see FormMapper} or {@see FormRenderer}
 *
 * And you probably want to override:
 * - {@see Log}
 *
 * And you may want to provide a:
 * - ModuleSpecificTranslations (extension specific translations overrides).
 *
 * If you want to override form functionality, you need to override the actual
 * form classes {@see ConfigForm}, {@see AdvancedConfigFrom}, and
 * {@see BatchForm}, not {@see Form}.
 */
namespace Siel\Acumulus\Helpers;
