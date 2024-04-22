<?php

declare(strict_types=1);

namespace Jsantos\ShoppingCart\Model\ResourceModel\Quote;

use Jsantos\ShoppingCart\Model\Quote;

class Collection extends \Magento\Framework\Model\ResourceModel\Db\VersionControl\Collection
{
    /**
     * Collection constructor method
     *
     * @inheirtdoc
     */
    protected function _construct(): void
    {
        $this->_init(Quote::class, \Jsantos\ShoppingCart\Model\ResourceModel\Quote::class);
    }
}
