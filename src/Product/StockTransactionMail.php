<?php

declare(strict_types=1);

namespace Siel\Acumulus\Product;

use Siel\Acumulus\Fld;
use Siel\Acumulus\Invoice\Item;
use Siel\Acumulus\Invoice\Source;
use Siel\Acumulus\Mail\Mail;

use function sprintf;

/**
 * StockTransaction mail creates and send the mail following a stock transaction request.
 *
 * @method null|StockTransactionResult getResult();
 * @method Source getSource();
 */
class StockTransactionMail extends Mail
{
    protected function getItem(): Item
    {
        return $this->args['item'];
    }

    protected function getProduct(): ?Product
    {
        return $this->args['product'];
    }

    protected function getPlaceholders(): array
    {
        $stockInfo = $this->getResult()?->getMainApiResponse();
        return [
            '{item_label}' => $this->getItem()->getLabel(MB_CASE_TITLE),
            '{item_reference}' => $this->getItem()->getReference(),
            '{product_label}' => $this->getProduct()?->getLabel(MB_CASE_TITLE) ?? 'Product',
            '{product_reference}' => $this->getProduct()?->getReference() ?? 'unknown',
            '{product_link}' => $this->getProduct()?->getLink() ?? '#',
            '{change_label}' => mb_convert_case($this->t('change'), MB_CASE_TITLE),
            '{change}' => sprintf('%+.2g', $this->args['change']),
            '{product_acumulus_id}' =>  $stockInfo !== null && isset($stockInfo[Fld::ProductId])
                ? $stockInfo[Fld::ProductId]
                : $this->t('unknown'),
            '{stock_level_label}' => $this->t('stock_level'),
            '{stock_level}' => $stockInfo !== null && isset($stockInfo[Fld::StockAmount])
                ? $stockInfo[Fld::StockAmount]
                : $this->t('message_not_created'),
        ] + parent::getPlaceholders();
    }

    protected function getIntroSentences(): array
    {
        $sentences = parent::getIntroSentences();
        /** @noinspection NullPointerExceptionInspection */
        if ($this->getResult()->isMatchError())
        {
            array_pop($sentences);
            $sentences[] = 'mail_body_errors_local';
        }
        return $sentences;
    }

    protected function getAboutLines(): array
    {
        /** @noinspection HtmlUnknownTarget */
        return [
                '{source_label}' => '{source_reference}',
                '{item_label}' => '{item_reference}',
                '{product_label} ({shop})' => '<a href="{product_link}">{product_reference}</a>',
                '{change_label}' => '{change}',
                '{product_label} ({module_name})' => '{product_acumulus_id}',
                '{stock_level_label} ({module_name})' => '{stock_level}',
            ] + parent::getAboutLines();
    }
}
