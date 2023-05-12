<?php
/**
 * @noinspection PhpMissingStrictTypesDeclarationInspection
 * @noinspection PhpUnused
 * @noinspection PhpUndefinedNamespaceInspection
 */

namespace Siel\Acumulus\TestWebShop;

/**
 * The TestWebShop namespace contains code for a Test web shop used with the unit
 * tests for the libAcumulus library.
 *
 * The libAcumulus library also contains a MyWebShop namespace with template or
 * example code for developers who wish to create an Acumulus extension for
 * another WebShop than already offered. Though its first use is for unit
 * testing, the TestWebShop namespace may also provide more insight into what to
 * do when adding support for a new web shop.
 *
 * Things to do when developing an extension for another web shop:
 * - Complete {@see \Siel\Acumulus\TestWebShop\Helpers\Log}.
 * - Complete {@see \Siel\Acumulus\TestWebShop\Helpers\Mailer}.
 * - Complete {@see \Siel\Acumulus\TestWebShop\Config\ConfigStore}.
 * - Complete {@see \Siel\Acumulus\TestWebShop\Config\ShopCapabilities}
 *   (getTokenInfo() may be deferred until later).
 *
 * You should now continue with the invoice handling and sending parts:
 * - Complete {@see \Siel\Acumulus\TestWebShop\Shop\AcumulusEntry}.
 * - Complete {@see \Siel\Acumulus\TestWebShop\Shop\AcumulusEntryManager}.
 * - Complete {@see \Siel\Acumulus\TestWebShop\Shop\InvoiceManager}.
 * - Complete {@see \Siel\Acumulus\TestWebShop\Invoice\Source}.
 * - Complete {@see \Siel\Acumulus\TestWebShop\Invoice\Creator}.
 * - You will now know what objects should be documented in
 *   ShopCapabilities::getTokenInfo(), so correct that method now as well.
 */
interface _Documentation {}
