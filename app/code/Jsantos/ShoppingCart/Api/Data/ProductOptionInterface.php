<?php

declare(strict_types=1);

namespace Jsantos\ShoppingCart\Api\Data;

use Magento\Framework\Api\ExtensibleDataInterface;

/**
 * Product custom option interface
 * @api
 * @since 100.0.2
 */
interface ProductOptionInterface extends ExtensibleDataInterface
{
    /**
     * Retrieve existing extension attributes object or create a new one.
     *
     * @return \Jsantos\ShoppingCart\Api\Data\ProductOptionExtensionInterface|null
     */
    public function getExtensionAttributes();

    /**
     * Set an extension attributes object.
     *
     * @param \Jsantos\ShoppingCart\Api\Data\ProductOptionExtensionInterface $extensionAttributes
     * @return $this
     */
    public function setExtensionAttributes(
        \Jsantos\ShoppingCart\Api\Data\ProductOptionExtensionInterface $extensionAttributes
    );
}
