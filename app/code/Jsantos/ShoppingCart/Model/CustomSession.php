<?php

declare(strict_types=1);

namespace Jsantos\ShoppingCart\Model;

use LogicException;
use Magento\Checkout\Model\Session;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\State;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\SessionException;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Framework\Session\Config\ConfigInterface;
use Magento\Framework\Session\SaveHandlerInterface;
use Magento\Framework\Session\SessionManager;
use Magento\Framework\Session\SidResolverInterface;
use Magento\Framework\Session\StorageInterface;
use Magento\Framework\Session\ValidatorInterface;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Jsantos\ShoppingCart\Api\CartRepositoryInterface;
use Jsantos\ShoppingCart\Api\Data\CartInterface;
use Jsantos\ShoppingCart\Model\Quote;
use Jsantos\ShoppingCart\Model\QuoteFactory;
use Magento\Quote\Model\QuoteIdMask;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Represents the session data for the checkout process
 *
 * @api
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.CookieAndSessionMisuse)
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @since 100.0.2
 */
class CustomSession extends SessionManager
{
    public const CHECKOUT_STATE_BEGIN = 'begin';

    /**
     * Quote instance
     *
     * @var Quote
     */
    protected $_quote;

    /**
     * Customer Data Object
     *
     * @var CustomerInterface|null
     */
    protected $_customer;

    /**
     * Whether load only active quote
     *
     * @var bool
     */
    protected $_loadInactive = false;
    /**
     * Loaded order instance
     *
     * @var Order
     */
    protected $_order;
    /**
     * @var OrderFactory
     */
    protected $_orderFactory;
    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $_customerSession;
    /**
     * @var CartRepositoryInterface
     */
    protected $quoteRepository;
    /**
     * @var RemoteAddress
     */
    protected $_remoteAddress;
    /**
     * @var ManagerInterface
     */
    protected $_eventManager;
    /**
     * @var StoreManagerInterface
     */
    protected $_storeManager;
    /**
     * @var CustomerRepositoryInterface
     */
    protected $customerRepository;
    /**
     * @var QuoteIdMaskFactory
     */
    protected $quoteIdMaskFactory;
    /**
     * @var bool
     */
    protected $isQuoteMasked;
    /**
     * @var QuoteFactory
     */
    protected $quoteFactory;
    /**
     * A flag to track when the quote is being loaded and attached to the session object.
     *
     * Used in trigger_recollect infinite loop detection.
     *
     * @var bool
     */
    private $isLoading = false;
    /**
     * @var LoggerInterface|null
     */
    private $logger;

    /**
     * @param Http $request
     * @param SidResolverInterface $sidResolver
     * @param ConfigInterface $sessionConfig
     * @param SaveHandlerInterface $saveHandler
     * @param ValidatorInterface $validator
     * @param StorageInterface $storage
     * @param CookieManagerInterface $cookieManager
     * @param CookieMetadataFactory $cookieMetadataFactory
     * @param State $appState
     * @param OrderFactory $orderFactory
     * @param \Magento\Customer\Model\Session $customerSession
     * @param CartRepositoryInterface $quoteRepository
     * @param RemoteAddress $remoteAddress
     * @param ManagerInterface $eventManager
     * @param StoreManagerInterface $storeManager
     * @param CustomerRepositoryInterface $customerRepository
     * @param QuoteIdMaskFactory $quoteIdMaskFactory
     * @param QuoteFactory $quoteFactory
     * @param LoggerInterface|null $logger
     * @throws SessionException
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        Http $request,
        SidResolverInterface $sidResolver,
        ConfigInterface $sessionConfig,
        SaveHandlerInterface $saveHandler,
        ValidatorInterface $validator,
        StorageInterface $storage,
        CookieManagerInterface $cookieManager,
        CookieMetadataFactory $cookieMetadataFactory,
        State $appState,
        OrderFactory $orderFactory,
        \Magento\Customer\Model\Session $customerSession,
        CartRepositoryInterface $quoteRepository,
        RemoteAddress $remoteAddress,
        ManagerInterface $eventManager,
        StoreManagerInterface $storeManager,
        CustomerRepositoryInterface $customerRepository,
        QuoteIdMaskFactory $quoteIdMaskFactory,
        QuoteFactory $quoteFactory,
        LoggerInterface $logger = null
    ) {
        $this->_orderFactory = $orderFactory;
        $this->_customerSession = $customerSession;
        $this->quoteRepository = $quoteRepository;
        $this->_remoteAddress = $remoteAddress;
        $this->_eventManager = $eventManager;
        $this->_storeManager = $storeManager;
        $this->customerRepository = $customerRepository;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->quoteFactory = $quoteFactory;
        parent::__construct(
            $request,
            $sidResolver,
            $sessionConfig,
            $saveHandler,
            $validator,
            $storage,
            $cookieManager,
            $cookieMetadataFactory,
            $appState
        );
        $this->logger = $logger ?: ObjectManager::getInstance()
            ->get(LoggerInterface::class);
    }

    /**
     * @inheritDoc
     */
    public function _resetState(): void
    {
        parent::_resetState();
        $this->_quote = null;
        $this->_customer = null;
        $this->_loadInactive = false;
        $this->isLoading = false;
        $this->_order = null;
    }

    /**
     * Set customer data.
     *
     * @param CustomerInterface|null $customer
     * @return Session
     * @codeCoverageIgnore
     */
    public function setCustomerData($customer)
    {
        $this->_customer = $customer;
        return $this;
    }

    /**
     * Check whether current session has quote
     *
     * @return bool
     * @codeCoverageIgnore
     */
    public function hasQuote()
    {
        return isset($this->_quote);
    }

    /**
     * Set quote to be loaded even if inactive
     *
     * @param bool $load
     * @return $this
     * @codeCoverageIgnore
     */
    public function setLoadInactive($load = true)
    {
        $this->_loadInactive = $load;
        return $this;
    }

    /**
     * Load data for customer quote and merge with current quote
     *
     * @return $this
     */
    public function loadCustomerQuote()
    {
        if (!$this->_customerSession->getCustomerId()) {
            return $this;
        }

        // TODO: Check event logic for current calls
        // $this->_eventManager->dispatch('load_customer_quote_before', ['checkout_session' => $this]);

        try {
            $customerQuote = $this->quoteRepository->getForCustomer($this->_customerSession->getCustomerId());
        } catch (NoSuchEntityException $e) {
            $customerQuote = $this->quoteFactory->create();
        }
        $customerQuote->setStoreId($this->_storeManager->getStore()->getId());

        if ($customerQuote->getId() && $this->getQuoteId() != $customerQuote->getId()) {
            if ($this->getQuoteId()) {
                $quote = $this->getQuote();
                $quote->setCustomerIsGuest(0);
                $this->quoteRepository->save(
                    $customerQuote->merge($quote)->collectTotals()
                );
                $newQuote = $this->quoteRepository->get($customerQuote->getId());
                $this->quoteRepository->save(
                    $newQuote->collectTotals()
                );
                $customerQuote = $newQuote;
            }

            $this->setQuoteId($customerQuote->getId());

            if ($this->_quote) {
                $this->quoteRepository->delete($this->_quote);
            }
            $this->_quote = $customerQuote;
        } else {
            $this->getQuote()->getBillingAddress();
            $this->getQuote()->getShippingAddress();
            $this->getQuote()->setCustomer($this->_customerSession->getCustomerDataObject())
                ->setCustomerIsGuest(0)
                ->setTotalsCollectedFlag(false)
                ->collectTotals();
            $this->quoteRepository->save($this->getQuote());
        }
        return $this;
    }

    /**
     * Return the current quote's ID
     *
     * @return int
     * @codeCoverageIgnore
     */
    public function getQuoteId()
    {
        return $this->getData($this->_getQuoteIdKey());
    }

    /**
     * Return the quote's key
     *
     * @return string
     * @codeCoverageIgnore
     */
    protected function _getQuoteIdKey()
    {
        return 'custom_quote_id_' . $this->_storeManager->getStore()->getWebsiteId();
    }

    /**
     * Get checkout quote instance by current session
     *
     * @return Quote
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function getQuote()
    {
        $this->_eventManager->dispatch('custom_quote_process', ['checkout_session' => $this]);

        if ($this->_quote === null) {
            if ($this->isLoading) {
                throw new LogicException("Infinite loop detected, review the trace for the looping path");
            }
            $this->isLoading = true;
            $quote = $this->quoteFactory->create();
            if ($this->getQuoteId()) {
                try {
                    if ($this->_loadInactive) {
                        $quote = $this->quoteRepository->get($this->getQuoteId());
                    } else {
                        $quote = $this->quoteRepository->getActive($this->getQuoteId());
                    }

                    $customerId = $this->_customer
                        ? $this->_customer->getId()
                        : $this->_customerSession->getCustomerId();

                    if ($quote->getData('customer_id') && (int)$quote->getData('customer_id') !== (int)$customerId) {
                        $quote = $this->quoteFactory->create();
                        $this->setQuoteId(null);
                    }

                    /**
                     * If current currency code of quote is not equal current currency code of store,
                     * need recalculate totals of quote. It is possible if customer use currency switcher or
                     * store switcher.
                     */
                    if ($quote->getQuoteCurrencyCode() != $this->_storeManager->getStore()->getCurrentCurrencyCode()) {
                        $quote->setStore($this->_storeManager->getStore());
                        $this->quoteRepository->save($quote->collectTotals());
                        /*
                         * We mast to create new quote object, because collectTotals()
                         * can to create links with other objects.
                         */
                        $quote = $this->quoteRepository->get($this->getQuoteId());
                    }

                    if ($quote->getTotalsCollectedFlag() === false) {
                        $quote->collectTotals();
                    }
                } catch (NoSuchEntityException $e) {
                    $this->setQuoteId(null);
                }
            }

            if (!$this->getQuoteId()) {
                if ($this->_customerSession->isLoggedIn() || $this->_customer) {
                    $quoteByCustomer = $this->getQuoteByCustomer();
                    if ($quoteByCustomer !== null) {
                        $this->setQuoteId($quoteByCustomer->getId());
                        $quote = $quoteByCustomer;
                    }
                } else {
                    $quote->setIsCheckoutCart(true);
                    $quote->setCustomerIsGuest(1);
                    $this->_eventManager->dispatch('checkout_quote_init', ['quote' => $quote]);
                }
            }

            if ($this->_customer) {
                $quote->setCustomer($this->_customer);
            } elseif ($this->_customerSession->isLoggedIn()) {
                $quote->setCustomer($this->customerRepository->getById($this->_customerSession->getCustomerId()));
            }

            $quote->setStore($this->_storeManager->getStore());
            $this->_quote = $quote;
            $this->isLoading = false;
        }

        if (!$this->isQuoteMasked() && !$this->_customerSession->isLoggedIn() && $this->getQuoteId()) {
            $quoteId = $this->getQuoteId();
            /** @var $quoteIdMask QuoteIdMask */
            $quoteIdMask = $this->quoteIdMaskFactory->create()->load($quoteId, 'quote_id');
            if ($quoteIdMask->getMaskedId() === null) {
                $quoteIdMask->setQuoteId($quoteId)->save();
            }
            $this->setIsQuoteMasked(true);
        }

        $remoteAddress = $this->_remoteAddress->getRemoteAddress();
        if ($remoteAddress) {
            $this->_quote->setRemoteIp($remoteAddress);
            $xForwardIp = $this->request->getServer('HTTP_X_FORWARDED_FOR');
            $this->_quote->setXForwardedFor($xForwardIp);
        }

        return $this->_quote;
    }

    /**
     * Set the current session's quote id
     *
     * @param int $quoteId
     * @return void
     * @codeCoverageIgnore
     */
    public function setQuoteId($quoteId)
    {
        $this->storage->setData($this->_getQuoteIdKey(), $quoteId);
    }

    /**
     * Returns quote for customer if there is any
     */
    private function getQuoteByCustomer(): ?CartInterface
    {
        $customerId = $this->_customer
            ? $this->_customer->getId()
            : $this->_customerSession->getCustomerId();

        try {
            $quote = $this->quoteRepository->getActiveForCustomer($customerId);
        } catch (NoSuchEntityException $e) {
            $quote = null;
        }

        return $quote;
    }

    /**
     * Return if the quote has a masked quote id
     *
     * @return bool|null
     * @codeCoverageIgnore
     */
    protected function isQuoteMasked()
    {
        return $this->isQuoteMasked;
    }

    /**
     * Flag whether or not the quote uses a masked quote id
     *
     * @param bool $isQuoteMasked
     * @return void
     * @codeCoverageIgnore
     */
    protected function setIsQuoteMasked($isQuoteMasked)
    {
        $this->isQuoteMasked = $isQuoteMasked;
    }

    /**
     * Associate data to a specified step of the checkout process
     *
     * @param string $step
     * @param array|string $data
     * @param bool|string|null $value
     * @return $this
     */
    public function setStepData($step, $data, $value = null)
    {
        $steps = $this->getSteps();
        if ($value === null) {
            if (is_array($data)) {
                $steps[$step] = $data;
            }
        } else {
            if (!isset($steps[$step])) {
                $steps[$step] = [];
            }
            if (is_string($data)) {
                $steps[$step][$data] = $value;
            }
        }
        $this->setSteps($steps);

        return $this;
    }

    /**
     * Return the data associated to a specified step
     *
     * @param string|null $step
     * @param string|null $data
     * @return array|string|bool
     */
    public function getStepData($step = null, $data = null)
    {
        $steps = $this->getSteps();
        if ($step === null) {
            return $steps;
        }
        if (!isset($steps[$step])) {
            return false;
        }
        if ($data === null) {
            return $steps[$step];
        }
        if (!is_string($data) || !isset($steps[$step][$data])) {
            return false;
        }
        return $steps[$step][$data];
    }

    /**
     * Destroy/end a session and unset all data associated with it
     *
     * @return $this
     */
    public function clearQuote()
    {
        $this->_eventManager->dispatch('checkout_quote_destroy', ['quote' => $this->getQuote()]);
        $this->_quote = null;
        $this->setQuoteId(null);
        $this->setLastSuccessQuoteId(null);
        return $this;
    }

    /**
     * Unset all session data and quote
     *
     * @return $this
     */
    public function clearStorage()
    {
        parent::clearStorage();
        $this->_quote = null;
        return $this;
    }

    /**
     * Clear misc checkout parameters
     *
     * @return void
     */
    public function clearHelperData()
    {
        $this->setRedirectUrl(null)->setLastOrderId(null)->setLastRealOrderId(null)->setAdditionalMessages(null);
    }

    /**
     * Revert the state of the checkout to the beginning
     *
     * @return $this
     * @codeCoverageIgnore
     */
    public function resetCheckout()
    {
        $this->setCheckoutState(self::CHECKOUT_STATE_BEGIN);
        return $this;
    }

    /**
     * Restore last active quote
     *
     * @return bool True if quote restored successfully, false otherwise
     */
    public function restoreQuote()
    {
        /** @var Order $order */
        $order = $this->getLastRealOrder();
        if ($order->getId()) {
            try {
                $quote = $this->quoteRepository->get($order->getQuoteId());
                $quote->setIsActive(1)->setReservedOrderId(null);
                $this->quoteRepository->save($quote);
                $this->replaceQuote($quote)->unsLastRealOrderId();
                $this->_eventManager->dispatch('restore_quote', ['order' => $order, 'quote' => $quote]);
                return true;
            } catch (NoSuchEntityException $e) {
                $this->logger->critical($e);
            }
        }

        return false;
    }

    /**
     * Get order instance based on last order ID
     *
     * @return Order
     */
    public function getLastRealOrder()
    {
        $orderId = $this->getLastRealOrderId();
        if ($this->_order !== null && $orderId == $this->_order->getIncrementId()) {
            return $this->_order;
        }
        $this->_order = $this->_orderFactory->create();
        if ($orderId) {
            $this->_order->loadByIncrementId($orderId);
        }
        return $this->_order;
    }

    /**
     * Replace the quote in the session with a specified object
     *
     * @param Quote $quote
     * @return $this
     */
    public function replaceQuote($quote)
    {
        $this->_quote = $quote;
        $this->setQuoteId($quote->getId());
        return $this;
    }
}
