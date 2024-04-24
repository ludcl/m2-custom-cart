<?php

declare(strict_types=1);

namespace Jsantos\CustomCart\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

/**
 * Interface for customcart search results.
 * @api
 * @since 1.0.0
 */
interface CustomcartSearchResultsInterface extends SearchResultsInterface
{
    /**
     * Get customcart list.
     *
     * @return \Jsantos\CustomCart\Api\Data\CustomcartInterface[]
     */
    public function getItems();

    /**
     * Set customcart list.
     *
     * @param \Jsantos\CustomCart\Api\Data\CustomcartInterface[] $items
     * @return $this
     */
    public function setItems(array $items);
}
