<?php

declare(strict_types=1);

namespace Jsantos\ShoppingCart\Api;

/**
 * Custom Cart repository interface.
 * @api
 * @since 1.0.0
 */
interface CartRepositoryInterface
{
    /**
     * Enables an administrative user to return information for a specified cart.
     *
     * @param int $cartId
     * @return \Jsantos\ShoppingCart\Api\Data\CartInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function get($cartId);

    /**
     * Enables administrative users to list carts that match specified search criteria.
     *
     * This call returns an array of objects, but detailed information about each object’s attributes might not be
     * included.  See https://developer.adobe.com/commerce/webapi/rest/attributes#CartRepositoryInterface to determine
     * which call to use to get detailed information about all attributes for an object.
     *
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @return \Jsantos\ShoppingCart\Api\Data\CartSearchResultsInterface
     */
    public function getList(\Magento\Framework\Api\SearchCriteriaInterface $searchCriteria);

    /**
     * Get quote by customer Id
     *
     * @param int $customerId
     * @param int[] $sharedStoreIds
     * @return \Jsantos\ShoppingCart\Api\Data\CartInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getForCustomer($customerId, array $sharedStoreIds = []);

    /**
     * Get active quote by id
     *
     * @param int $cartId
     * @param int[] $sharedStoreIds
     * @return \Jsantos\ShoppingCart\Api\Data\CartInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getActive($cartId, array $sharedStoreIds = []);

    /**
     * Get active quote by customer Id
     *
     * @param int $customerId
     * @param int[] $sharedStoreIds
     * @return \Jsantos\ShoppingCart\Api\Data\CartInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getActiveForCustomer($customerId, array $sharedStoreIds = []);

    /**
     * Save quote
     *
     * @param \Jsantos\ShoppingCart\Api\Data\CartInterface $quote
     * @return void
     */
    public function save(\Jsantos\ShoppingCart\Api\Data\CartInterface $quote);

    /**
     * Delete quote
     *
     * @param \Jsantos\ShoppingCart\Api\Data\CartInterface $quote
     * @return void
     */
    public function delete(\Jsantos\ShoppingCart\Api\Data\CartInterface $quote);
}
