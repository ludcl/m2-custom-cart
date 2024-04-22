<?php

declare(strict_types=1);

namespace Jsantos\ShoppingCart\Model\Quote\Item;

use Jsantos\ShoppingCart\Api\Data\CartItemInterface;
use Magento\Framework\DataObject;

/**
 * Interface CartItemProcessorInterface
 *
 * @api
 */
interface CartItemProcessorInterface
{
    /**
     * Convert cart item to buy request object
     *
     * @param CartItemInterface $cartItem
     * @return DataObject|null
     */
    public function convertToBuyRequest(CartItemInterface $cartItem): ?DataObject;

    /**
     * Process cart item product/custom options
     *
     * @param CartItemInterface $cartItem
     * @return CartItemInterface
     */
    public function processOptions(CartItemInterface $cartItem): CartItemInterface;
}
