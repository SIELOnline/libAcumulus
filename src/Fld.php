<?php
/**
 * Not all constants may have actual usages, in that case they are here for
 * completeness and future use/auto-completion.
 * @noinspection PhpUnused
 */

declare(strict_types=1);

namespace Siel\Acumulus;

/**
 * Fld defines string constants for the fields used in the Acumulus API messages.
 *
 * Mainly the tags used in the invoice-add and signup api call are added here.
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
    public const ContractCode = 'contractCode';
    public const UserName = 'userName';
    public const Password = 'password';
    public const EmailOnError = 'emailOnError';
    public const EmailOnWarning = 'emailOnWarning';

    // Connector
    public const Application = 'application';
    public const Format = 'format';
    public const TestMode = 'testMode';
    public const Lang = 'lang';
    public const INodes = 'iNodes';
    public const ONodes = 'oNodes';
    public const Order = 'order';
    public const WebKoppel = 'webKoppel';
    public const Development = 'development';
    public const Remark = 'remark';
    public const SourceUri = 'sourceUri';

    // Customer
    public const Type = 'type';
    public const VatTypeId = 'vatTypeId';
    public const ContactId = 'contactId';
    public const ContactYourId = 'contactYourId';
    public const ContactStatus = 'contactStatus';
    public const CompanyName = 'companyName';

    // Address
    public const CompanyName1 = 'companyName1';
    public const CompanyName2 = 'companyName2';
    public const CompanyTypeId = 'companyTypeId';
    public const FullName = 'fullName';
    public const Salutation = 'salutation';
    public const Address = 'address';
    public const Address1 = 'address1';
    public const Address2 = 'address2';
    public const PostalCode = 'postalCode';
    public const City = 'city';
    public const Country = 'country';
    public const CountryCode = 'countryCode';
    public const CountryAutoName = 'countryAutoName';
    public const CountryAutoNameLang = 'countryAutoNameLang';
    public const Website = 'website';
    public const VatNumber = 'vatNumber';
    public const Telephone = 'telephone';
    public const Telephone2 = 'telephone2';
    public const Fax = 'fax';
    public const Email = 'email';
    public const OverwriteIfExists = 'overwriteIfExists';
    public const BankAccount = 'bankAccount';
    public const BankAccountNumber = 'bankAccountNumber';
    public const Mark = 'mark';
    public const DisableDuplicates = 'disableDuplicates';

    // Alternative address
    public const AltCompanyName1 = 'altCompanyName1';
    public const AltCompanyName2 = 'altCompanyName2';
    public const AltFullName = 'altFullName';
    public const AltAddress1 = 'altAddress1';
    public const AltAddress2 = 'altAddress2';
    public const AltPostalCode = 'altPostalCode';
    public const AltCity = 'altCity';
    public const AltCountry = 'altCountry';
    public const AltCountryCode = 'altCountryCode';
    public const AltCountryAutoName = 'altCountryAutoName';
    public const AltCountryAutoNameLang = 'altCountryAutoNameLang';

    // Invoice
    public const Concept = 'concept';
    public const ConceptType = 'conceptType';
    public const Number = 'number';
    public const VatType = 'vatType';
    public const IssueDate = 'issueDate';
    public const CostCenter = 'costCenter';
    public const AccountNumber = 'accountNumber';
    public const PaymentStatus = 'paymentStatus';
    public const PaymentDate = 'paymentDate';
    public const Description = 'description';
    public const DescriptionText = 'descriptionText';
    public const Template = 'template';
    public const Notes = 'notes';
    public const InvoiceNotes = 'invoiceNotes';

    // Line
    public const ItemNumber = 'itemNumber';
    public const Product = 'product';
    public const Nature = 'nature';
    public const UnitPrice = 'unitPrice';
    public const VatRate = 'vatRate';
    public const Quantity = 'quantity';
    public const CostPrice = 'costPrice';

    // Email PDF/ Get PDF (invoice and packing slip)
    public const Token = 'token';
    public const EmailAsPdf = 'emailAsPdf';
    public const EmailTo = 'emailTo';
    public const EmailBcc = 'emailBcc';
    public const EmailFrom = 'emailFrom';
    public const Subject = 'subject';
    public const Message = 'message';
    public const ConfirmReading = 'confirmReading';
    public const Gfx = 'gfx';
    public const Ubl = 'ubl';

    // Register
    public const LoginName = 'loginName';
    public const Gender = 'gender';
    public const CreateApiUser = 'createApiUser';

    public const CountryRegion = 'countryRegion';

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
    public const StockDescription = 'stockDescription';
    public const StockDate = 'stockDate';
}
