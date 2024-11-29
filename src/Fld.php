<?php
/**
 * Not all constants may have actual usages, in that case they are here for
 * completeness and future use/auto-completion.
 * @noinspection PhpUnused
 */

declare(strict_types=1);

namespace Siel\Acumulus;

/**
 * Fld defines string constants for the tags used in the Acumulus API messages.
 *
 * @todo: Add all tags used, also for incoming messages.
 *   And thus replace all usages of string constants in code accessing fields.
 */
interface Fld
{
    // Structures
    public const Connector = 'connector';
    public const Contract = 'contract';
    public const Customer = 'customer';
    public const Invoice = 'invoice';
    public const Line = 'line';
    public const Stock = 'stock';

    // Contract
    public const ContractCode = 'contractcode';
    public const UserName = 'username';
    public const Password = 'password';
    public const EmailOnError = 'emailonerror';
    public const EmailOnWarning = 'emailonwarning';

    // Connector
    public const Application = 'application';
    public const Format = 'format';
    public const TestMode = 'testmode';
    public const Lang = 'lang';
    public const INodes = 'inodes';
    public const ONodes = 'onodes';
    public const Order = 'order';
    public const WebKoppel = 'webkoppel';
    public const Development = 'development';
    public const Remark = 'remark';
    public const SourceUri = 'sourceuri';

    // Customer
    public const Type = 'type';
    public const VatTypeId = 'vattypeid';
    public const ContactId = 'contactid';
    public const ContactYourId = 'contactyourid';
    public const ContactStatus = 'contactstatus';
    public const CompanyName = 'companyname';

    // Address
    public const CompanyName1 = 'companyname1';
    public const CompanyName2 = 'companyname2';
    public const CompanyTypeId = 'companytypeid';
    public const FullName = 'fullname';
    public const Salutation = 'salutation';
    public const Address = 'address';
    public const Address1 = 'address1';
    public const Address2 = 'address2';
    public const PostalCode = 'postalcode';
    public const City = 'city';
    public const Country = 'country';
    public const CountryCode = 'countrycode';
    public const CountryAutoName = 'countryautoname';
    public const CountryAutoNameLang = 'countryautonamelang';
    public const Website = 'website';
    public const VatNumber = 'vatnumber';
    public const Telephone = 'telephone';
    public const Telephone2 = 'telephone2';
    public const Fax = 'fax';
    public const Email = 'email';
    public const OverwriteIfExists = 'overwriteifexists';
    public const BankAccount = 'bankaccount';
    public const BankAccountNumber = 'bankaccountnumber';
    public const Mark = 'mark';
    public const DisableDuplicates = 'disableduplicates';

    // Alternative address
    public const AltCompanyName1 = 'altcompanyname1';
    public const AltCompanyName2 = 'altcompanyname2';
    public const AltFullName = 'altfullname';
    public const AltAddress1 = 'altaddress1';
    public const AltAddress2 = 'altaddress2';
    public const AltPostalCode = 'altpostalcode';
    public const AltCity = 'altcity';
    public const AltCountry = 'altcountry';
    public const AltCountryCode = 'altcountrycode';
    public const AltCountryAutoName = 'altcountryautoname';
    public const AltCountryAutoNameLang = 'altcountryautonamelang';

    // Invoice
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

    // Line
    public const ItemNumber = 'itemnumber';
    public const Product = 'product';
    public const Nature = 'nature';
    public const UnitPrice = 'unitprice';
    public const VatRate = 'vatrate';
    public const Quantity = 'quantity';
    public const CostPrice = 'costprice';

    // Email PDF/ Get PDF (invoice and packing slip)
    public const Token = 'token';
    public const EmailAsPdf = 'emailaspdf';
    public const EmailTo = 'emailto';
    public const EmailBcc = 'emailbcc';
    public const EmailFrom = 'emailfrom';
    public const Subject = 'subject';
    public const Message = 'message';
    public const ConfirmReading = 'confirmreading';
    public const Gfx = 'gfx';
    public const Ubl = 'ubl';

    // Register
    public const LoginName = 'loginname';
    public const Gender = 'gender';
    public const CreateApiUser = 'createapiuser';

    public const CountryRegion = 'countryregion';

    // Product (on receiving a product, so all lower case, note that empty strings are
    // represented as an empty array).
    public const ProductId = 'productid';
    public const ProductNature = 'productnature';
    public const ProductDescription = 'productdescription';
    public const ProductTagId = 'producttagid';
    public const productContactId = 'productcontactid';
    public const ProductPrice = 'productprice';
    public const ProductVatRate = 'productvatrate';
    public const ProductSku = 'productsku';
    public const ProductEan = 'productean';
    public const ProductHash = 'producthash';
    public const ProductNotes = 'productnotes';
    public const ProductStockAmount = 'productstockamount';

    // Stock
    public const StockAmount = 'stockamount';
    public const StockDescription = 'stockdescription';
    public const StockDate = 'stockdate';
}
