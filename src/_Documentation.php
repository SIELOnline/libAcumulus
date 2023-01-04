<?php
/**
 * @noinspection PhpMissingStrictTypesDeclarationInspection
 * @noinspection PhpUnused
 */

namespace Siel\Acumulus;

/**
 * Documentation for the Acumulus application
 *
 * The \Siel\Acumulus namespace contains libAcumulus, a PHP library that
 * supports the Acumulus web service API.
 *
 * ### Some vocabulary
 *
 * - (Acumulus) invoice: the representation of invoice data as presented to
 *   Acumulus web service API and that can be used to create a new invoice in
 *   Acumulus.
 * - Shop invoice: The shop's representation of an invoice. Not all webshops
 *   support a fully fledged notion and storage of an invoice. Many see an order
 *   with price (and tax) info as an invoice.
 * - Source or invoice source: a webshop object that can serve as a source for
 *   the creation of an (Acumulus) invoice. This library supports orders and
 *   refunds (credit memos) as invoice source, the latter of course only as far
 *   as supported by the webshop itself.
 * - Extension: a way to extend the functionality of a webshop or CMS. Other
 *   words used for this concept are module, addon, plugin, and component.
 * - User: a person with administrative access rights to an installation of the
 *   webshop or CMS system. Clients/buyers/visitors are thus not users and this
 *   library will never present messages or any other texts to visitors.
 *
 * ### Introduction
 *
 * LibAcumulus was written to simplify development of client side code that
 * communicates with the Acumulus web service and that resides within the
 * ecosystem of a webshop or CMS with a webshop extension. Therefore, this
 * library does not:
 * - Contain controller like classes as they normally have to fit within the
 *   architecture of the webshop.
 * - Directly output forms, views or messages as normally webshop specific ways
 *   to do so exist.
 * - Contain explicit install or update code as normally webshop specific ways
 *   to invoke this exists. However, it does contain code that can be called by
 *   the installation or update code of the webshop.
 *
 * It is currently used by the extensions for the webshop software of HikaShop,
 * Magento, PrestaShop, OpenCart, VirtueMart and WooCommerce, but was built to
 * be easily usable with other webshop/CMS software systems as well. The library
 * part of the code for these extensions can also be found in this library under
 * Siel\Acumulus\\{webshop}.
 *
 * ### Sub namespaces
 *
 * \Siel\Acumulus contains 5 sub namespaces to logically group functionality:
 * - {@see \Siel\Acumulus\Config\_Documentation \Siel\Acumulus\Config}:
 *   configuration related classes.
 * - {@see \Siel\Acumulus\Helpers\_Documentation \Siel\Acumulus\Helpers}:
 *   general utility classes.
 * - {@see \Siel\Acumulus\Invoice\_Documentation \Siel\Acumulus\Invoice}:
 *   invoice handling related classes.
 * - {@see \Siel\Acumulus\Shop\_Documentation \Siel\Acumulus\Shop}:
 *   entity handling, form handling, and managing (controller like) classes.
 * - {@see \Siel\Acumulus\ApiClient\_Documentation \Siel\Acumulus\ApiClient}:
 *   web service and communication related classes.
 *
 * More detail about these sub namespaces and their classes can be found in the
 * documentation per sub-namespace or per class.
 *
 * ### Sub namespaces per existing extension
 *
 * \Siel\Acumulus also contains sub namespaces per existing webshop/CMS
 * implementation:
 * - {@see \Siel\Acumulus\Joomla} (with sub sub namespaces
 *   {@see \Siel\Acumulus\Joomla\VirtueMart} and
 *   {@see \Siel\Acumulus\Joomla\HikaShop}).
 * - {@see \Siel\Acumulus\Magento}
 * - {@see \Siel\Acumulus\OpenCart}
 * - {@see \Siel\Acumulus\PrestaShop}
 * - {@see \Siel\Acumulus\WooCommerce}
 * - {@see \Siel\Acumulus\MyWebShop}: example and template code to implement the
 *   Acumulus plugin for your own webshop.
 * - {@see \Siel\Acumulus\Tests\TestWebShop\_Documentation \Siel\Acumulus\Tests\TestWebShop}
 *   A minimalist implementation to allow the tests to run in a "functional"
 *   shop environment.
 *
 * These sub namespaces only contain shop/CMS specific code.
 *
 * ### General principles
 *
 * This library applies/adheres/follows the following general principles:
 * - PSR-12 coding standards.
 * - PHPDoc to fully document each and every part of the code.
 * - When needed, its own PSR-4 autoloader to circumvent the fact that many
 *   webshop/CMS systems still live in the pre PSR-4 autoloading era, meaning
 *   that if an autoloader already exists it often won't work with the PSR4
 *   standard.
 * - Its own translation system to present user facing texts in this library in
 *   English and Dutch, see {@see \Siel\Acumulus\Helpers\Translator} for more
 *   information about translation.
 * - Its own way of defining forms, and subsequently rendering them or mapping
 *   them to the form system of the webshop itself, see
 *   {@see \Siel\Acumulus\Helpers\Form},
 *   {@see \Siel\Acumulus\Helpers\FormRenderer}, and
 *   {@see \Siel\Acumulus\Helpers\FormMapper} for more information about form
 *   handling.
 * - Dependency injection: our container uses a namespace based (instead of
 *   configuration based) way of finding an instantiating the right class, and
 *   it knows what arguments the constructors want. See
 *   {@see \Siel\Acumulus\Helpers\Container} for more information about our
 *   container.
 * - Support friendly: lots of logging, emailing, clear and explicative user
 *   messages, built in dry-run, allows easy use of the API test mode.
 *
 * ### Invoice creation details
 *
 * - The extension should support invoices for orders and refunds, the latter of
 *   course only if supported by the webshop.
 * - 2 ways of sending an invoice to Acumulus are supported:
 *     - Event triggered: the library currently supports the following types of
 *       events: order creation or status change, refund creation, (shop)
 *       invoice creation or (shop) invoice send to customer (if the webshop
 *       supports its own notion of invoices as clearly separate entities).
 *       This support consists of: the configuration form allows to define at
 *       what order statuses and/or what events the invoice should be sent and
 *       to handle the status change to see if the invoice has to be sent and
 *       has not already been sent.
 *     - Manual: the library defines a batch send form where the user can define
 *       a range of orders or refunds to send to Acumulus as well as some other
 *       options to influence if and how the invoices are sent.
 * - Protection against sending an invoice for a given order or refund twice.
 * - Invoice creation: if the invoice should be sent a raw version of it has to
 *   be created. This raw version is based on the shop order and should be in
 *   the approximate format as Acumulus expects it to receive. This will be the
 *   most work when developing a new extension. See
 *   {@see \Siel\Acumulus\Invoice} for more detail.
 * - Invoice completion: the raw invoice is a more or less direct conversion
 *   from the shop order to the Acumulus invoice format. The completion phase
 *   will ensure that it fully complies with the Acumulus format and will
 *   largely be done by code that is webshop agnostic and is thus already part
 *   of the library. In rare case you may have to override classes that
 *   participate in the completion phase to cater for specific situations. See
 *   {@see \Siel\Acumulus\Invoice} for more detail about the creation and
 *   completion phases.
 * - Invoice sending: when the invoice is completed, it is sent by the
 *   {@see \Siel\Acumulus\Shop\InvoiceManager} that uses the
 *   {@see \Siel\Acumulus\ApiClient\Acumulus} for the actual communication.
 * - Result handling: when the invoice is sent, the results are processed. if
 *   sending it was successful, an {@see \Siel\Acumulus\Shop\AcumulusEntry}
 *   record is stored to prevent sending it again, if not a mail is sent
 *   informing the admin about any errors and/or warnings.
 *
 * ### Developing a new extension
 *
 * If you want to develop an extension for another web shop these are the steps
 * to follow:
 * - Use only PHP language constructs that are available in PHP 7.2 and have not
 *   been deprecated since, even though PHP 7.2 is hardly used anymore. We will
 *   advance this requirement to PHP 7.4 in the nearby future, but being a
 *   library that can be used by a wide range of users in a wide range of host
 *   system and hosting environments, we should not be too modern.
 * - We advise using PhpStorm. This IDE guards you against numerous errors that
 *   are simply to make in not strongly typed languages. Moreover, the code uses
 *   many no-inspection hints provided by PhpStorm to document "false"
 *   positives.
 * - Document and type hint all your code, especially parameters, properties,
 *   return types and, where useful, local variables. This allows other
 *   developers and IDEs to better follow what is going on.
 * - Use the inheritdoc tag where possible, only adding documentation to
 *   describe what the override does different from the base implementation.
 * - Set up a new namespace that will contain all classes that extend, implement
 *   and override the functionality of the libAcumulus classes. The namespace
 *   should preferably contain levels for the CMS (if the web shop resides in a
 *   CMS), the web shop, and, if needed, the major version of the web shop. See
 *   {@see \Siel\Acumulus\Helpers\Container} for a detailed explanation of the
 *   expected namespace structure and how that will be used by the container.
 *   Use the {@see \Siel\Acumulus\MyWebShop} example code or the MyTestWebShop
 *   test code for a quick start and lots of advice on what and what not to do.
 * - Follow the detailed tips and advice from the MyWebShop example code to
 *   adapt it to the web shop you are implementing the extension for.
 * - The code in the library contains almost no private members, allowing
 *   extension developers to override each and every part of the code as
 *   necessary. However, try to use this sparingly, following advice in the
 *   MyWebShop example code about which method to override and which not.
 * - When you are more or less finished with the library based part of your
 *   extension, you need to hook it into your web shop. This part is very web
 *   shop specific but generally speaking you should:
 *     - Create a full-fledged module adhering to the web shop architecture.
 *     - Including install code that checks any (additional) requirements,
 *       creates the Acumulus entry table, and subscribes to the relevant
 *       events.
 *     - Including uninstall code that removes the event subscriptions and
 *       Acumulus entry table.
 *     - Write initialization code that adds the library's namespace to the web
 *       shop autoloader, or, if no autoloader is present in the web shop, or it
 *       cannot be used (not PSR4 compliant), defines an autoloader by including
 *       SielAcumulusAutoloader.php and calling the static
 *       {@see SielAcumulusAutoloader::register()} method.
 *     - Write initialization code that creates the Container (passing in the
 *       correct arguments).
 *     - Define the 3 admin form pages: the settings page, the advanced settings
 *       page, and the batch send page. The settings page may be the default
 *       settings page for an extension, that normally can be reached via
 *       something like "Modules - select Acumulus module - Configure".  These
 *       pages/forms probably need their own menu-item, a route definition
 *       and/or a controller, a renderer part, a form submission part, and
 *       sometimes, depending on the web shop, a view or something like that.
 *     - Event handling code to forward create or status change events of orders
 *       and credit notes to the library.
 *     - See existing code on https://github.com/SIELOnline, where all web shop
 *       specific projects are also viewable.
 */
interface _Documentation {}
