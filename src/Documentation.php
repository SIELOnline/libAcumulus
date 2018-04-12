<?php
/**
 * The Siel\Acumulus namespace contains libAcumulus, a PHP library that supports the Acumulus web service API.
 *
 * libAcumulus  was written to simplify development of client side code that communicates with this Acumulus web service and that resides within the ecosystem
 * of a webshop or CMS with an existing webshop extension. Therefore this library does not:
 * * contain controller like classes as they normally are ecosystem specific.
 * * directly output forms, views or messages as there are normally ecosystem specific ways for to do so.
 * * explicit install or update code, though it does contain code that can be called by installing or updating code from the system it resides in.
 *
 * It is currently used by the extensions for the webshop software of HikaShop,
 * Magento, PrestaShop, OpenCart, VirtueMart and WooCommerce, but was built to be
 * easily usable with other webshop/CMS software systems as well. The library part of the code of these extensions can also be found in this library under Siel\Acumulus\{webshop}
 *
 * \Siel\Acumulus has 5 sub namespaces
 * -----------------------------------
 * * \Siel\Acumulus\Config: configuration related classes.
 * * \Siel\Acumulus\Helpers: general utility classes.
 * * \Siel\Acumulus\Invoice: invoice handling related classes.
 * * \Siel\Acumulus\Shop: Entity handling, form handling, and managing (controller like) classes.
 * * \Siel\Acumulus\Web: API and communication related classes.
 *
 * More detail about these sub namespaces and their classes can be found in the documentation per sub-namespace.
 *
 * \Siel\Acumulus also contains sub namespaces per existing webshop/CMS implementation
 * -----------------------------------------------------------------------------------
 * * \Siel\Acumulus\Joomla (VirtueMart and HikaShop)
 * * \Siel\Acumulus\Magento
 * * \Siel\Acumulus\OpenCart
 * * \Siel\Acumulus\PrestaShop
 * * \Siel\Acumulus\WooCommerce
 *
 * These sub namespaces contain shop/CMS specific code.
 *
 * Some general principles used in this library
 * --------------------------------------------
 * * We use our own translation, see {@see \Siel\Acumulus\Helpers\Translator}.
 * * Dependency injection: our container is namespace based regarding finding the right class to instantiate and knows what arguments the constructors want,
 *   see {@see \Siel\Acumulus\Helpers\ContainerInterface} (todo).
 * * Support friendly: lots of logging, emailing, clear and explicative end user messages, built in dry-run, allows easy use of the API test mode.
 *
 * Invoice handling details
 * ------------------------
 * * The extension should support invoices for orders and refunds, the latter of course only if supported by the webshop.
 * * An event in the webshop signals that an invoice might have to be sent to Acumulus. The library currently supports the following types of events:
 *   order creation or status change, refund creation, invoice creation or invoice send to customer (if the webshop supports its own notion of invoices as clearly separate entities).
 *   Current support consists of: the configuration form allows to define at what order statuses and/or what events the invoice should be sent and to
 *   handle the status change to see if the invoice has to be sent and has not already be sent.
 * * Invoice creation: if the invoice should be sent a raw version of it has to be created. This raw version is based on the shop order and should be in the
 *   approximate format as Acumulus expects it to receive. This will be the most work when developing a new extension. See {@see \Siel\Acumulus\Invoice} for more detail (todo).
 * * Invoice completion: the raw invoice is a more or less direct conversion from the shop order to the Acumulus invoice format.
 *   The completion phase will ensure that it fully complies with the Acumulus format and will largely be done by code that is webshop agnostic
 *   and is thus already part of the library. In rare case you may have to override classes that participate in the completion phase to cater for specific situations.
 *   {@see \Siel\Acumulus\Invoice} for more detail abut the creation and completion phases (todo).
 * * Invoice sending: when the invoice is completed, it is sent by the \Siel\Acumulus\Shop\InvoiceManager that uses the \Siel\Acumulus\Web\Service for the actual communication.
 * * Result handling: when the invoice is sent, the results are processed. if sending it was successful, an \Siel\Acumulus\Shop\AcumulusEntry record is stored
 *   to prevent sending it again, if not a mail is sent informing the admin about any errors and/or warnings.
 *
 * Developing a new extension
 * --------------------------
 * If you want to develop an extension for another webshop these are the steps to follow:
 * * Use only PHP language constructs that were already available in PHP 5.3 and have not been deprecated since.
 *   Even though PHP5.3 is hardly used anymore, e.g. WordPress still validates on it when checking in code.
 * * We advice using PhpStorm. This IDE guards you against numerous errors that are simply to make in not strongly typed languages.
 * * Document all your code, especially parameter, return and variable types. This allows other developers and IDEs to better follow what is going on
 * * Use the inheritdoc tag where possible, only adding deviations form the base implementation.
 *
 * * Set up a new namespace that will contain all classes that extend, implement and override the functionality of the libAcumulus classes.
 *   The namespace should preferably contain levels for the CMS (if the webshop resides in a CMS), the webshop, and, if needed, the major version of the webshop.
 *   Use the MyWebShop example code for a quick start and lots of advice on what and what not to do (todo).
 * * Follow the detailed tips and advice from the MyWebShop example code to adapt it to the Webshop you are implementing the extension for.
 * * The code in the library contains almost no private members, allowing extension developers to override each and every part of the code as necessary.
 *   However, try to use this sparingly, following advice in the MyWebShop example code about which method to override and which not.
 * * When you are more or less finished with the library based part of your extension, you need to hook into your webshop.
 *   This part is very webshop specific but generally speaking you should:
 *     - Create a full fledged module adhering to the webshop architecture
 *     - Including install code that checks any (additional) requirements, creates the Acumulus entry table, and subscribes to the relevant events.
 *     - Including uninstall code that removes the event subscriptions and Acumulus entry table.
 *     - Initialization code that adds the library's namespace to the webshop autoloader, or, if no autoloader is present in the webshop or it cannot be used (not PSR4 compliant),
 *       defines an autoloader by including SielAcumulusAutoloader.php
 *       and calling the static SielAcumulusAutoloader::register() method.
 *     - Initialization code that creates the Container (passing in the correct arguments)
 *     - That defines and handles 3 admin form pages, the settings page, the advanced settings page, and the batch send page.
 *       The settings page may be the default extension settings page, that normally can be reached via something like
 *       "Modules - select Acumulus module - Configure".
 *       These pages/forms probably need their own menu-item, a route definition and/or a controller, a renderer part, a form submission part, and sometimes,
 *       depending on the webshop, a view or something like that.
 *     - Event handling code to forward the events to the library.
 *     - See existing code on https://github.com/SIELOnline, where all webshop specific projects are also viewable.
 *
 *  Note that:
 */
namespace Siel\Acumulus;
