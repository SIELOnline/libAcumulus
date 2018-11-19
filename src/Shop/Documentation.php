<?php
/**
 * The Shop namespace contains the high level functionality of this library.
 *
 * Roughly, the features can be divided into these categories:
 * - Models:
 *     - {@see AcumulusEntry}: Stores information about Acumulus entries for
 *       orders and refunds of this shop.
 * - Managers:
 *     - {@see AcumulusEntryManager}: Managing of Acumulus entries
 *     - {@see InvoiceManager}: Manages invoice handling
 * - Forms:
 *     - {@see ConfigForm}: the configuration form.
 *     - {@see AdvancedConfigForm}: The advanced configuration form.
 *     - {@see BaseConfigForm}: A base class for the 2 configuration forms.
 *     - {@see BatchForm}: The form to manually send invoice data to Acumulus.
 *     - {@see ConfirmUninstallForm}: A popup to ask for confirmation that the
 *       data may be deleted. Not really used yet
 *     - {@see \Siel\Acumulus\WooCommerce\Shop\ShopOrderOverviewForm}: An
 *       information box on an order screen informing the user about the status
 *       of the Acumulus invoice related to the actual order. For now, only a
 *       proof of concept is available for WooCommerce. The name of the form
 *       and its features are pretty sure to be changed in the near future.
 *
 * When implementing a new extension, you must override the abstract managers:
 * - {@see AcumulusEntryManager}
 * - {@see InvoiceManager}
 *
 * And you may have to override the model and forms:
 * - {@see AcumulusEntry} just to define the column names.
 * - {@see ConfigForm}
 * - {@see AdvancedConfigForm}
 * - {@see BaseConfigForm}
 * - {@see BatchForm}
 * - {@see ConfirmUninstallForm}
 * - {@see \Siel\Acumulus\WooCommerce\Shop\ShopOrderOverviewForm} in the near
 *   future.
 */
namespace Siel\Acumulus\Shop;
