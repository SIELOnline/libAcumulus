<?php
/**
 * @noinspection PhpUnused
 */

namespace Siel\Acumulus\Helpers;

/**
 * Documentation for the Helpers namespace
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
 * ### Note to developers
 * When implementing a new extension, you must override the `abstract` classes:
 * - {@see Mailer} to hook into the mail-sending system of your environment.
 * - {@see FormMapper}, but only when your environment proposes its own form API
 *   and you want to map to that
 *
 * And you probably want to override:
 * - {@see Log} to hook into the logging system of your environment.
 * - {@see FormHelper} to provide some form handling utilities that are done in
 *   a special way in your environment.
 * - {@see FormRenderer} when you have to render forms yourself because your
 *   environment does not have its own form API.
 * - {@see ModuleSpecificTranslations} to provide translation overrides specific
 *   for your environment.
 *
 * And you may have to override:
 * - {@see Token} when this base Token class is not able
 *   to extract property values from your domain objects like an Order,
 *   Customer, etc.
 *
 * And you may want to override:
 * - {@see Translator} if you want to hook it to your
 *   environments own translation system.
 * - {@see Requirements} to add additional requirements.
 *
 * If you want to override form functionality, you need to override the actual
 * form classes {@see ConfigForm}, {@see AdvancedConfigFrom}, and
 * {@see BatchForm}, not {@see Form}.
 */
interface _Documentation {}
