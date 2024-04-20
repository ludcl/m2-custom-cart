<?php
/**
 * This is not the recommended approach because this override could conflict with
 * other modules overriding the same class.
 * The best idea is to inject the custom template as a config option in etc/widget.xml.
 * However, since this module is intended for future use in a clean Magento installation
 * (like Luma with sample data), using a preference here ensures the custom template
 * works with existing instances of the products_list widget.
 */

declare(strict_types=1);

namespace Jsantos\ShoppingCart\Block\Product;

class ProductsList extends \Magento\CatalogWidget\Block\Product\ProductsList
{
}
