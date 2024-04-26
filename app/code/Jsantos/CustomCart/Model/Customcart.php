<?php

declare(strict_types=1);

namespace Jsantos\CustomCart\Model;

use Exception;
use Jsantos\CustomCart\Api\CustomcartItemRepositoryInterface;
use Jsantos\CustomCart\Api\Data\CustomcartInterface;
use Jsantos\CustomCart\Api\Data\CustomcartItemInterface;
use Jsantos\CustomCart\Model\ResourceModel\CustomcartItem\Collection as CustomcartItemCollection;
use Jsantos\CustomCart\Model\ResourceModel\CustomcartItem\CollectionFactory as CustomcartItemCollectionFactory;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status as ProductStatus;
use Magento\Config\Model\Config\Backend\Admin\Custom;
use Magento\Eav\Model\Entity\Collection\AbstractCollection;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;

/**
 * Customcart model
 */
class Customcart extends AbstractModel implements CustomcartInterface
{
    /**
     * Quote items collection
     *
     * @var CustomcartItemCollection|null
     */
    protected ?CustomcartItemCollection $items = null;

    /**
     * @param CustomcartItemCollectionFactory $customcartItemCollectionFactory
     * @param CustomcartItemRepositoryInterface $customcartItemRepository
     * @param Context $context
     * @param Registry $registry
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        protected CustomcartItemCollectionFactory $customcartItemCollectionFactory,
        protected CustomcartItemRepositoryInterface $customcartItemRepository,
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
    public function getItems(): ?array
    {
        $items = [];

        foreach ($this->getItemsCollection() as $item) {
            /** @var ProductInterface $product */
            $product = $item->getProduct();

            // Remove the item if the product has been deleted or disabled
            if ($product && (int)$product->getStatus() !== ProductStatus::STATUS_DISABLED) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * Retrieve quote items collection
     *
     * @param bool $useCache
     * @return CustomcartItemCollection
     */
    public function getItemsCollection(bool $useCache = true): CustomcartItemCollection
    {
        // Condition used for tests
        if ($this->hasItemsCollection() && $useCache) {
            return $this->getData('items_collection');
        }
        if (null === $this->items || !$useCache) {
            $collection = $this->customcartItemCollectionFactory->create();
            $collection->addFieldToFilter(CustomcartItemInterface::CUSTOMCART_ID, $this->getEntityId());
            $this->items = $collection;
        }

        return $this->items;
    }

    /**
     * @inheritDoc
     * @throws CouldNotDeleteException
     * @throws LocalizedException
     */
    public function setItems(array $items): static
    {
        $this->removeAllItems();

        foreach ($items as $item) {
            $this->addItem($item);
        }

        return $this;
    }

    /**
     * Merge Custom Carts
     *
     * @param CustomcartInterface $customcart
     * @return $this
     * @throws LocalizedException
     */
    public function merge(CustomcartInterface $customcart): static
    {
        foreach ($customcart->getItems() as $item) {
            $found = false;
            foreach ($this->getItems() as $customcartItem) {
                if ($customcartItem->compare($item)) {
                    $customcartItem->setQty($customcartItem->getQty() + $item->getQty());
                    $customcartItem->setSubtotal($customcartItem->getPrice() * $customcartItem->getQty());
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $newItem = clone $item;
                $this->addItem($newItem);
            }
        }

        return $this;
    }

    /**
     * Adding new item to Custom Cart
     *
     * @param CustomcartItemInterface $item
     * @return $this
     * @throws LocalizedException|Exception
     */
    public function addItem(CustomcartItemInterface $item): static
    {
        $item->setCustomcartId($this->getEntityId());
        if (!$item->getRowSubtotal()) {
            $item->setRowSubtotal($item->getQty() * $item->getPrice());
        }
        if (!$item->getId()) {
            $this->customcartItemRepository->save($item);
        }

        return $this;
    }

    /**
     * Remove customcart item by item identifier
     *
     * @param int $itemId
     * @return $this
     * @throws CouldNotDeleteException
     */
    public function removeItem(int $itemId): static
    {
        try {
            $this->customcartItemRepository->deleteById($itemId);
        } catch (NoSuchEntityException|LocalizedException $e) {
            throw new CouldNotDeleteException(__('Could not delete the custom cart item.'));
        }

        return $this;
    }

    /**
     * Remove All Customcart items
     *
     * @return $this
     * @throws CouldNotDeleteException
     */
    public function removeAllItems(): static
    {
        foreach ($this->getItemsCollection() as $item) {
            $this->removeItem($item->getId());
        }
        $this->items = $this->getItemsCollection(false);

        return $this;
    }

    /**
     * Collect totals
     *
     * @return $this
     */
    public function collectTotals(): static
    {
        $subtotal = 0;
        $itemsQty = 0;

        foreach ($this->getItems() as $item) {
            $subtotal += $item->getPrice() * $item->getQty();
            $itemsQty += $item->getQty();
        }
        $this->setSubtotal($subtotal);
        $this->setItemsQty($itemsQty);

        return $this;
    }
}
