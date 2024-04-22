<?php

declare(strict_types=1);

namespace Jsantos\ShoppingCart\Model\QuoteRepository;

use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Jsantos\ShoppingCart\Api\Data\CartInterface;
use Magento\Quote\Model\Quote\Item\CartItemPersister;
use Jsantos\ShoppingCart\Model\ResourceModel\Quote;

/**
 * Handler for saving quote.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.CookieAndSessionMisuse)
 */
class SaveHandler
{
    /**
     * @param Quote $quoteResourceModel
     * @param CartItemPersister $cartItemPersister
     */
    public function __construct(
        private Quote $quoteResourceModel,
        private CartItemPersister $cartItemPersister
    ) {
    }

    /**
     * Process and save quote data
     *
     * @param CartInterface $quote
     * @return CartInterface
     * @throws InputException
     * @throws CouldNotSaveException
     * @throws LocalizedException
     */
    public function save(CartInterface $quote): CartInterface
    {
        // Quote Item processing
        $items = $quote->getItems();

        if ($items) {
            foreach ($items as $item) {
                if (!$item->isDeleted()) {
                    $quote->setLastAddedItem($this->cartItemPersister->save($quote, $item));
                }
            }
        }

        $this->quoteResourceModel->save($quote->collectTotals());

        return $quote;
    }
}
