<?php

declare(strict_types=1);

namespace Jsantos\CustomCart\Model;

use Jsantos\CustomCart\Api\Data\CustomcartItemInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Catalog\Model\ProductRepository as ProductRepository;

class CustomcartItem extends AbstractModel implements CustomcartItemInterface
{
    /**
     * @param ProductRepository $productRepository
     * @param Context $context
     * @param Registry $registry
     * @param AbstractResource $resource
     * @param AbstractDb $resourceCollection
     * @param array $data
     */
    public function __construct(
        protected ProductRepository $productRepository,
        Context $context,
        Registry $registry,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $registry,
            $resource,
            $resourceCollection,
            $data
        );
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
     * Get Product Entity
     *
     * @return ProductInterface
     * @throws NoSuchEntityException
     */
    public function getProduct(): ProductInterface
    {
        return $this->productRepository->getById($this->getProductId());
    }

    /**
     * Compare items
     *
     * @param CustomcartItemInterface $item
     * @return bool
     */
    public function compare(CustomcartItemInterface $item): bool
    {
        return ($this->getSku() !== null && $this->getSku() === $item->getSku());
    }
}
