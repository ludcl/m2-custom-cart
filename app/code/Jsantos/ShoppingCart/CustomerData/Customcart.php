<?php

declare(strict_types=1);

namespace Jsantos\ShoppingCart\CustomerData;

use Magento\Catalog\Block\ShortcutButtons;
use Magento\Catalog\Model\ResourceModel\Url;
use Magento\Checkout\CustomerData\ItemPoolInterface;
use Magento\Checkout\Helper\Data;
use Magento\Checkout\Model\Cart;
use Magento\Checkout\Model\Session;
use Magento\Customer\CustomerData\SectionSourceInterface;
use Magento\Framework\DataObject;
use Magento\Framework\View\LayoutInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;

class Customcart extends DataObject implements SectionSourceInterface
{
    /**
     * @var Quote|null
     */
    protected $quote = null;

    /**
     * @var int|float
     */
    protected $summeryCount;

    /**
     * @param Session $checkoutSession
     * @param Url $catalogUrl
     * @param Cart $checkoutCart
     * @param Data $checkoutHelper
     * @param ItemPoolInterface $itemPoolInterface
     * @param LayoutInterface $layout
     * @param array $data
     * @codeCoverageIgnore
     */
    public function __construct(
        protected Session $checkoutSession,
        protected Url $catalogUrl,
        protected Cart $checkoutCart,
        protected Data $checkoutHelper,
        protected ItemPoolInterface $itemPoolInterface,
        protected LayoutInterface $layout,
        array $data = []
    ) {
        parent::__construct($data);
    }

    /**
     * @inheritdoc
     */
    public function getSectionData(): array
    {
        $totals = $this->getQuote()->getTotals();
        $subtotalAmount = $totals['subtotal']->getValue();
        return [
            'summary_count' => $this->getSummaryCount(),
            'subtotalAmount' => $subtotalAmount,
            'subtotal' => isset($totals['subtotal'])
                ? $this->checkoutHelper->formatPrice($subtotalAmount)
                : 0,
            'possible_onepage_checkout' => $this->isPossibleOnepageCheckout(),
            'items' => $this->getRecentItems(),
            'extra_actions' => $this->layout->createBlock(ShortcutButtons::class)->toHtml(),
            'isGuestCheckoutAllowed' => $this->isGuestCheckoutAllowed(),
            'website_id' => $this->getQuote()->getStore()->getWebsiteId(),
            'storeId' => $this->getQuote()->getStore()->getStoreId()
        ];
    }

    /**
     * Get active quote
     *
     * @return Quote
     */
    protected function getQuote(): Quote
    {
        if (null === $this->quote) {
            $this->quote = $this->checkoutSession->getQuote();
        }
        return $this->quote;
    }

    /**
     * Get shopping cart items qty based on configuration (summary qty or items qty)
     *
     * @return int|float
     */
    protected function getSummaryCount(): float|int
    {
        if (!$this->summeryCount) {
            $this->summeryCount = $this->checkoutCart->getSummaryQty() ?: 0;
        }
        return $this->summeryCount;
    }

    /**
     * Check if one page checkout is available
     *
     * @return bool
     */
    protected function isPossibleOnepageCheckout(): bool
    {
        return $this->checkoutHelper->canOnepageCheckout() && !$this->getQuote()->getHasError();
    }

    /**
     * Get array of last added items
     *
     * @return Item[]
     */
    protected function getRecentItems(): array
    {
        $items = [];
        if (!$this->getSummaryCount()) {
            return $items;
        }

        foreach (array_reverse($this->getAllQuoteItems()) as $item) {
            /* @var $item Item */
            if (!$item->getProduct()->isVisibleInSiteVisibility()) {
                $product =  $item->getOptionByCode('product_type') !== null
                    ? $item->getOptionByCode('product_type')->getProduct()
                    : $item->getProduct();

                $products = $this->catalogUrl->getRewriteByProductStore([$product->getId() => $item->getStoreId()]);
                if (isset($products[$product->getId()])) {
                    $urlDataObject = new DataObject($products[$product->getId()]);
                    $item->getProduct()->setUrlDataObject($urlDataObject);
                }
            }
            $items[] = $this->itemPoolInterface->getItemData($item);
        }
        return $items;
    }

    /**
     * Return customer quote items
     *
     * @return Item[]
     */
    protected function getAllQuoteItems(): array
    {
        if ($this->getCustomQuote()) {
            return $this->getCustomQuote()->getAllVisibleItems();
        }
        return $this->getQuote()->getAllVisibleItems();
    }

    /**
     * Check if guest checkout is allowed
     *
     * @return bool
     */
    public function isGuestCheckoutAllowed(): bool
    {
        return $this->checkoutHelper->isAllowedGuestCheckout($this->checkoutSession->getQuote());
    }
}
