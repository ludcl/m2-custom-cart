<?php

declare(strict_types=1);

namespace Jsantos\CustomCart\CustomerData;

use Jsantos\CustomCart\Api\Data\CustomcartInterface;
use Jsantos\CustomCart\Api\Data\CustomcartItemInterface;
use Jsantos\CustomCart\Model\Session;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Helper\Image;
use Magento\Catalog\Model\Product;
use Magento\Customer\CustomerData\SectionSourceInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Checkout\Helper\Data as CheckoutHelper;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;

class Customcart implements SectionSourceInterface
{
    /**
     * @var CustomcartInterface|null
     */
    protected ?CustomcartInterface $customcart = null;

    /**
     * Constructor
     *
     * @param CheckoutHelper $checkoutHelper
     * @param CustomerSession $customerSession
     * @param Image $imageHelper
     * @param LoggerInterface $logger
     * @param Session $customcartSession
     */
    public function __construct(
        protected CheckoutHelper $checkoutHelper,
        protected CustomerSession $customerSession,
        protected Image $imageHelper,
        protected LoggerInterface $logger,
        protected Session $customcartSession
    ) {
    }

    /**
     * @inheritdoc
     */
    public function getSectionData(): array
    {
        $formattedZero = $this->checkoutHelper->formatPrice(0);

        try {
            $subtotalAmount = $this->getCustomcart()->getSubtotal() ?? 0;
            return [
                'summary_count' => $this->getSummaryCount(),
                'subtotalAmount' => $subtotalAmount,
                'subtotal' => isset($subtotalAmount)
                    ? $this->checkoutHelper->formatPrice($subtotalAmount)
                    : $formattedZero,
                'items' => $this->getRecentItems(),
                "subtotal_incl_tax" => $formattedZero,
                "subtotal_excl_tax" => $formattedZero,
            ];
        } catch (NoSuchEntityException|LocalizedException $e) {
            return [
                'summary_count' => 0,
                'subtotalAmount' => 0,
                'subtotal' => $formattedZero,
                'items' => [],
                "subtotal_incl_tax" => $formattedZero,
                "subtotal_excl_tax" => $formattedZero,
            ];
        }
    }

    /**
     * Get customcart from session
     *
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function getCustomcart(): CustomcartInterface
    {
        if (null === $this->customcart) {
            $this->customcart = $this->customcartSession->getCustomcart();
        }
        return $this->customcart;
    }

    /**
     * Get shopping cart items qty based on configuration (summary qty or items qty)
     *
     * @return int|float
     * @throws LocalizedException
     */
    protected function getSummaryCount(): float|int
    {
        try {
            return $this->getCustomcart()->getItemsQty() * 1;
        } catch (NoSuchEntityException|LocalizedException $e) {
            throw new LocalizedException(__($e->getMessage()));
        }
    }

    /**
     * Get array of last added items
     *
     * @return array
     * @throws LocalizedException
     */
    protected function getRecentItems(): array
    {
        $items = [];
        if (!$this->getSummaryCount()) {
            return $items;
        }

        /** @var $item CustomcartItemInterface */
        foreach ($this->getCustomcart()->getItems() as $item) {
            $product = $item->getProduct();
            $items[] = [
                'product_type' => $product->getTypeId(),
                'options' => $product->getOptions(),
                'qty' => $item->getQty(),
                'item_id' => $item->getItemId(),
                'is_visible_in_site_visibility' => $product->isVisibleInSiteVisibility(),
                'product_id' => $item->getProductId(),
                'product_name' => $item->getName(),
                'product_sku' => $item->getSku(),
                'product_url' => $product->getProductUrl(),
                'product_has_url' => $product->hasUrl(),
                'product_price' => $product->getPrice(),
                'product_price_value' => $this->checkoutHelper->formatPrice($product->getPrice()),
                'product_image' => [
                    'src' => $this->getProductImage($product),
                    'alt' => $item->getName(),
                    'width' => 150,
                    'height' => 150
                ],
                'message' => ""
            ];
        }

        return array_reverse($items);
    }

    /**
     * Get Product Image URL
     *
     * @param ProductInterface $product
     * @return string
     */
    public function getProductImage(ProductInterface $product): string
    {
        /** @var Product $product */
        return $this->imageHelper->init($product, 'product_base_image')->getUrl();
    }
}
