<?php
/**
 * @noinspection PhpMissingStrictTypesDeclarationInspection
 * @noinspection PhpUnused
 */

namespace Siel\Acumulus\Shop;

/**
 * Documentation for the Shop namespace
 *
 * The Shop namespace contains the high level functionality of this library.
 *
 * Roughly, the features can be divided into these categories:
 * - Models:
 *     - {@see AcumulusEntry}: Stores information about Acumulus entries for
 *       orders and refunds of this shop.
 * - Managers:
 *     - {@see AcumulusEntryManager}: Manages {@see AcumulusEntry} objects.
 *     - {@see InvoiceManager}: Manages invoice handling.
 * - Forms:
 *     - {@see ConfigForm}: the configuration form.
 *     - {@see AdvancedConfigForm}: The advanced configuration form.
 *     - {@see BaseConfigForm}: A base class for the 2 configuration forms.
 *     - {@see BatchForm}: The form to manually send invoice data to Acumulus.
 *     - {@see ConfirmUninstallForm}: A popup to ask for confirmation that the
 *       data may be deleted. Not really used yet
 *     - {@see InvoiceStatusForm}: An information box or tab on an order screen
 *       informing the user about the status of the Acumulus invoice related to
 *       the actual order.
 *     - {@see RegisterForm}: Displays the "Register" form to create a new
 *       Acumulus account.
 *     - {@see ActivateSupportForm}: Displays the "Activate support" form.
 *     - {@see AboutForm}: Displays the "About" information that is present on
 *       most other forms.
 *     - {@see RatePluginForm}: Displays an message on the backend to ask for a
 *       review on the webshop specific marketplace.
 *
 * When implementing a new extension, you must override the abstract managers:
 * - {@see AcumulusEntryManager}
 * - {@see InvoiceManager}
 *
 * And you may have to override the following model and forms:
 * - {@see AcumulusEntry} just to override the column names.
 *
 * You probably should not have to override any of the forms. Currently, only
 * the PrestaShop namespace has such an override:
 * {@see \Siel\Acumulus\PrestaShop\Shop\ConfigForm}.
 */
interface _Documentation
{
}
