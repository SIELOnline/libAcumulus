<?php
namespace Siel\Acumulus\Helpers;

/**
 * Contains translations for classes in the \Siel\Acumulus\ApiClient namespace.
 *
 * @noinspection PhpUnused : Loaded by the Container
 */
class SeverityTranslations extends TranslationCollection
{
    protected $nl = [
        Severity::Exception => 'Ernstige fout',
        Severity::Error => 'Fout',
        Severity::Warning => 'Waarschuwing',
        Severity::Notice => 'Opmerking',
        Severity::Info => 'Informatie',
        Severity::Log => 'Debug',
        Severity::Success => 'Succes',
        Severity::Unknown => 'Nog niet gezet',
        'severity_unknown' => 'Onbekend bericht-ernstniveau %d',
    ];

    protected $en = [
        Severity::Exception => 'Exception',
        Severity::Error => 'Error',
        Severity::Warning => 'Warning',
        Severity::Notice => 'Notice',
        Severity::Info => 'Info',
        Severity::Log => 'Debug',
        Severity::Success => 'Success',
        Severity::Unknown => 'Not yet set',
        'severity_unknown' => 'Unknown severity level %d',
    ];
}
