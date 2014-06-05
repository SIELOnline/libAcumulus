<?php
// Page elements
$_['extensions'] = 'Extensions';
$_['modules'] = 'Modules';
$_['page_title'] = 'Acumulus settings';
$_['module_name'] = 'Acumulus';
$_['module_description'] = 'Acumulus connection';
$_['text_home'] = 'Home';
$_['button_settings'] = 'Settings';
$_['button_save'] = 'Save';
$_['button_back'] = 'Back to list';
$_['button_cancel'] = 'Cancel';

// Messages
$_['message_config_saved'] = 'De settings are saved.';
$_['message_uninstall'] = 'Are you sure to delete the configuration settings?';

$_['message_validate_contractcode_0'] = 'The field Contract code is required, please fill in the contract code you use to log in to Acumulus.';
$_['message_validate_contractcode_1'] = 'The field Contract code is a numeric field, please fill in the contract code you use to log in to Acumulus.';
$_['message_validate_username_0'] = 'The field User name is required, please fill in the user name you use to log in to Acumulus.';
$_['message_validate_password_0'] = 'The field Password is required, please fill in the password you use to log in to Acumulus.';
$_['message_validate_email_0'] = 'The field Email is not a valid e-mail address, please fill in you own e-mail address.';
$_['message_validate_email_1'] = 'The field Email is required, please fill in you own e-mail address.';
$_['message_validate_email_2'] = 'The field (fictitious customer) Email is not a valid e-mail address, please fill in a correct e-mail address.';

$_['message_error_vat19and21'] = 'This order has both 19% and 21% VAT rates. You will have to manually enter this order into Acumulus.';
$_['message_warning_incorrect_vat'] = 'The Acumulus module was not able to correctly determine the VAT amounts on the invoice. You should check and correct this invoice manually in Acumulus!';

$_['message_error_req_curl'] = 'The CURL PHP extension needs to be activated on your server for this module to work.';
$_['message_error_req_xml'] = 'The SimpleXML extension needs to be activated on your server for this module to be able to work with the XML format.';
$_['message_error_req_dom'] = 'The DOM PHP extension needs to be activated on your server for this module to work.';

$_['message_error_auth'] = 'Your Acumulus connection settings are incorrect. Please check them. After you have entered the correct connection settings the other settings will be shown as well.';
$_['message_error_comm'] = 'The module encountered an error retrieving your Acumulus configuration. Please try again. When the connection is restored the other settings will be shown as well.';
$_['message_auth_unknown'] = 'When your Acumulus connection settings are filled in, the other settings will be shown as well.';

$_['message_response_0'] = 'Success. Without warnings';
$_['message_response_1'] = 'Failed. Errors found';
$_['message_response_2'] = 'Success. With any warnings';
$_['message_response_3'] = 'Exception. Please contact Acumulus technical support';
$_['message_response_x'] = 'Unknown status code';

$_['message_error'] = 'Error';
$_['message_warning'] = 'Warning';

$_['message_info_for_user'] = 'The information below is only shown to facilitate support. You may ignore these messages.';
$_['message_sent'] = 'Message sent';
$_['message_received'] = 'Message received';

$_['message_no_invoice'] = 'not created';

$_['order_id'] = 'Ordernumber';
$_['shipping_costs'] = 'Shipping costs';
$_['discount'] = 'Discount';
$_['discount_code'] = 'Coupon code';
$_['coupon_code'] = 'Voucher';
$_['gift_wrapping'] = 'Gift wrapping';
$_['fee'] = 'Order treatment costs';
$_['refund'] = 'Refund';


// Mails
$_['mail_sender_name'] = 'Your web store';
$_['mail_subject'] = 'Errors or warnings on sending an invoice to Acumulus';
$_['mail_text'] = <<<LONGSTRING
Dear madam, sir,

On sending an invoice to Acumulus, some errors or warnings occurred.

If the send state below equals "2 {status_2_text}",
warnings were returned, but the invoice has been created in Acumulus. However,
we advice you to check the invoice in Acumulus for correctness.

If the send state equals "1 {status_1_text}" or
"3 {status_3_text}", errors were returned and the invpoice has NOT been created.
You will have to manually create the incvoice in Acumulus or adapt it in your
web shop and resend it to Acumulus.

Send state: {status} {status_text}.
Order:      {order_id}
Invoice:    {invoice_id}

Messages:
{messages}

At https://apidoc.sielsystems.nl/node/16 you can find more information regarding
any error codes mentioned above.
LONGSTRING;

$_['mail_html'] = <<<LONGSTRING
<p>Dear madam, sir,</p>

<p>On sending an invoice to Acumulus, some errors or warnings occurred.</p>
<p>If the send state below equals "2 {status_2_text}",
warnings were returned, but the invoice has been created in Acumulus. However,
we advice you to check the invoice in Acumulus for correctness.</p>
<p>If the send state equals "1 {status_1_text}" or
"3 {status_3_text}", errors were returned and the invpoice has NOT been created.
You will have to manually create the incvoice in Acumulus or adapt it in your
web shop and resend it to Acumulus.</p>
<table>
  <tr><td>Send state:</td><td>{status} {status_text}.</td></tr>
  <tr><td>Order:</td><td>{order_id}</td></tr>
  <tr><td>Invoice:</td><td>{invoice_id}</td></tr>
</table>
<p>Messages:<br>
{messages_html}</p>
<p>At <a href="https://apidoc.sielsystems.nl/node/16">Acumulus - API documentation: exit and warning codes</a>
you can find more information regarding any error codes mentioned above.</p>
LONGSTRING;

// Configuration form
$_['accountSettingsHeader'] = 'Your Acumulus connection settings';
$_['field_code'] = 'Contract code';
$_['field_username'] = 'User name';
$_['field_password'] = 'Password';
$_['field_email'] = 'E-mail';
$_['desc_email'] = 'The e-mail address at which you will be informed about any errors that occur during invoice sending. As this module cannot know if it is called from an interactive administrator screen, it will not display any messages in the user interface. Therefore you have to fill in an e-mail address.';

$_['invoiceSettingsHeader'] = 'Your invoice settings';
$_['option_empty'] = 'Select one';

$_['field_invoiceNrSource'] = 'Invoice number';
$_['option_invoiceNrSource_1'] = 'Use the web shop invoice number. Note: if no invoice has been created for the order yet, the order number will be used!';
$_['option_invoiceNrSource_2'] = 'Use the web shop order number as invoice number';
$_['option_invoiceNrSource_3'] = 'Have Acumulus create an invoice number';
$_['desc_invoiceNrSource'] = 'Select which number to use for the invoice in Acumulus.';

$_['field_dateToUse'] = 'Invoice date';
$_['option_dateToUse_1'] = 'Use the invoice date. Note: if no invoice has been created for the order yet, the order create date will be used!';
$_['option_dateToUse_2'] = 'Use the order create date';
$_['option_dateToUse_3'] = 'Use the transfer date';
$_['desc_dateToUse'] = 'Select which date to use for the invoice in Acumulus.';

$_['field_clientData'] = 'Customer address data';
$_['option_sendCustomer'] = 'Send consumer client records to Acumulus';
$_['option_overwriteIfExists'] = 'Overwrite existing address data';
$_['desc_clientData'] = 'Acumulus allows you to store client data.
This extension automatically sends client data to Acumulus.
If you don\'t want this, uncheck this option.
All consumer invoices will be booked on one and the same fictitious client.
You should uncheck the second option if you edit customer address data manually in Acumulus.
If you unchecked the first option, the second option only applies to business clients.';

//$_['field_genericCustomerEmail'] = 'E-mail fictitious client';
//$_['desc_genericCustomerEmail'] = 'If you checked the option above, then please create a fictitious relation, including an e-mail address, in Acumulus and fill in that e-mail address.';

$_['field_defaultCustomerType'] = 'Create customers as';

$_['field_defaultAccountNumber'] = 'Bank account number';
$_['desc_defaultAccountNumber'] = 'Select the (bank) account number at which you want to receive all your order payments.';

$_['field_defaultCostCenter'] = 'Cost center';
$_['desc_defaultCostCenter'] = 'Select the cost center to assign your orders to.';

$_['field_defaultInvoiceTemplate'] = 'Invoice template';
$_['desc_defaultInvoiceTemplate'] = 'Select the invoice template to print your web shop orders with.';

$_['option_empty_triggerOrderStatus'] = 'Do not send automatically';
$_['field_triggerOrderStatus'] = 'Order state';
$_['desc_triggerOrderStatus'] = 'Select the order state at which orders will be sent to Acumulus. If you select "Do not send automatically" this module will do nothing.';

$_['versionInformationHeader'] = 'Module information';
$_['desc_versionInformation'] = 'Please mention this information with any support request.';
