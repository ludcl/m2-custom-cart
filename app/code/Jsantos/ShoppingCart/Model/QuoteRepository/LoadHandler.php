<?php

declare(strict_types=1);

namespace Jsantos\ShoppingCart\Model\QuoteRepository;

use Jsantos\ShoppingCart\Api\Data\CartInterface;
use Jsantos\ShoppingCart\Model\Quote;

class LoadHandler
{
    /**
     * Loads the cart items for the given quote.
     *
     * @param CartInterface $quote The quote instance.
     * @return CartInterface The quote instance with loaded items.
     */
    public function load(CartInterface $quote): CartInterface
    {
        if (!$quote->getIsActive()) {
            return $quote;
        }
        /** @var CartInterface $quote */
        $quote->setItems($quote->getAllVisibleItems());

        return $quote;
    }
}
