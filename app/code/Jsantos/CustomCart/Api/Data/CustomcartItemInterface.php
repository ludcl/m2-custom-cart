<?php

declare(strict_types=1);

namespace Jsantos\CustomCart\Api\Data;

/**
 * Customcart_item interface.
 * @api
 * @since 1.0.0
 * phpcs:disable PSR12.Properties.ConstantVisibility.NotFound
 */
interface CustomcartItemInterface
{
    const ITEM_ID = 'item_id';
    const CUSTOMCART_ID = 'customcart_id';
    const PRODUCT_ID = 'product_id';
    const SKU = 'sku';
    const NAME = 'name';
    const QTY = 'qty';
    const PRICE = 'price';
    const ROW_SUBTOTAL = 'row_subtotal';
    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    /**
     * Get Item ID
     *
     * @return int|null
     */
    public function getItemId();

    /**
     * Get Custom Cart ID
     *
     * @return int|null
     */
    public function getCustomcartId();

    /**
     * Set Custom Cart ID
     *
     * @param int $customcartId
     * @return $this
     */
    public function setCustomcartId($customcartId);

    /**
     * Get Product ID
     *
     * @return int|null
     */
    public function getProductId();

    /**
     * Set Product ID
     *
     * @param int $productId
     * @return $this
     */
    public function setProductId($productId);

    /**
     * Get SKU
     *
     * @return string|null
     */
    public function getSku();

    /**
     * Set SKU
     *
     * @param string $sku
     * @return $this
     */
    public function setSku($sku);

    /**
     * Get Name
     *
     * @return string|null
     */
    public function getName();

    /**
     * Set Name
     *
     * @param string $name
     * @return $this
     */
    public function setName($name);

    /**
     * Get Qty
     *
     * @return int|null
     */
    public function getQty();

    /**
     * Set Qty
     *
     * @param int $qty
     * @return $this
     */
    public function setQty($qty);

    /**
     * Get Price
     *
     * @return float|null
     */
    public function getPrice();

    /**
     * Set Price
     *
     * @param float $price
     * @return $this
     */
    public function setPrice($price);

    /**
     * Get Row Subtotal
     *
     * @return float|null
     */
    public function getRowSubtotal();

    /**
     * Set Row Subtotal
     *
     * @param float $rowSubtotal
     * @return $this
     */
    public function setRowSubtotal($rowSubtotal);

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
}
