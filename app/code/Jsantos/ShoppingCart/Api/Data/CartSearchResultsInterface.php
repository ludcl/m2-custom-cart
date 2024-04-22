<?php

declare(strict_types=1);

namespace Jsantos\ShoppingCart\Api\Data;

// phpcs:disable PSR12.Properties.ConstantVisibility.NotFound

/**
 * Interface CartSearchResultsInterface
 * @api
 * @since 1.0.0
 */
interface CartSearchResultsInterface extends \Magento\Framework\Api\SearchResultsInterface
{
    /**#@+
     * Constants defined for keys of array, makes typos less likely
     */
    const KEY_ITEMS = 'items';

    const KEY_SEARCH_CRITERIA = 'search_criteria';

    const KEY_TOTAL_COUNT = 'total_count';

    /**#@-*/

    /**
     * Get carts list.
     *
     * @return \Jsantos\ShoppingCart\Api\Data\CartInterface[]
     */
    public function getItems();

    /**
     * Set carts list.
     *
     * @param \Jsantos\ShoppingCart\Api\Data\CartInterface[] $items
     * @return $this
     */
    public function setItems(array $items);

    /**
     * Get search criteria.
     *
     * @return \Magento\Framework\Api\SearchCriteriaInterface
     */
    public function getSearchCriteria();

    /**
     * Set search criteria.
     *
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @return $this
     */
    public function setSearchCriteria(\Magento\Framework\Api\SearchCriteriaInterface $searchCriteria);

    /**
     * Get total count.
     *
     * @return int
     */
    public function getTotalCount();

    /**
     * Set total count.
     *
     * @param int $totalCount
     * @return $this
     */
    public function setTotalCount($totalCount);
}
