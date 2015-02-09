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
$_['button_confirm_uninstall'] = 'Yes, uninstall data and settings';
$_['button_cancel_uninstall'] = 'No, disable only, keep data and settings';
$_['button_cancel'] = 'Cancel';
$_['button_send'] = 'Send';

// Messages
$_['message_config_saved'] = 'The settings are saved.';
$_['message_uninstall'] = 'Are you sure to delete the configuration settings?';

$_['message_validate_contractcode_0'] = 'The field Contract code is required, please fill in the contract code you use to log in to Acumulus.';
$_['message_validate_contractcode_1'] = 'The field Contract code is a numeric field, please fill in the contract code you use to log in to Acumulus.';
$_['message_validate_username_0'] = 'The field User name is required, please fill in the user name you use to log in to Acumulus.';
$_['message_validate_password_0'] = 'The field Password is required, please fill in the password you use to log in to Acumulus.';
$_['message_validate_email_0'] = 'The field Email is not a valid e-mail address, please fill in your own e-mail address.';
$_['message_validate_email_1'] = 'The field Email is required, please fill in you own e-mail address.';
$_['message_validate_email_2'] = 'The field (fictitious customer) Email is not a valid e-mail address, please fill in a correct e-mail address.';
$_['message_validate_email_3'] = 'The field BCC is not a valid e-mail address, please fill in a valid e-mail address.';
$_['message_validate_email_4'] = 'The field Sender is not a valid e-mail address, please fill in a valid e-mail address.';
$_['message_validate_conflicting_options'] = 'If you don\'t send customer data to Acumulus, Acumulus cannot send PDF invoices. Change one of the options.';

$_['message_error_vat19and21'] = 'This order has both 19% and 21% VAT rates. You will have to manually enter this order into Acumulus.';
$_['message_warning_incorrect_vat'] = 'The Acumulus module was not able to correctly determine the VAT amounts on the invoice. You should check and correct this invoice manually in Acumulus!';
$_['message_warning_incorrect_vat_corrected'] = 'The invoice specified an incorrect VAT rate of %1$0.1f%%. This has been corrected to %2$0.1f%%. Check this invoice in Acumulus!';
$_['message_warning_incorrect_vat_not_corrected'] = 'The invoice specified an incorrect VAT rate of %0.1f%%. It was not possible to correct this to a valid VAT rate. Correct this invoice manually in Acumulus!';

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
$_['payment_costs'] = 'Payment costs';
$_['discount'] = 'Discount';
$_['discount_code'] = 'Coupon code';
$_['coupon_code'] = 'Voucher';
$_['used'] = 'used';
$_['gift_wrapping'] = 'Gift wrapping';
$_['fee'] = 'Order treatment costs';
$_['refund'] = 'Refund';
$_['refund_adjustment'] = 'Refund adjustment';


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

Send state:        {status} {status_text}.
(Webshop) Order:   {order_id}
(Webshop) Invoice: {invoice_id}

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
  <tr><td>(Webshop) Order:</td><td>{order_id}</td></tr>
  <tr><td>(Webshop) Invoice:</td><td>{invoice_id}</td></tr>
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
$_['option_invoiceNrSource_2'] = 'Use the web shop order number as invoice number.';
$_['option_invoiceNrSource_3'] = 'Have Acumulus create an invoice number.';
$_['desc_invoiceNrSource'] = 'Select which number to use for the invoice in Acumulus.';

$_['field_dateToUse'] = 'Invoice date';
$_['option_dateToUse_1'] = 'Use the invoice date. Note: if no invoice has been created for the order yet, the order create date will be used!';
$_['option_dateToUse_2'] = 'Use the order create date.';
$_['option_dateToUse_3'] = 'Use the transfer date.';
$_['desc_dateToUse'] = 'Select which date to use for the invoice in Acumulus.';

$_['field_clientData'] = 'Customer address data';
$_['option_sendCustomer'] = 'Send consumer client records to Acumulus.';
$_['option_overwriteIfExists'] = 'Overwrite existing address data.';
$_['desc_clientData'] = 'Acumulus allows you to store client data.
This extension automatically sends client data to Acumulus.
If you don\'t want this, uncheck this option.
All consumer invoices will be booked on one and the same fictitious client.
You should uncheck the second option if you edit customer address data manually in Acumulus.
If you unchecked the first option, the second option only applies to business clients.';

$_['field_defaultCustomerType'] = 'Create customers as';

$_['field_defaultAccountNumber'] = 'Bank account number';
$_['desc_defaultAccountNumber'] = 'Select the (bank) account number at which you want to receive all your order payments.';

$_['field_defaultCostCenter'] = 'Cost center';
$_['desc_defaultCostCenter'] = 'Select the cost center to assign your orders to.';

$_['field_defaultInvoiceTemplate'] = 'Invoice template (due)';
$_['field_defaultInvoicePaidTemplate'] = 'Invoice template (paid)';
$_['option_same_template'] = 'Same template as for due';
$_['desc_defaultInvoiceTemplates'] = 'Select the invoice templates to print your web shop orders with, for due respectively paid orders.';

$_['field_triggerOrderEvent'] = 'Send the invoice to Acumulus';
$_['option_triggerOrderEvent_1'] = 'When an order reaches the state as defined below.';
$_['option_triggerOrderEvent_2'] = 'When the invoice gets created for this order.';
$_['desc_triggerOrderEvent'] = 'Select when to send the invoice to Acumulus. This extension only uses order data. so you may select any status. The invoice does not already have to be created.';

$_['option_empty_triggerOrderStatus'] = 'Do not send automatically';
$_['field_triggerOrderStatus'] = 'Order state';
$_['desc_triggerOrderStatus'] = 'Select the order state at which orders will be sent to Acumulus. If you select "Do not send automatically" this module will do nothing.';

$_['emailAsPdfSettingsHeader'] = 'PDF Invoice';
$_['desc_emailAsPdfInformation'] = 'On sending the order details to Acumulus, Acumulus can send a PDF invoice to your customer. The mail will be sent to the clients\' email address.';

$_['field_emailAsPdf'] = 'Enable the feature';
$_['option_emailAsPdf'] = 'Have Acumulus send the invoice as PDF.';
$_['desc_emailAsPdf'] = 'If you check this option, you can use the other options below to configure the emails to your preferences. However, to configure the text in them mail body, go to Acumulus to "Beheer - Factuur-sjablonen".';

$_['field_emailFrom'] = 'Sender';
$_['desc_emailFrom'] = 'The email address to use as sender. If you leave this empty, the email address of the Acumulus account owner will be used.';

$_['field_emailBcc'] = 'BCC';
$_['desc_emailBcc'] = 'Additional email addresses to send the invoice to, e.g. the email address of your own administration department. If you leave this empty the invoice email will only be sent to your client.';

$_['field_subject'] = 'Subject';
$_['desc_subject'] = 'The subject line of the email. If you leave this empty "Invoice [invoice#] Order [order#]" will be used. You can use [#b] to place the order number in the subject and [#f] for the invoice number (from the webshop, not Acumulus).';

$_['versionInformationHeader'] = 'Module information';
$_['desc_versionInformation'] = 'Please mention this information with any support request.';

$_['field_debug'] = 'Debug and support';
$_['option_debug_1'] = 'Send messages to Acumulus and only receive a mail when there are errors or warnings.';
$_['option_debug_2'] = 'Send messages to Acumulus and receive a mail with the results.';
$_['option_debug_3'] = 'Do not send messages to Acumulus, but receive a mail with the message as would have been sent.';
$_['option_debug_4'] = 'Send messages to Acumulus, but Acumulus will only check the input for errors and warnings, not store any changes.';
$_['desc_debug'] = 'Select a debug mode. Choose for the first option unless otherwise instructed by support staff.';

// Send manual form
$_['page_title_manual'] = 'Manually send invoice';
$_['manualSelectIdHeader'] = 'Specify the invoice to send';
$_['field_manual_order'] = 'Order #';
$_['field_manual_invoice'] = 'Invoice #';
$_['field_manual_creditmemo'] = 'Creditmemo #';
$_['manual_form_desc'] = '<strong>ATTENTION: Use this form at your own risk.</strong> Acumulus does not check if invoices are sent twice. By sending an invoce manually (a 2nd time), your administration can become incorrect. use this form only if isntructed tod os o by support staff and even then only when the 3rd option has been chosen for the "Support and debug" mode on the settings screen.';
$_['manual_order_sent'] = "Order '%s' has been sent";
$_['manual_order_not_found'] = "Order '%s' not found";
$_['manual_invoice_sent'] = "Factuur '%s' has been sent";
$_['manual_invoice_not_found'] = "Factuur '%s' not found";
$_['manual_creditmemo_sent'] = "Creditmemo '%s' has been sent";
$_['manual_creditmemo_not_found'] = "Credit memo '%s' not found";

// Uninstall form
$_['uninstallHeader'] = 'Confirm uninstall';
$_['desc_uninstall'] = 'The module has been disabled. Choose whether you also want to delete all data and settings or if you want to keep these for now.';
