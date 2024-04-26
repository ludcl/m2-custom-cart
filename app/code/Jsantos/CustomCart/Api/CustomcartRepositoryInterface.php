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
     * Retrieve customcart by id.
     *
     * @param int $id
     * @return \Jsantos\CustomCart\Api\Data\CustomcartInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getById($id): Data\CustomcartInterface;

    /**
     * Retrieve customcart by customer id.
     *
     * @param int $customerId
     * @return \Jsantos\CustomCart\Api\Data\CustomcartInterface
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getByCustomerId(int $customerId): Data\CustomcartInterface;

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
