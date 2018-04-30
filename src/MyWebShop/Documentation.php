<?php

/**
 * The MyWebShop namespace contains template/example code for developers who
 * wish to create an Acumulus extension for another WebShop.
 *
 * Things to do when developing an extension for another webshop:
 * - Rename the namespace MyWebShop to your webshop's name.
 * - Create an empty module according to the rules of your webshop.
 * - Add libAcumulus to your webshop, as a separate library or within the
 *   module.
 * - Have the namespace registered for autoloading. Via composer, if used by
 *   your webshop, or by some initializing code in your module that includes
 *   SielAcumulusAutoloader.php and calls the
 *   SielAcumulusAutoloader::register() function.
 * - Add uninstall code that removes the acumulus entry table.
 * - Complete {@see Siel\Acumulus\MyWebshop\Helpers\Log}.
 * - Complete {@see Siel\Acumulus\MyWebshop\Helpers\Mailer}.
 * - Complete {@see Siel\Acumulus\MyWebshop\Config\ConfigStore}.
 * - Complete {@see Siel\Acumulus\MyWebshop\Config\ShopCapabilities}
 *   (getTokenInfo() may be deferred until later).
 * - Choose between {@see Siel\Acumulus\MyWebshop\Helpers\FormMapper} and
 *   {@see Siel\Acumulus\MyWebshop\Helpers\FormRenderer}. A FormMapper is
 *   preferred, but your shop needs something like a Form API.
 * - Complete the FormMapper, if chosen. note: the FormRenderer will basically
 *   do its job without any changes made by you. Moreover, it is easier to adapt
 *   when your module is in a state where it can show the form on your screen.
 *   So better to wait with completing the FormRenderer.
 * - In your module's code, create menu-items, routes and/or controllers for the
 *   3 forms of this module: config, advanced config and batch. Basically these
 *   controllers should initialise the Container, get a form from it, and have
 *   it processed. Example code is not in this library but in the Webshop
 *   specific module parts for existing modules, these can all be found on
 *   github on https://github.com/SIELOnline.
 * - Add install/enable code that creates the acumulus entry table. If your
 *   webshop expects separate (sql) scripts for this, add it over there, if not,
 *   have the module's install method/function initialize the Container, get the
 *   AcumulusEntryManager from it, and call the
 *   {@see Siel\Acumulus\MyWebShop\Shop\AcumulusEntryManager::install()} method.
 * - If needed (install not in a separate script), implement this install()
 *   method, and its counterpart uninstall() in your AcumulusEntryManager.
 *   Implement other missing abstract methods with empty stubs for now.
 *
 * You should now be able to install your module and have the Acumulus entry
 * table crated. After enabling the module you should also be able to go to the
 * config and advanced config form now.
 *
 * - If you have the config form on your screen and you have chosen to use a
 *   FormRenderer, you can now set properties and, where necessary, override
 *   methods to get the form to display as the other forms in your webshop's
 *   backend.
 * - Whether you use the FormRenderer or FormMapper, you might need some
 *   additional css, to get it all perfect. If so, create a css file and have it
 *   included on the form pages.
 * - In some cases, you may also need to override the 3 form classes to further
 *   get it right. E.g. in PrestaShop each fieldset legend also gets an icon,
 *   and this icon definition needed to be added to our form definitions so that
 *   it subsequently could be mapped by the FormMapper.
 * - Test the config and advanced config forms, you may still ignore
 *   ShopCapabilities::getTokenInfo().
 *
 * You should have a "working" module now that can create and drop the table on
 * install resp. uninstall and that has 2 working forms, including saving config
 * to your webshop's config store. The batch form should display and validation
 * should work, but submitting a correctly filled in form will give errors.
 *
 * You should now continue with the invoice handling and sending parts:
 * - Complete {@see Siel\Acumulus\MyWebshop\Shop\AcumulusEntry}.
 * - Complete {@see Siel\Acumulus\MyWebshop\Shop\AcumulusEntryManager}.
 * - Complete {@see Siel\Acumulus\MyWebshop\Shop\InvoiceManager}.
 * - Complete {@see Siel\Acumulus\MyWebshop\Invoice\Source}.
 * - Complete {@see Siel\Acumulus\MyWebshop\Invoice\Creator}.
 * - You will now know what objects should be documented in
 *   ShopCapabilities::getTokenInfo(), so correct that method now as well.
 *
 * This should give you a working batch form allowing you to extensively test
 * your Creator class tos ee if it handles all situations correctly. Test
 * situations like:
 * - Orders and refunds.
 * - Shipping costs and other fees, including free shipping.
 * - Reversed VAT orders (and refunds).
 * - Discounts and partial payments via gift vouchers.
 *
 * The last part is the automatic sending of invoice data to Acumulus:
 * - In your module's code, define event handlers for events that inform you
 *   about:
 *     - Order creation
 *     - Order (state) changes
 *     - Refund creation
 *     - (Shop) Invoice creation
 *     - (Shop) Invoice sending (to the client)
 *   and pass the info on to the correct handling method in this library:
 *     - Siel\Acumulus\Shop\InvoiceManager::sourceStatusChange()
 *     - Siel\Acumulus\Shop\InvoiceManager::invoiceCreate()
 *     - Siel\Acumulus\Shop\InvoiceManager::invoiceSend()
 *
 * This should conclude the development of an Acumulus extension for your
 * webshop based on this library. Any questions, support and suggestions
 * can be directed at support at burorader dot com.
 */
namespace Siel\Acumulus\MyWebShop;


