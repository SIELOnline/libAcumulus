<?php
/**
 * Not all constants may have actual usages, in that case they are here for
 * completeness and future use/auto-completion.
 * @noinspection PhpUnused
 */

declare(strict_types=1);

namespace Siel\Acumulus;

/**
 * Tag defines string constants for the tags used in the Acumulus API messages.
 *
 * Mainly the tags used in the invoice-add and signup api call are added here.
 *
 * @legacy: replace by {@see Fld}. Usages:
 *   - Legacy code: as array keys
 *   - Config groups (easy to change)
 *   - Config keys (relatively easy to change: needs upgrade and change to Config::get())
 *     and thus forms (register and setting)
 *   - Metadata (Meta::RecalculatePrice), Meta::FieldsCalculated
 *   - Tests (including testdata)
 *   - ...  other uses
 *   - Currently 476 uses (442 static member access; 34 imports)
 */
interface Tag
{
    public const Contract = 'contract';
    public const Customer = 'customer';
    public const Invoice = 'invoice';
    public const Line = 'line';

    public const ContractCode = 'contractcode';
    public const UserName = 'username';
    public const Password = 'password';
    public const EmailOnError = 'emailonerror';
    public const EmailOnWarning = 'emailonwarning';

    public const Type = 'type';
    public const VatTypeId = 'vattypeid';
    public const ContactId = 'contactid';
    public const ContactYourId = 'contactyourid';
    public const ContactStatus = 'contactstatus';
    public const CompanyTypeId = 'companytypeid';
    public const CompanyName = 'companyname';
    public const CompanyName1 = 'companyname1';
    public const CompanyName2 = 'companyname2';
    public const FullName = 'fullname';
    public const Salutation = 'salutation';
    public const Address = 'address';
    public const Address1 = 'address1';
    public const Address2 = 'address2';
    public const PostalCode = 'postalcode';
    public const City = 'city';
    public const Country = 'country';
    public const CountryCode = 'countrycode';
    public const VatNumber = 'vatnumber';
    public const Telephone = 'telephone';
    public const Fax = 'fax';
    public const Email = 'email';
    public const OverwriteIfExists = 'overwriteifexists';
    public const BankAccount = 'bankaccount';
    public const BankAccountNumber = 'bankaccountnumber';
    public const Mark = 'mark';
    public const DisableDuplicates = 'disableduplicates';

    public const Concept = 'concept';
    public const ConceptType = 'concepttype';
    public const Number = 'number';
    public const VatType = 'vattype';
    public const IssueDate = 'issuedate';
    public const CostCenter = 'costcenter';
    public const AccountNumber = 'accountnumber';
    public const PaymentStatus = 'paymentstatus';
    public const PaymentDate = 'paymentdate';
    public const Description = 'description';
    public const DescriptionText = 'descriptiontext';
    public const Template = 'template';
    public const Notes = 'notes';
    public const InvoiceNotes = 'invoicenotes';

    public const ItemNumber = 'itemnumber';
    public const Product = 'product';
    public const Nature = 'nature';
    public const UnitPrice = 'unitprice';
    public const VatRate = 'vatrate';
    public const Quantity = 'quantity';
    public const CostPrice = 'costprice';

    public const EmailAsPdf = 'emailaspdf';
    public const EmailTo = 'emailto';
    public const EmailBcc = 'emailbcc';
    public const EmailFrom = 'emailfrom';
    public const Subject = 'subject';
    public const Message = 'message';
    public const Ubl = 'ubl';
    public const ConfirmReading = 'confirmreading';

    public const LoginName = 'loginname';
    public const Gender = 'gender';
    public const CreateApiUser = 'createapiuser';

    public const CountryRegion = 'countryregion';
}
