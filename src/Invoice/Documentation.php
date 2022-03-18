<?php

/**
 * The Invoice namespace contains the invoice handling related classes.
 *
 * This namespace can be considered to be the centre of each Acumulus extension
 * as it is the place where orders (and refunds) are transformed into the
 * invoice structure as sent to Acumulus.
 *
 * Classes
 * -------
 *
 * The most important classes are:
 * - {@see Source}: A wrapper around a webshop order or refund.
 * - {@see Creator}: The class that creates a first raw version of the Acumulus
 *   invoice structure based on a {@see Source}.
 * - {@see Completor}: The class that completes the Acumulus invoice structure
 *   given a raw version as created by the {@see Creator}.
 * - {@see AcumulusResult}: An extension of the ApiClient {@see Siel\Acumulus\ApiClient\Result}
 *   class with additional methods specific for handling the result of sending
 *   an Invoice over the Acumulus API.
 *
 * Other classes, including the {@see CompletorStrategy} sub namespace are part
 * of the Completor phase and are called via the {@see Completor}. These are:
 * - {@see CompletorInvoiceLines}: Completes the invoice at the line level.
 * - {@see FlattenerInvoiceLines}: Composed and Bundled products, but also the
 *   options (e.g. size, color) are created as child lines for the base product.
 *   This gives flexibility with printing an invoice, e.g. print all options or
 *   sub products on 1 line or on separate indented lines, or do not print them
 *   at all. However, when sending to Acumulus the invoice must be "flattened"
 *   as Acumulus does not accept multi level lines. The task of this class is to
 *   do so based on the configuration as set by the user.
 * - {@see CompletorStrategyLines}: Completes ihe invoice at the line level for
 *   those lines that are not easy to complete or correct on its own.
 * - {@see CompletorStrategyBase}: The base class for the strategies that can be
 *   used to correct "strategy" lines. The various strategies are found in the
 *   {@see CompletorStrategy} sub namespace.
 *
 * The invoice creation process
 * ----------------------------
 *
 * Creating an Acumulus invoice, given a webshop order or refund, is **not** a
 * trivial task. Even though a large part of the fields may be simply mapped to
 * properties, getters or other methods of some webshop defined object or array,
 * the difficulty is in the details and in getting the invoice complete and
 * correctly complete.
 *
 * The process to create an Acumulus invoice exists of 2 phases:
 * - Creator phase: in the creator phase a "raw" version of the Acumulus invoice
 *   is created by a Creator class that derives from the base {@see Creator}
 *   class.This derived class will be highly webshop dependent.
 * - Completor phase: in the completor phase, the "raw" invoice is completed
 *   by filling in missing fields and by correcting fields based on e.g.
 *   settings (e.g. "do not send a free shipping line").
 *
 * The idea is that the "raw" version delivered by the 1st phase is mostly a
 * "1-to-1 mapping" of properties of webshop defined objects to tags in the
 * Acumulus invoice structure. However, object structures vary wildly between
 * webshops, from relatively flat "all info in 1 array", to highly normalized
 * data models with separate Order, Customer, CustomerAddress, OrderLine,
 * and Product objects. So the Creator classes can become quite complex anyway.
 *
 * To reduce complexity of the derived Creator classes, a few design principles
 * have been implemented:
 * - The base Creator class provides a number of helper methods to easily add
 *   values to the Acumulus invoice structure, these are mostly the add...
 *   methods.
 * - The base Creator class has been structured to take the structural work out
 *   of the derived classes and have those only implement small methods with
 *   well defined results. This is the so-called template pattern.
 * - As already documented above, the creation of the invoice has been split in
 *   2 phases: the creator phase, being highly webshop dependent but relatively
 *   simple, and the completor phase, being almost webshop independent but
 *   highly complex.
 * - To let the completor phase do its work, its needs (quite) some meta data.
 *   this is to be delivered by the creator phase, thus unfortunately adding
 *   some work to do in that phase. To have this meta information available for
 *   support as well, it is added to the invoice structure using additional
 *   tags. This makes it easily accessible to the completor phase, but also in
 *   the mails sent when there are errors or warnings or when sent in test mode.
 *   The Acumulus webservice ignores any not defined tags, giving this library
 *   the freedom to add what it wants.
 *
 * @link https://www.siel.nl/acumulus/API/Invoicing/Add_Invoice/
 */
namespace Siel\Acumulus\Invoice;
