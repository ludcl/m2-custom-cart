<?php

declare(strict_types=1);

namespace Jsantos\ShoppingCart\Model\ResourceModel\Quote\Item\Option;

use Jsantos\ShoppingCart\Model\Quote\Item;
use Jsantos\ShoppingCart\Model\ResourceModel\Quote\Item\Option;
use Magento\Catalog\Model\Product;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * Item option collection
 *
 */
class Collection extends AbstractCollection
{
    /**
     * Array of option ids grouped by item id
     *
     * @var array
     */
    protected array $_optionsByItem = [];

    /**
     * Array of option ids grouped by product id
     *
     * @var array
     */
    protected array $_optionsByProduct = [];

    /**
     * Apply quote item(s) filter to collection
     *
     * @param int|array|Item $item
     * @return $this
     */
    public function addItemFilter(int|array|Item $item): Collection
    {
        if (empty($item)) {
            $this->_totalRecords = 0;
            $this->_setIsLoaded(true);
            //$this->addFieldToFilter('item_id', '');
        } elseif (is_array($item)) {
            $this->addFieldToFilter('item_id', ['in' => $item]);
        } elseif ($item instanceof Item) {
            $this->addFieldToFilter('item_id', $item->getId());
        } else {
            $this->addFieldToFilter('item_id', $item);
        }

        return $this;
    }

    /**
     * Get array of all product ids
     *
     * @return array
     */
    public function getProductIds(): array
    {
        $this->load();

        return array_keys($this->_optionsByProduct);
    }

    /**
     * Get all option for item
     *
     * @param mixed $item
     * @return array
     */
    public function getOptionsByItem(mixed $item): array
    {
        if ($item instanceof Item) {
            $itemId = $item->getId();
        } else {
            $itemId = $item;
        }

        $this->load();

        $options = [];
        if (isset($this->_optionsByItem[$itemId])) {
            foreach ($this->_optionsByItem[$itemId] as $optionId) {
                $options[] = $this->_items[$optionId];
            }
        }

        return $options;
    }

    /**
     * Get all option for item
     *
     * @param int|Product $product
     * @return array
     */
    public function getOptionsByProduct(int|Product $product): array
    {
        if ($product instanceof Product) {
            $productId = $product->getId();
        } else {
            $productId = $product;
        }

        $this->load();

        $options = [];
        if (isset($this->_optionsByProduct[$productId])) {
            foreach ($this->_optionsByProduct[$productId] as $optionId) {
                $options[] = $this->_items[$optionId];
            }
        }

        return $options;
    }

    /**
     * Define resource model for collection
     *
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(
            \Jsantos\ShoppingCart\Model\Quote\Item\Option::class,
            Option::class
        );
    }

    /**
     * Fill array of options by item and product
     *
     * @return $this
     */
    protected function _afterLoad(): Collection
    {
        parent::_afterLoad();

        foreach ($this as $option) {
            $optionId = $option->getId();
            $itemId = $option->getItemId();
            $productId = $option->getProductId();
            if (isset($this->_optionsByItem[$itemId])) {
                $this->_optionsByItem[$itemId][] = $optionId;
            } else {
                $this->_optionsByItem[$itemId] = [$optionId];
            }
            if (isset($this->_optionsByProduct[$productId])) {
                $this->_optionsByProduct[$productId][] = $optionId;
            } else {
                $this->_optionsByProduct[$productId] = [$optionId];
            }
        }

        return $this;
    }
}
