<?php

declare(strict_types=1);

namespace Jsantos\CustomCart\Api;

/**
 * Interface: Customcart item repository.
 * @api
 * @since 1.0.0
 */
interface CustomcartItemRepositoryInterface
{
    /**
     * Save customcart item.
     *
     * @param \Jsantos\CustomCart\Api\Data\CustomcartItemInterface $customcartItem
     * @return \Jsantos\CustomCart\Api\Data\CustomcartItemInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function save(
        \Jsantos\CustomCart\Api\Data\CustomcartItemInterface $customcartItem
    ): Data\CustomcartItemInterface;

    /**
     * Retrieve customcart item.
     *
     * @param int $id
     * @return \Jsantos\CustomCart\Api\Data\CustomcartItemInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getById($id): Data\CustomcartItemInterface;

    /**
     * Delete customcart item.
     *
     * @param \Jsantos\CustomCart\Api\Data\CustomcartItemInterface $customcartItem
     * @return bool true on success
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function delete(\Jsantos\CustomCart\Api\Data\CustomcartItemInterface $customcartItem): bool;

    /**
     * Delete customcart item by ID.
     *
     * @param int $id
     * @return bool true on success
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function deleteById($id): bool;
}
