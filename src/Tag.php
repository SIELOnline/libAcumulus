<?php
namespace Siel\Acumulus;

/**
 * Tag defines string constants for the tags used in the Acumulus API messages.
 *
 * Mainly the tags used in the invoice-add and signup api call are added here.
 */
interface Tag
{
    const Customer = 'customer';
    const Invoice = 'invoice';
    const Line = 'line';

    const ContractCode = 'contractcode';
    const UserName = 'username';
    const Password = 'password';
    const EmailOnError = 'emailonerror';
    const EmailOnWarning = 'emailonwarning';

    const Type = 'type';
    const ContactId = 'contactid';
    const ContactYourId = 'contactyourid';
    const ContactStatus = 'contactstatus';
    const CompanyTypeId = 'companytypeid';
    const CompanyName = 'companyname';
    const CompanyName1 = 'companyname1';
    const CompanyName2 = 'companyname2';
    const FullName = 'fullname';
    const Salutation = 'salutation';
    const Address = 'address';
    const Address1 = 'address1';
    const Address2 = 'address2';
    const PostalCode = 'postalcode';
    const City = 'city';
    const Country = 'country';
    const CountryCode = 'countrycode';
    const VatNumber = 'vatnumber';
    const Telephone = 'telephone';
    const Fax = 'fax';
    const Email = 'email';
    const OverwriteIfExists = 'overwriteifexists';
    const BankAccount = 'bankaccount';
    const BankAccountNumber = 'bankaccountnumber';
    const Mark = 'mark';
    const DisableDuplicates = 'disableduplicates';

    const Concept = 'concept';
    const ConceptType = 'concepttype';
    const Number = 'number';
    const VatType = 'vattype';
    const IssueDate = 'issuedate';
    const CostCenter = 'costcenter';
    const AccountNumber = 'accountnumber';
    const PaymentStatus = 'paymentstatus';
    const PaymentDate = 'paymentdate';
    const Description = 'description';
    const DescriptionText = 'descriptiontext';
    const Template = 'template';
    const Notes = 'notes';
    const InvoiceNotes = 'invoicenotes';

    const ItemNumber = 'itemnumber';
    const Product = 'product';
    const Nature = 'nature';
    const UnitPrice = 'unitprice';
    const VatRate = 'vatrate';
    const Quantity = 'quantity';
    const CostPrice = 'costprice';

    const EmailAsPdf = 'emailaspdf';
    const EmailTo = 'emailto';
    const EmailBcc = 'emailbcc';
    const EmailFrom = 'emailfrom';
    const Subject = 'subject';
    const Message = 'message';
    const ConfirmReading = 'confirmreading';

    const LoginName = 'loginname';
    const Gender = 'gender';
    const CreateApiUser = 'createapiuser';
}
