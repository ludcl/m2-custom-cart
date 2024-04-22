<?php

declare(strict_types=1);

namespace Jsantos\ShoppingCart\Model;

class QuoteIdMask extends \Magento\Quote\Model\QuoteIdMask
{

    /**
     * Method constructor
     *
     * @inheirtdoc
     */
    protected function _construct(): void
    {
        $this->_init(ResourceModel\Quote\QuoteIdMask::class);
    }
}
