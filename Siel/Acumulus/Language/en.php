<?php
// Page elements
$_['extensions'] = 'Extensions';
$_['modules'] = 'Modules';
$_['page_title'] = 'Acumulus';
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

$_['message_error_vat19and21'] = 'This order has both 19% and 21% VAT rates. You will have to manually enter this order into Acumulus.';
$_['message_warning_multiplevat'] = 'This order has multiple VAT rates. due to differences in the way that your web sho and Acumulus store an invoice, it may well be that the VAT amounts in Acumulus are NOT correct. You should check this invoice in Acumulus!';

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
$_['discount_code'] = 'Coupon code';
$_['coupon_code'] = 'Voucher';
$_['gift_wrapping'] = 'Gift wrapping';


// Mails
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
$_['desc_email'] = 'The e-mail address at which you will be informed about any errors that occur during invoice sending. If you leave this empty you may NOT be notified at all.';

$_['invoiceSettingsHeader'] = 'Your invoice settings';
$_['option_empty'] = 'Select one';

$_['field_useAcumulusInvoiceNr'] = 'Invoice number';
$_['option_useAcumulusInvoiceNr_0'] = 'Use the web shop order number as invoice number';
$_['option_useAcumulusInvoiceNr_1'] = 'Have Acumulus create an invoice number';

$_['field_useOrderDate'] = 'Invoice date';
$_['option_useOrderDate_0'] = 'Use the order create date';
$_['option_useOrderDate_1'] = 'Use the transfer date';

$_['field_defaultCustomerType'] = 'Create customers as';

$_['field_overwriteIfExists'] = 'Customer address data';
$_['option_overwriteIfExists'] = 'Overwrite existing  address data';
$_['desc_overwriteIfExists'] = 'Check this option, unless you edit customer address data manually in Acumulus.';

$_['field_defaultAccountNumber'] = 'Bank account number';
$_['desc_defaultAccountNumber'] = 'Select the (bank) account number at which you want to receive all your order payments.';

$_['field_defaultCostHeading'] = 'Cost center';
$_['desc_defaultCostHeading'] = 'Select the cost center to assign your orders to.';

$_['field_defaultInvoiceTemplate'] = 'Invoice template';
$_['desc_defaultInvoiceTemplate'] = 'Select the invoice template to print your web shop orders with.';

$_['option_empty_triggerOrderStatus'] = 'Do not send automatically';
$_['field_triggerOrderStatus'] = 'Order state';
$_['desc_triggerOrderStatus'] = 'Select the order state at which orders will be sent to Acumulus. If you select "Do not send automatically" this module will do nothing.';
