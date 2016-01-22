<?php
namespace Siel\Acumulus\OpenCart\Shop;

use Siel\Acumulus\Helpers\TranslationCollection;

/**
 * Contains module related translations, like module name,
 * - InvoiceManager
 */
class OpenCartTranslations extends TranslationCollection {

  protected $nl = array(
    'batch_link_desc' => 'OpenCart staat niet toe dat wij het admin menu aan de linkerkant uitbreiden. Daarom nemen wij de link naar het batchverzendformulier hier op. Nadat u de instellingen hebt ingegeven, kunt u via de link hierboven naar het batchverzendformulier.',
  );

  protected $en = array(
    'batch_link_desc' => 'OpenCart does not allow us to extend the admin menu on the left hand side. Therefore we placed the link to it on this form. After you have configured this module, you can go to the batch form via the above link.',
  );

}
