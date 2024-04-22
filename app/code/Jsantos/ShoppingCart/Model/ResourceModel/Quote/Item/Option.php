<?php

declare(strict_types=1);

namespace Jsantos\ShoppingCart\Model\ResourceModel\Quote\Item;

/**
 * Item option resource model
 *
 */
class Option extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    /**
     * Main table and field initialization
     *
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init('custom_quote_item_option', 'option_id');
    }
}
