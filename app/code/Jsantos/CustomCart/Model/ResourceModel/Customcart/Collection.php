<?php

declare(strict_types=1);

namespace Jsantos\CustomCart\Model\ResourceModel\Customcart;

use Jsantos\CustomCart\Model\ResourceModel\Customcart as CustomCartResourceModel;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Jsantos\CustomCart\Model\CustomCart;

class Collection extends AbstractCollection
{
    /**
     * Customcart Collection constructor
     *
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(CustomCart::class, CustomCartResourceModel::class);
    }
}
