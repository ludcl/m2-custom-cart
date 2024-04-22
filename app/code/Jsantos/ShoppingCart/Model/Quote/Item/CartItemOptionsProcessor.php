<?php

declare(strict_types=1);

namespace Jsantos\ShoppingCart\Model\Quote\Item;

use Magento\Framework\DataObject;
use Jsantos\ShoppingCart\Api\Data\CartItemInterface;

/**
 * Custom Cart item options processor
 *
 * @api
 */
class CartItemOptionsProcessor
{
    /**
     * @var CartItemProcessorInterface[]
     */
    private array $cartItemProcessors = [];

    /**
     * @param CartItemProcessorsPool $cartItemProcessorsPool
     */
    public function __construct(CartItemProcessorsPool $cartItemProcessorsPool)
    {
        $this->cartItemProcessors = $cartItemProcessorsPool->getCartItemProcessors();
    }

    /**
     * Get the buy request for a given product type and cart item.
     *
     * @param string $productType The product type.
     * @param CartItemInterface $cartItem The cart item.
     * @return DataObject|float The buy request.
     */
    public function getBuyRequest(string $productType, CartItemInterface $cartItem): DataObject|float
    {
        $params = (isset($this->cartItemProcessors[$productType]))
            ? $this->cartItemProcessors[$productType]->convertToBuyRequest($cartItem)
            : null;

        $params = ($params === null) ? $cartItem->getQty() : $params->setData('qty', $cartItem->getQty());
        return $this->addCustomOptionsToBuyRequest($cartItem, $params);
    }

    /**
     * Add custom options to buy request.
     *
     * @param CartItemInterface $cartItem
     * @param float|DataObject $params
     * @return DataObject|float
     */
    private function addCustomOptionsToBuyRequest(
        CartItemInterface $cartItem,
        DataObject|float $params
    ): DataObject|float {
        if (isset($this->cartItemProcessors['custom_options'])) {
            $buyRequestUpdate = $this->cartItemProcessors['custom_options']->convertToBuyRequest($cartItem);
            if (!$buyRequestUpdate) {
                return $params;
            }
            if ($params instanceof DataObject) {
                $buyRequestUpdate->addData($params->getData());
            } elseif (is_numeric($params)) {
                $buyRequestUpdate->setData('qty', $params);
            }
            return $buyRequestUpdate;
        }
        return $params;
    }

    /**
     * Apply custom options to a cart item.
     *
     * @param CartItemInterface $cartItem The cart item to apply custom options to.
     * @return CartItemInterface The modified cart item with custom options applied.
     */
    public function applyCustomOptions(CartItemInterface $cartItem): CartItemInterface
    {
        if (isset($this->cartItemProcessors['custom_options'])) {
            $cartItem = $this->cartItemProcessors['custom_options']->processOptions($cartItem);
        }
        return $cartItem;
    }

    /**
     * Add product options to cart item.
     *
     * @param string $productType
     * @param CartItemInterface $cartItem
     * @return CartItemInterface
     */
    public function addProductOptions(string $productType, CartItemInterface $cartItem): CartItemInterface
    {
        return (isset($this->cartItemProcessors[$productType]))
            ? $this->cartItemProcessors[$productType]->processOptions($cartItem)
            : $cartItem;
    }
}
