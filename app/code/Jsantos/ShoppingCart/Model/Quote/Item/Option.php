<?php

declare(strict_types=1);

namespace Jsantos\ShoppingCart\Model\Quote\Item;

use Jsantos\ShoppingCart\Model\Quote\Item;
use Magento\Catalog\Model\Product;
use Magento\Framework\Model\AbstractModel;

/**
 * Item option model
 *
 * @api
 */
class Option extends AbstractModel implements
    \Magento\Catalog\Model\Product\Configuration\Item\Option\OptionInterface
{
    /**
     * @var Item
     */
    protected Item $item;

    /**
     * @var Product
     */
    protected Product $product;

    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(\Jsantos\ShoppingCart\Model\ResourceModel\Quote\Item\Option::class);
    }

    /**
     * Checks that item option model has data changes
     *
     * @return boolean
     */
    protected function _hasModelChanged(): bool
    {
        if (!$this->hasDataChanges()) {
            return false;
        }

        return $this->_getResource()->hasDataChanged($this);
    }

    /**
     * Set quote item
     *
     * @param Item $item
     * @return $this
     */
    public function setItem(Item $item): static
    {
        $this->setItemId($item->getId());
        $this->item = $item;
        return $this;
    }

    /**
     * Get option item
     *
     * @return Item
     */
    public function getItem(): Item
    {
        return $this->item;
    }

    /**
     * Set option product
     *
     * @param Product $product
     * @return $this
     */
    public function setProduct(Product $product): static
    {
        $this->setProductId($product->getId());
        $this->product = $product;
        return $this;
    }

    /**
     * Get option product
     *
     * @return Product
     */
    public function getProduct(): Product
    {
        return $this->product;
    }

    /**
     * Get option value
     *
     * @return mixed
     */
    public function getValue(): mixed
    {
        return $this->_getData('value');
    }

    /**
     * Initialize item identifier before save data
     *
     * @return $this
     */
    public function beforeSave(): static
    {
        if ($this->getItem()) {
            $this->setItemId($this->getItem()->getId());
        }
        return parent::beforeSave();
    }

    /**
     * Clone option object
     *
     * @return $this
     */
    public function __clone()
    {
        $this->setId(null);
        $this->item = null;
        return $this;
    }
}
