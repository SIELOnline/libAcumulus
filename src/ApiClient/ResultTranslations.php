<?php

declare(strict_types=1);

namespace Siel\Acumulus\ApiClient;

use Siel\Acumulus\Helpers\TranslationCollection;

/**
 * Contains translations for the Result class.
 */
class ResultTranslations extends TranslationCollection
{
    protected array $nl = [
        'api_status_unknown' => 'Onbekende API status code %d',
        'request_not_yet_sent' => 'Verzoek (nog) niet verstuurd',
        'message_sent' => 'Verzonden bericht',
        'message_received' => 'Ontvangen bericht',
        'message_response_success' => 'Succes',
        'message_response_info' => 'Succes, met informatieve meldingen',
        'message_response_notice' => 'Succes, met opmerkingen',
        'message_response_warning' => 'Succes, met waarschuwingen',
        'message_response_error' => 'Mislukt, fouten gevonden',
        'message_response_exception' => 'Ernstige fout, neem contact op met Acumulus',
    ];

    protected array $en = [
        'api_status_unknown' => 'Unknown API status code %d',
        'request_not_yet_sent' => 'Request not (yet) sent',
        'message_sent' => 'Message sent',
        'message_received' => 'Message received',
        'message_response_success' => 'Success',
        'message_response_info' => 'Success, with informational messages',
        'message_response_notice' => 'Success, with notices',
        'message_response_warning' => 'Success, with warnings',
        'message_response_error' => 'Failed, errors found',
        'message_response_exception' => 'Exception, please contact Acumulus technical support',
    ];
}
