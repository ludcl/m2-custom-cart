<?php

declare(strict_types=1);

namespace Jsantos\CustomCart\Model;

use Jsantos\CustomCart\Api\Data\CustomcartItemInterface;
use Magento\Framework\Model\AbstractModel;

class CustomcartItem extends AbstractModel implements CustomcartItemInterface
{
    /**
     * Initialize the object and set the resource model to be used.
     *
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(ResourceModel\CustomcartItem::class);
    }

    /**
     * @inheritDoc
     */
    public function getItemId()
    {
        return $this->getData(self::ITEM_ID);
    }

    /**
     * @inheritDoc
     */
    public function getCustomcartId()
    {
        return $this->getData(self::CUSTOMCART_ID);
    }

    /**
     * @inheritDoc
     */
    public function setCustomcartId($customcartId)
    {
        return $this->setData(self::CUSTOMCART_ID, $customcartId);
    }

    /**
     * @inheritDoc
     */
    public function getProductId()
    {
        return $this->getData(self::PRODUCT_ID);
    }

    /**
     * @inheritDoc
     */
    public function setProductId($productId)
    {
        return $this->setData(self::PRODUCT_ID, $productId);
    }

    /**
     * @inheritDoc
     */
    public function getSku()
    {
        return $this->getData(self::SKU);
    }

    /**
     * @inheritDoc
     */
    public function setSku($sku)
    {
        return $this->setData(self::SKU, $sku);
    }

    /**
     * @inheritDoc
     */
    public function getName()
    {
        return $this->getData(self::NAME);
    }

    /**
     * @inheritDoc
     */
    public function setName($name)
    {
        return $this->setData(self::NAME, $name);
    }

    /**
     * @inheritDoc
     */
    public function getQty()
    {
        return $this->getData(self::QTY);
    }

    /**
     * @inheritDoc
     */
    public function setQty($qty)
    {
        return $this->setData(self::QTY, $qty);
    }

    /**
     * @inheritDoc
     */
    public function getPrice()
    {
        return $this->getData(self::PRICE);
    }

    /**
     * @inheritDoc
     */
    public function setPrice($price)
    {
        return $this->setData(self::PRICE, $price);
    }

    /**
     * @inheritDoc
     */
    public function getRowSubtotal()
    {
        return $this->getData(self::ROW_SUBTOTAL);
    }

    /**
     * @inheritDoc
     */
    public function setRowSubtotal($rowSubtotal)
    {
        return $this->setData(self::ROW_SUBTOTAL, $rowSubtotal);
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
}
