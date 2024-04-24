<?php

declare(strict_types=1);

namespace Jsantos\CustomCart\Model;

use Jsantos\CustomCart\Api\Data\CustomcartInterface;
use Magento\Framework\Model\AbstractModel;

class Customcart extends AbstractModel implements CustomcartInterface
{
    /**
     * Customcart model constructor
     *
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(ResourceModel\Customcart::class);
    }

    /**
     * @inheritDoc
     */
    public function getCustomerId()
    {
        return $this->getData(self::CUSTOMER_ID);
    }

    /**
     * @inheritDoc
     */
    public function setCustomerId($customerId)
    {
        return $this->setData(self::CUSTOMER_ID, $customerId);
    }

    /**
     * @inheritDoc
     */
    public function getItemsQty()
    {
        return $this->getData(self::ITEMS_QTY);
    }

    /**
     * @inheritDoc
     */
    public function setItemsQty($itemsQty)
    {
        return $this->setData(self::ITEMS_QTY, $itemsQty);
    }

    /**
     * @inheritDoc
     */
    public function getSubtotal()
    {
        return $this->getData(self::SUBTOTAL);
    }

    /**
     * @inheritDoc
     */
    public function setSubtotal($subtotal)
    {
        return $this->setData(self::SUBTOTAL, $subtotal);
    }

    /**
     * @inheritDoc
     */
    public function getCreatedAt()
    {
        return $this->getData(self::CREATED_AT);
    }

    /**
     * @inheritDoc
     */
    public function getUpdatedAt()
    {
        return $this->getData(self::UPDATED_AT);
    }

    /**
     * @inheritDoc
     */
    public function getItems()
    {
        // TODO: Implement getItems() method.
    }

    /**
     * @inheritDoc
     */
    public function setItems(array $items)
    {
        // TODO: Implement setItems() method.
    }
}
