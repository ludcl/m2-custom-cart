<?php

declare(strict_types=1);

namespace Jsantos\CustomCart\Api\Data;

/**
 * Customcart interface.
 * @api
 * @since 1.0.0
 * phpcs:disable PSR12.Properties.ConstantVisibility.NotFound
 */
interface CustomcartInterface
{
    const ENTITY_ID = 'entity_id';
    const CUSTOMER_ID = 'customer_id';
    const ITEMS_QTY = 'items_qty';
    const SUBTOTAL = 'subtotal';
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    /**
     * Get Entity ID
     *
     * @return int|null
     */
    public function getEntityId();

    /**
     * Get Customer ID
     *
     * @return int|null
     */
    public function getCustomerId();

    /**
     * Set Customer ID
     *
     * @param int $customerId
     * @return $this
     */
    public function setCustomerId($customerId);

    /**
     * Get Items Qty
     *
     * @return int|null
     */
    public function getItemsQty();

    /**
     * Set Items Qty
     *
     * @param int $itemsQty
     * @return $this
     */
    public function setItemsQty($itemsQty);

    /**
     * Get Subtotal
     *
     * @return float|null
     */
    public function getSubtotal();

    /**
     * Set Subtotal
     *
     * @param float $subtotal
     * @return $this
     */
    public function setSubtotal($subtotal);

    /**
     * Get Created At
     *
     * @return string|null
     */
    public function getCreatedAt();

    /**
     * Get Updated At
     *
     * @return string|null
     */
    public function getUpdatedAt();

    /**
     * Return all the cart items associated to the customercart
     *
     * @return CustomcartItemInterface[]|null
     */
    public function getItems();

    /**
     * Set Items
     *
     * @param CustomcartItemInterface[] $items
     * @return $this
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function setItems(array $items);
}
