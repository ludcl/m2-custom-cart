<?php

declare(strict_types=1);

namespace Jsantos\CustomCart\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

/**
 * Interface for customcart item search results.
 * @api
 * @since 1.0.0
 */
interface CustomcartItemSearchResultsInterface extends SearchResultsInterface
{
    /**
     * Get customcart item list.
     *
     * @return \Jsantos\CustomCart\Api\Data\CustomcartItemInterface[]
     */
    public function getItems();

    /**
     * Set customcart item list.
     *
     * @param \Jsantos\CustomCart\Api\Data\CustomcartItemInterface[] $items
     * @return $this
     */
    public function setItems(array $items);
}
