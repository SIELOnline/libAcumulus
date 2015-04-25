<?php
namespace Siel\Acumulus\Invoice\CompletorStrategy;

use Siel\Acumulus\Invoice\CompletorStrategyBase;

/**
 * @class FailStrategy implements a complete strategy that always fails.
 */
class Fail extends CompletorStrategyBase {

  /** @var int This strategy should be executed last. */
  static public $tryOrder = PHP_INT_MAX;

  /**
   * {@inheritdoc}
   */
  public function execute() {
    $this->description = "Fail";
    return true;
  }

}
