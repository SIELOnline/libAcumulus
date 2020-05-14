<?php
namespace Siel\Acumulus\Helpers;

/**
 * Contains translations for classes in the \Siel\Acumulus\Web namespace.
 */
class SeverityTranslations extends TranslationCollection
{
    protected $nl = array(
        Severity::Exception => 'Ernstige fout',
        Severity::Error => 'Fout',
        Severity::Warning => 'Waarschuwing',
        Severity::Notice => 'Opmerking',
        Severity::Info => 'Informatie',
        Severity::Log => 'Debug',
        Severity::Success => 'Succes',
        Severity::Unknown => 'Nog niet gezet',
        'severity_unknown' => 'Onbekende severity code %d',
    );

    protected $en = array(
        Severity::Exception => 'Exception',
        Severity::Error => 'Error',
        Severity::Warning => 'Warning',
        Severity::Notice => 'Notice',
        Severity::Info => 'Info',
        Severity::Log => 'Debug',
        Severity::Success => 'Success',
        Severity::Unknown => 'Not yet set',
        'severity_unknown' => 'Unknown severity code %d',
    );
}
