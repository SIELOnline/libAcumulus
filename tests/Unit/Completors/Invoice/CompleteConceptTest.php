<?php

declare(strict_types=1);

namespace Siel\Acumulus\Tests\Unit\Completors\Invoice;

use PHPUnit\Framework\TestCase;
use Siel\Acumulus\Api;
use Siel\Acumulus\Completors\Invoice\CompleteConcept;
use Siel\Acumulus\Config\Config;
use Siel\Acumulus\Data\DataType;
use Siel\Acumulus\Data\LineType;
use Siel\Acumulus\Meta;
use Siel\Acumulus\Tests\Utils\AcumulusContainer;
use Siel\Acumulus\Tests\Utils\DataObjectFactory;

/**
 * CompleteConceptTest test {@see CompleteConcept}.
 */
class CompleteConceptTest extends TestCase
{
    use AcumulusContainer;
    use DataObjectFactory;

    public function testComplete(): void
    {
        $config = self::getContainer()->getConfig();
        $completor = self::getContainer()->getCompletorTask('Invoice','Concept');

        // Plugin, no warning = false
        $config->set('concept', Config::Concept_Plugin);
        $invoice = $this->getInvoice();
        $completor->complete($invoice);
        $this->assertFalse($invoice->concept);

        // No, no warning = false
        $config->set('concept', Api::Concept_No);
        $invoice = $this->getInvoice();
        $completor->complete($invoice);
        $this->assertFalse($invoice->concept);

        // Yes, no warning = true
        $config->set('concept', Api::Concept_Yes);
        $invoice = $this->getInvoice();
        $completor->complete($invoice);
        $this->assertTrue($invoice->concept);

        // Test not overwrite.
        $config->set('concept', Api::Concept_No);
        $completor->complete($invoice);
        $this->assertTrue($invoice->concept);

        $line = $this->getLine(LineType::Item);
        $line->metadataAdd(Meta::Warning, 'warning');

        // Plugin, warning = true
        $config->set('concept', Config::Concept_Plugin);
        $invoice = $this->getInvoice();
        $invoice->addLine($line);
        $completor->complete($invoice);
        $this->assertTrue($invoice->concept);

        // No, warning = false
        $config->set('concept', Api::Concept_No);
        $invoice = $this->getInvoice();
        $invoice->addLine($line);
        $completor->complete($invoice);
        $this->assertFalse($invoice->concept);

        // Yes, warning = true
        $config->set('concept', Api::Concept_Yes);
        $invoice = $this->getInvoice();
        $invoice->addLine($line);
        $completor->complete($invoice);
        $this->assertTrue($invoice->concept);
    }
}
