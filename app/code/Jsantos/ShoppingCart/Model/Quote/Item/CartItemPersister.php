<?php

declare(strict_types=1);

namespace Jsantos\ShoppingCart\Model\Quote\Item;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Jsantos\ShoppingCart\Api\Data\CartInterface;
use Jsantos\ShoppingCart\Api\Data\CartItemInterface;
use Jsantos\ShoppingCart\Model\Quote;
use Jsantos\ShoppingCart\Model\Quote\Item;

/**
 * Cart item save handler
 */
class CartItemPersister
{

    /**
     * @param ProductRepositoryInterface $productRepository
     * @param CartItemOptionsProcessor $cartItemOptionProcessor
     */
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly CartItemOptionsProcessor $cartItemOptionProcessor
    ) {
    }

    /**
     * Save cart item into cart
     *
     * @param CartInterface $quote
     * @param CartItemInterface $item
     * @return CartItemInterface
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function save(CartInterface $quote, CartItemInterface $item)
    {
        /** @var Quote $quote */
        $qty = $item->getQty();
        if (!is_numeric($qty) || $qty <= 0) {
            throw InputException::invalidFieldValue('qty', $qty);
        }
        $cartId = $item->getQuoteId();
        $itemId = $item->getItemId();
        try {
            /** Update existing item */
            if (isset($itemId)) {
                $currentItem = $quote->getItemById($itemId);
                if (!$currentItem) {
                    throw new NoSuchEntityException(
                        __('The %1 Cart doesn\'t contain the %2 item.', $cartId, $itemId)
                    );
                }
                $productType = $currentItem->getProduct()->getTypeId();
                $buyRequestData = $this->cartItemOptionProcessor->getBuyRequest($productType, $item);
                if (is_object($buyRequestData)) {
                    /** Update item product options */
                    if ($quote->getIsActive()) {
                        $item = $quote->updateItem($itemId, $buyRequestData);
                    }
                } else {
                    if ($item->getQty() !== $currentItem->getQty()) {
                        $currentItem->clearMessage();
                        $currentItem->setQty($qty);
                        /**
                         * Qty validation errors are stored as items message
                         * @see \Magento\CatalogInventory\Model\Quote\Item\QuantityValidator::validate
                         */
                        if (!empty($currentItem->getMessage()) && $currentItem->getHasError()) {
                            throw new LocalizedException(__($currentItem->getMessage()));
                        }
                    }
                }
            } else {
                /** add new item to shopping cart */
                $product = $this->productRepository->get($item->getSku());
                $productType = $product->getTypeId();
                $item = $quote->addProduct(
                    $product,
                    $this->cartItemOptionProcessor->getBuyRequest($productType, $item)
                );
                if (is_string($item)) {
                    throw new LocalizedException(__($item));
                }
            }
        } catch (NoSuchEntityException|LocalizedException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new CouldNotSaveException(__("The quote couldn't be saved."));
        }
        $itemId = $item->getId();
        foreach ($quote->getAllItems() as $quoteItem) {
            /** @var Item $quoteItem */
            if ($itemId == $quoteItem->getId()) {
                $item = $this->cartItemOptionProcessor->addProductOptions($productType, $quoteItem);
                return $this->cartItemOptionProcessor->applyCustomOptions($item);
            }
        }
        throw new CouldNotSaveException(__("The quote couldn't be saved."));
    }
}
