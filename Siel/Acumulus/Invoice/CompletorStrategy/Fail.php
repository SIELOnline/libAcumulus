<?php
namespace Siel\Acumulus\Invoice\CompletorStrategy;

use Siel\Acumulus\Invoice\CompletorStrategyBase;

/**
 * FailStrategy implements a complete strategy that always fails.
 *
 * @todo: should we log a fail differently (e.g. not alter the meta info in the invoice)
 */
class Fail extends CompletorStrategyBase {
  /** @var int This strategy should be executed last. */
  static public $tryOrder = PHP_INT_MAX;

  /**
   * {@inheritdoc}
   */
  public function execute() {
    $this->description = "Fail";
    $this->completedLines = $this->lines2Complete;
    return true;
  }
}
