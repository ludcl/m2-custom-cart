<?php

declare(strict_types=1);

namespace Jsantos\CustomCart\Model\ResourceModel\CustomcartItem;

use Jsantos\CustomCart\Model\ResourceModel\CustomcartItem as CustomcartItemResourceModel;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Jsantos\CustomCart\Model\CustomcartItem;

class Collection extends AbstractCollection
{
    /**
     * Customcart Item Collection constructor
     *
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(CustomcartItem::class, CustomcartItemResourceModel::class);
    }
}
