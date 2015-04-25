<?php

namespace Siel\Acumulus\Invoice;

/**
 * Wraps a source for an invoice, typically an order or a credit note.
 *
 * By defining a wrapper around orders from a specific web shop we can more
 * easily (and in a type safe way) pass them around.
 */
abstract class Source {
  // Invoice source type constants.
  const Other = 'other';
  const Order = 'order';
  const CreditNote = 'credit note';

  /** @var string */
  protected $type;

  /** @var string */
  protected $id;

  /** @var array|object */
  protected $source;

  /**
   * @param string $type
   * @param string|array|object $idOrSource
   */
  public function __construct($type, $idOrSource) {
    $this->type   = $type;
    if (is_scalar($idOrSource)) {
      $this->id = $idOrSource;
      $this->setSource();
    }
    else {
      $this->source = $idOrSource;
      $this->id = $this->setId();
    }
  }

  /**
   * @return string
   */
  public function getType() {
    return $this->type;
  }

  /**
   * Sets the source based on type and id.
   */
  abstract protected function setSource();

  /**
   * @return array|object
   */
  public function getSource() {
    return $this->source;
  }

  /**
   * Sets the id based on type and source.
   */
  abstract protected function setId();

  /**
   * Returns the internal id of this invoice source.
   *
   * @return string|int
   *   The internal id.
   */
  public function getId() {
    return $this->id;
  }

  /**
   * Returns the user facing reference for this invoice source.
   *
   * Should be overridden when this is not the internal id.
   *
   * @return string|int
   *   The user facing id for this invoice source. This is not necessarily the
   *   internal id!
   */
  public function getReference() {
    return $this->getId();
  }

}
