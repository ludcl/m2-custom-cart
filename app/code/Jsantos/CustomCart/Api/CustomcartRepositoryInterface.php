<?php

declare(strict_types=1);

namespace Jsantos\CustomCart\Api;

interface CustomcartRepositoryInterface
{
    /**
     * Save customcart.
     *
     * @param \Jsantos\CustomCart\Api\Data\CustomcartInterface $customcart
     * @return \Jsantos\CustomCart\Api\Data\CustomcartInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function save(\Jsantos\CustomCart\Api\Data\CustomcartInterface $customcart): Data\CustomcartInterface;

    /**
     * Retrieve customcart.
     *
     * @param int $id
     * @return \Jsantos\CustomCart\Api\Data\CustomcartInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getById($id): Data\CustomcartInterface;

    /**
     * Retrieve customcarts matching the specified criteria.
     *
     * @param \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
     * @return \Jsantos\CustomCart\Api\Data\CustomcartSearchResultsInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getList(
        \Magento\Framework\Api\SearchCriteriaInterface $searchCriteria
    ): Data\CustomcartSearchResultsInterface;

    /**
     * Delete customcart.
     *
     * @param \Jsantos\CustomCart\Api\Data\CustomcartInterface $customcart
     * @return bool true on success
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function delete(\Jsantos\CustomCart\Api\Data\CustomcartInterface $customcart): bool;

    /**
     * Delete customcart by ID.
     *
     * @param int $id
     * @return bool true on success
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function deleteById($id): bool;
}
