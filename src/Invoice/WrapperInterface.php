<?php

declare(strict_types=1);

namespace Siel\Acumulus\Invoice;

/**
 * WrapperInterface implements an interface that all adapter/wrapper classes adhere to.
 *
 * Note: It is implemented by {@see WrapperTrait}.
 */
interface WrapperInterface
{
    /**
     * Returns the common name for the wrapped object.
     *
     * This base implementation returns the non-namespaced class, but this can be
     * overridden by using classes. E.g. {@see \Siel\Acumulus\Invoice\Source} will return
     * {@see \Siel\Acumulus\Invoice\Source::Order} or
     * {@see \Siel\Acumulus\Invoice\Source::CreditNote}.
     */
    public function getType(): string;

    /**
     * Returns the (internal) id of the wrapped object.
     */
    public function getId(): int;

    /**
     * Returns the reference of the wrapped object.
     *
     * The reference is a unique string used in communication with the client. In absence
     * of a specific value for this, the {@see WrapperInterface::getId() internal id} will
     * be returned.
     */
    public function getReference(): int|string;
    /**
     * The translated {@see WrapperInterface::getType() type} of the wrapped object.
     *
     * @param int $case
     *   - MB_CASE_LOWER (1): convert to all lower case
     *   - MB_CASE_UPPER (0): convert to all upper case
     *   - MB_CASE_TITLE (2): convert first character to upper case
     *   - Any other value or not passed: return as is, do not convert
     */
    public function getLabel(int $case = -1): string;

    /**
     * Returns the {@see WrapperInterface::getLabel() label} and
     * {@see WrapperInterface::getReference() reference} of the wrapped object.
     *
     * @param int $case
     *   - MB_CASE_LOWER (1): convert to all lower case
     *   - MB_CASE_UPPER (0): convert to all upper case
     *   - MB_CASE_TITLE (2): convert first character to upper case
     *   - Any other value or not passed: return as is, do not convert
     */
    public function getLabelReference(int $case = -1): string;

    /**
     * Returns the wrapped, shop specific, "object".
     */
    public function getShopObject(): object|array;
}
