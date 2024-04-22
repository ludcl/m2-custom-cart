<?php

declare(strict_types=1);

namespace Jsantos\ShoppingCart\Model\Quote;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\Event\ManagerInterface;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Api\Data\ShippingInterface;
use Jsantos\ShoppingCart\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Model\Quote\Address\Total\Collector;
use Magento\Quote\Model\Quote\Address\Total\CollectorFactory;
use Magento\Quote\Model\Quote\Address\Total\CollectorInterface;
use Magento\Quote\Model\Quote\Address\TotalFactory;
use Magento\Quote\Model\Quote\QuantityCollector;
use Magento\Quote\Model\Quote\TotalsCollectorList;
use Magento\Quote\Model\QuoteValidator;
use Magento\Quote\Model\ShippingAssignmentFactory;
use Magento\Quote\Model\ShippingFactory;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Composite object for collecting total.
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class TotalsCollector
{
    /**
     * Total models collector
     *
     * @var Collector
     */
    protected $totalCollector;

    /**
     * @var CollectorFactory
     */
    protected $totalCollectorFactory;

    /**
     * Application Event Dispatcher
     *
     * @var ManagerInterface
     */
    protected $eventManager;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var TotalFactory
     */
    protected $totalFactory;

    /**
     * @var TotalsCollectorList
     */
    protected $collectorList;

    /**
     * @var QuoteValidator
     */
    protected $quoteValidator;

    /**
     * @var ShippingFactory
     */
    protected $shippingFactory;

    /**
     * @var ShippingAssignmentFactory
     */
    protected $shippingAssignmentFactory;

    /**
     * @var QuantityCollector
     */
    private $quantityCollector;

    /**
     * @param Collector $totalCollector
     * @param CollectorFactory $totalCollectorFactory
     * @param ManagerInterface $eventManager
     * @param StoreManagerInterface $storeManager
     * @param TotalFactory $totalFactory
     * @param TotalsCollectorList $collectorList
     * @param ShippingFactory $shippingFactory
     * @param ShippingAssignmentFactory $shippingAssignmentFactory
     * @param QuoteValidator $quoteValidator
     * @param QuantityCollector $quantityCollector
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        Collector $totalCollector,
        CollectorFactory $totalCollectorFactory,
        ManagerInterface $eventManager,
        StoreManagerInterface $storeManager,
        TotalFactory $totalFactory,
        TotalsCollectorList $collectorList,
        ShippingFactory $shippingFactory,
        ShippingAssignmentFactory $shippingAssignmentFactory,
        QuoteValidator $quoteValidator,
        QuantityCollector $quantityCollector = null
    ) {
        $this->totalCollector = $totalCollector;
        $this->totalCollectorFactory = $totalCollectorFactory;
        $this->eventManager = $eventManager;
        $this->storeManager = $storeManager;
        $this->totalFactory = $totalFactory;
        $this->collectorList = $collectorList;
        $this->shippingFactory = $shippingFactory;
        $this->shippingAssignmentFactory = $shippingAssignmentFactory;
        $this->quoteValidator = $quoteValidator;
        $this->quantityCollector = $quantityCollector
            ?: ObjectManager::getInstance()->get(QuantityCollector::class);
    }

    /**
     * Collect quote totals.
     *
     * @param Quote $quote
     * @return Total
     */
    public function collectQuoteTotals(Quote $quote)
    {
        if ($quote->isVirtual()) {
            return $this->collectAddressTotals($quote, $quote->getBillingAddress());
        }
        return $this->collectAddressTotals($quote, $quote->getShippingAddress());
    }

    /**
     * Collect address total.
     *
     * @param Quote $quote
     * @param Address $address
     * @return Total
     */
    public function collectAddressTotals(
        Quote $quote,
        Address $address
    ) {
        /** @var ShippingAssignmentInterface $shippingAssignment */
        $shippingAssignment = $this->shippingAssignmentFactory->create();

        /** @var ShippingInterface $shipping */
        $shipping = $this->shippingFactory->create();
        $shipping->setMethod($address->getShippingMethod());
        $shipping->setAddress($address);
        $shippingAssignment->setShipping($shipping);
        $shippingAssignment->setItems($address->getAllItems());

        /** @var Total $total */
        $total = $this->totalFactory->create(Total::class);
        $this->eventManager->dispatch(
            'sales_quote_address_collect_totals_before',
            [
                'quote' => $quote,
                'shipping_assignment' => $shippingAssignment,
                'total' => $total
            ]
        );

        foreach ($this->collectorList->getCollectors($quote->getStoreId()) as $collector) {
            /** @var CollectorInterface $collector */
            $collector->collect($quote, $shippingAssignment, $total);
        }

        $this->eventManager->dispatch(
            'sales_quote_address_collect_totals_after',
            [
                'quote' => $quote,
                'shipping_assignment' => $shippingAssignment,
                'total' => $total
            ]
        );
        $total->setBaseSubtotalTotalInclTax($total->getBaseSubtotalInclTax());
        $address->addData($total->getData());
        $address->setAppliedTaxes($total->getAppliedTaxes());
        return $total;
    }

    /**
     * Collect quote.
     *
     * @param Quote $quote
     * @return Total
     */
    public function collect(Quote $quote)
    {
        /** @var Total $total */
        $total = $this->totalFactory->create(Total::class);

        $this->eventManager->dispatch(
            'sales_quote_collect_totals_before',
            ['quote' => $quote]
        );

        $this->quantityCollector->collectItemsQtys($quote);

        $total->setSubtotal(0);
        $total->setBaseSubtotal(0);

        $total->setSubtotalWithDiscount(0);
        $total->setBaseSubtotalWithDiscount(0);

        $total->setGrandTotal(0);
        $total->setBaseGrandTotal(0);

        /** @var Address $address */
        foreach ($quote->getAllAddresses() as $address) {
            $addressTotal = $this->collectAddressTotals($quote, $address);

            $total->setShippingAmount($addressTotal->getShippingAmount());
            $total->setBaseShippingAmount($addressTotal->getBaseShippingAmount());
            $total->setShippingDescription($addressTotal->getShippingDescription());

            $total->setSubtotal((float)$total->getSubtotal() + $addressTotal->getSubtotal());
            $total->setBaseSubtotal((float)$total->getBaseSubtotal() + $addressTotal->getBaseSubtotal());

            $total->setSubtotalWithDiscount(
                (float)$total->getSubtotalWithDiscount() + $addressTotal->getSubtotalWithDiscount()
            );
            $total->setBaseSubtotalWithDiscount(
                (float)$total->getBaseSubtotalWithDiscount() + $addressTotal->getBaseSubtotalWithDiscount()
            );

            $total->setGrandTotal((float)$total->getGrandTotal() + $addressTotal->getGrandTotal());
            $total->setBaseGrandTotal((float)$total->getBaseGrandTotal() + $addressTotal->getBaseGrandTotal());
        }

        $this->quoteValidator->validateQuoteAmount($quote, $quote->getGrandTotal());
        $this->quoteValidator->validateQuoteAmount($quote, $quote->getBaseGrandTotal());
        $this->_validateCouponCode($quote);
        $this->eventManager->dispatch(
            'sales_quote_collect_totals_after',
            ['quote' => $quote]
        );
        return $total;
    }

    /**
     * Validate coupon code.
     *
     * @param Quote $quote
     * @return $this
     */
    protected function _validateCouponCode(Quote $quote)
    {
        $code = $quote->getData('coupon_code');
        if ($code !== null && strlen($code)) {
            $addressHasCoupon = false;
            $addresses = $quote->getAllAddresses();
            if (count($addresses) > 0) {
                foreach ($addresses as $address) {
                    if ($address->hasCouponCode()) {
                        $addressHasCoupon = true;
                    }
                }
                if (!$addressHasCoupon) {
                    $quote->setCouponCode('');
                }
            }
        }
        return $this;
    }

    /**
     * Collect items qty
     *
     * @param Quote $quote
     * @return $this
     * @deprecated
     * @see \Magento\Quote\Model\Quote\QuantityCollector
     */
    protected function _collectItemsQtys(Quote $quote)
    {
        $this->quantityCollector->collectItemsQtys($quote);

        return $this;
    }
}
