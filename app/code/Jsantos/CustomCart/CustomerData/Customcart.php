<?php

declare(strict_types=1);

namespace Jsantos\CustomCart\CustomerData;

use Jsantos\CustomCart\Api\Data\CustomcartInterface;
use Jsantos\CustomCart\Api\Data\CustomcartItemInterface;
use Jsantos\CustomCart\Model\Session;
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
     * @param LoggerInterface $logger
     * @param CustomerSession $customerSession
     * @param Session $customcartSession
     */
    public function __construct(
        protected CheckoutHelper $checkoutHelper,
        protected LoggerInterface $logger,
        protected CustomerSession $customerSession,
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
            $subtotalAmount = $this->getCustomcart()->getSubtotal();
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

        foreach (array_reverse($this->getCustomcart()->getItems()) as $item) {
            /* @var $item CustomcartItemInterface */
            if (!$item->getProduct()->isVisibleInSiteVisibility()) {
                //$product = $item->getProduct();

                // TODO: Add productItem to recentItems
                $items[] = [];
            }
        }
        return $items;
    }
}
