<?php

declare(strict_types=1);

namespace Jsantos\ShoppingCart\Model;

use Jsantos\ShoppingCart\Api\Data\CartInterface;
use Jsantos\ShoppingCart\Model\Quote\Item;
use Jsantos\ShoppingCart\Model\Quote\ItemFactory;
use Jsantos\ShoppingCart\Model\ResourceModel\Quote\Item\CollectionFactory as QuoteItemCollectionFactory;
use Magento\Backend\App\Area\FrontNameResolver;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Helper\Product as ProductHelper;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status as ProductStatus;
use Magento\Catalog\Model\Product\Type\AbstractType;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\AddressInterfaceFactory;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Customer\Api\Data\GroupInterface;
use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Customer\Model\Customer;
use Magento\Customer\Model\CustomerFactory;
use Magento\Directory\Model\AllowedCountries;
use Magento\Directory\Model\Currency;
use Magento\Eav\Model\Entity\Collection\AbstractCollection;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\DataObjectHelper;
use Magento\Framework\Api\ExtensibleDataInterface;
use Magento\Framework\Api\ExtensibleDataObjectConverter;
use Magento\Framework\Api\ExtensionAttribute\JoinProcessorInterface;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\DataObject;
use Magento\Framework\DataObject\Copy;
use Magento\Framework\DataObject\Factory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\AbstractMessage;
use Magento\Framework\Message\Factory as MessageFactory;
use Magento\Framework\Message\MessageInterface;
use Magento\Framework\Model\AbstractExtensibleModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Phrase;
use Magento\Framework\Registry;
use Magento\Quote\Api\Data\AddressInterface;
use Jsantos\ShoppingCart\Api\Data\CartExtensionInterface;
use Magento\Quote\Api\Data\CurrencyInterface;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Quote\Model\Cart\CurrencyFactory;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\Address\Total as AddressTotal;
use Magento\Quote\Model\Quote\AddressFactory;
use Magento\Quote\Model\Quote\Item\Processor;
use Magento\Quote\Model\Quote\Payment;
use Magento\Quote\Model\Quote\PaymentFactory;
use Jsantos\ShoppingCart\Model\Quote\TotalsCollector;
use Jsantos\ShoppingCart\Model\Quote\TotalsReader;
use Magento\Quote\Model\QuoteValidator;
use Magento\Quote\Model\ResourceModel\Quote\Payment\CollectionFactory;
use Magento\Quote\Model\ShippingAssignmentFactory;
use Magento\Quote\Model\ShippingFactory;
use Magento\Sales\Model\OrderIncrementIdChecker;
use Magento\Sales\Model\Status\ListFactory;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use function count;

/**
 * Quote model
 *
 * Supported events:
 *  sales_quote_load_after
 *  sales_quote_save_before
 *  sales_quote_save_after
 *  sales_quote_delete_before
 *  sales_quote_delete_after
 *
 * @api
 * @SuppressWarnings(PHPMD.ExcessivePublicCount)
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @since 100.0.2
 */
class Quote extends AbstractExtensibleModel implements CartInterface
{
    /**
     * Checkout login method key
     */
    public const CHECKOUT_METHOD_LOGIN_IN = 'login_in';

    /**
     * @var string
     */
    protected $_eventPrefix = 'sales_quote';

    /**
     * @var string
     */
    protected $_eventObject = 'quote';

    /**
     * Quote customer model object
     *
     * @var Customer
     */
    protected $_customer;

    /**
     * Quote addresses collection
     *
     * @var AbstractCollection
     */
    protected $_addresses;

    /**
     * Quote items collection
     *
     * @var AbstractCollection
     */
    protected $_items;

    /**
     * Quote payments
     *
     * @var AbstractCollection
     */
    protected $_payments;

    /**
     * @var Payment
     */
    protected $_currentPayment;

    /**
     * Different groups of error infos
     *
     * @var array
     */
    protected $_errorInfoGroups = [];

    /**
     * Whether quote should not be saved
     *
     * @var bool
     */
    protected $_preventSaving = false;

    /**
     * Product of the catalog
     *
     * @var ProductHelper
     */
    protected $_catalogProduct;

    /**
     * To perform validation on the quote
     *
     * @var QuoteValidator
     */
    protected $quoteValidator;

    /**
     * Core store config
     *
     * @var ScopeConfigInterface
     */
    protected $_scopeConfig;

    /**
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var ScopeConfigInterface
     */
    protected $_config;

    /**
     * @var AddressFactory
     */
    protected $_quoteAddressFactory;

    /**
     * @var CustomerFactory
     */
    protected $_customerFactory;

    /**
     * Repository for group to perform CRUD operations
     *
     * @var GroupRepositoryInterface
     */
    protected $groupRepository;

    /**
     * @var QuoteItemCollectionFactory
     */
    protected $_quoteItemCollectionFactory;

    /**
     * @var ItemFactory
     */
    protected $_quoteItemFactory;

    /**
     * @var MessageFactory
     */
    protected $messageFactory;

    /**
     * @var ListFactory
     */
    protected $_statusListFactory;

    /**
     * @var ProductRepositoryInterface
     */
    protected $productRepository;

    /**
     * @var PaymentFactory
     */
    protected $_quotePaymentFactory;

    /**
     * @var CollectionFactory
     */
    protected $_quotePaymentCollectionFactory;

    /**
     * @var Copy
     */
    protected $_objectCopyService;

    /**
     * Repository for customer address to perform crud operations
     *
     * @var AddressRepositoryInterface
     */
    protected $addressRepository;

    /**
     * It is used for building search criteria
     *
     * @var SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;

    /**
     * This is used for holding  builder object for filter service
     *
     * @var FilterBuilder
     */
    protected $filterBuilder;

    /**
     * @var StockRegistryInterface
     */
    protected $stockRegistry;

    /**
     * @var Processor
     */
    protected $itemProcessor;

    /**
     * @var Factory
     */
    protected $objectFactory;

    /**
     * @var ExtensibleDataObjectConverter
     */
    protected $extensibleDataObjectConverter;

    /**
     * @var AddressInterfaceFactory
     */
    protected $addressDataFactory;

    /**
     * @var CustomerInterfaceFactory
     */
    protected $customerDataFactory;

    /**
     * @var CustomerRepositoryInterface
     */
    protected $customerRepository;

    /**
     * @var Cart\CurrencyFactory
     */
    protected $currencyFactory;

    /**
     * @var DataObjectHelper
     */
    protected $dataObjectHelper;

    /**
     * @var JoinProcessorInterface
     */
    protected $extensionAttributesJoinProcessor;

    /**
     * @var TotalsCollector
     */
    protected $totalsCollector;

    /**
     * @var TotalsReader
     */
    protected $totalsReader;

    /**
     * @var ShippingFactory
     */
    protected $shippingFactory;

    /**
     * @var ShippingAssignmentFactory
     */
    protected $shippingAssignmentFactory;

    /**
     * Quote shipping addresses items cache
     *
     * @var array
     */
    protected $shippingAddressesItems;

    /**
     * @var OrderIncrementIdChecker
     */
    private $orderIncrementIdChecker;

    /**
     * @var AllowedCountries
     */
    private $allowedCountriesReader;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param ExtensionAttributesFactory $extensionFactory
     * @param AttributeValueFactory $customAttributeFactory
     * @param QuoteValidator $quoteValidator
     * @param ProductHelper $catalogProduct
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $config
     * @param AddressFactory $quoteAddressFactory
     * @param CustomerFactory $customerFactory
     * @param GroupRepositoryInterface $groupRepository
     * @param QuoteItemCollectionFactory $quoteItemCollectionFactory
     * @param ItemFactory $quoteItemFactory
     * @param MessageFactory $messageFactory
     * @param ListFactory $statusListFactory
     * @param ProductRepositoryInterface $productRepository
     * @param PaymentFactory $quotePaymentFactory
     * @param CollectionFactory $quotePaymentCollectionFactory
     * @param Copy $objectCopyService
     * @param StockRegistryInterface $stockRegistry
     * @param Processor $itemProcessor
     * @param Factory $objectFactory
     * @param AddressRepositoryInterface $addressRepository
     * @param SearchCriteriaBuilder $criteriaBuilder
     * @param FilterBuilder $filterBuilder
     * @param AddressInterfaceFactory $addressDataFactory
     * @param CustomerInterfaceFactory $customerDataFactory
     * @param CustomerRepositoryInterface $customerRepository
     * @param DataObjectHelper $dataObjectHelper
     * @param ExtensibleDataObjectConverter $extensibleDataObjectConverter
     * @param CurrencyFactory $currencyFactory
     * @param JoinProcessorInterface $extensionAttributesJoinProcessor
     * @param TotalsCollector $totalsCollector
     * @param TotalsReader $totalsReader
     * @param ShippingFactory $shippingFactory
     * @param ShippingAssignmentFactory $shippingAssignmentFactory
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     * @param OrderIncrementIdChecker|null $orderIncrementIdChecker
     * @param AllowedCountries|null $allowedCountriesReader
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ExtensionAttributesFactory $extensionFactory,
        AttributeValueFactory $customAttributeFactory,
        QuoteValidator $quoteValidator,
        ProductHelper $catalogProduct,
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $config,
        AddressFactory $quoteAddressFactory,
        CustomerFactory $customerFactory,
        GroupRepositoryInterface $groupRepository,
        QuoteItemCollectionFactory $quoteItemCollectionFactory,
        ItemFactory $quoteItemFactory,
        MessageFactory $messageFactory,
        ListFactory $statusListFactory,
        ProductRepositoryInterface $productRepository,
        PaymentFactory $quotePaymentFactory,
        CollectionFactory $quotePaymentCollectionFactory,
        Copy $objectCopyService,
        StockRegistryInterface $stockRegistry,
        Processor $itemProcessor,
        Factory $objectFactory,
        AddressRepositoryInterface $addressRepository,
        SearchCriteriaBuilder $criteriaBuilder,
        FilterBuilder $filterBuilder,
        AddressInterfaceFactory $addressDataFactory,
        CustomerInterfaceFactory $customerDataFactory,
        CustomerRepositoryInterface $customerRepository,
        DataObjectHelper $dataObjectHelper,
        ExtensibleDataObjectConverter $extensibleDataObjectConverter,
        CurrencyFactory $currencyFactory,
        JoinProcessorInterface $extensionAttributesJoinProcessor,
        TotalsCollector $totalsCollector,
        TotalsReader $totalsReader,
        ShippingFactory $shippingFactory,
        ShippingAssignmentFactory $shippingAssignmentFactory,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = [],
        OrderIncrementIdChecker $orderIncrementIdChecker = null,
        AllowedCountries $allowedCountriesReader = null
    ) {
        $this->quoteValidator = $quoteValidator;
        $this->_catalogProduct = $catalogProduct;
        $this->_scopeConfig = $scopeConfig;
        $this->_storeManager = $storeManager;
        $this->_config = $config;
        $this->_quoteAddressFactory = $quoteAddressFactory;
        $this->_customerFactory = $customerFactory;
        $this->groupRepository = $groupRepository;
        $this->_quoteItemCollectionFactory = $quoteItemCollectionFactory;
        $this->_quoteItemFactory = $quoteItemFactory;
        $this->messageFactory = $messageFactory;
        $this->_statusListFactory = $statusListFactory;
        $this->productRepository = $productRepository;
        $this->_quotePaymentFactory = $quotePaymentFactory;
        $this->_quotePaymentCollectionFactory = $quotePaymentCollectionFactory;
        $this->_objectCopyService = $objectCopyService;
        $this->addressRepository = $addressRepository;
        $this->searchCriteriaBuilder = $criteriaBuilder;
        $this->filterBuilder = $filterBuilder;
        $this->stockRegistry = $stockRegistry;
        $this->itemProcessor = $itemProcessor;
        $this->objectFactory = $objectFactory;
        $this->addressDataFactory = $addressDataFactory;
        $this->customerDataFactory = $customerDataFactory;
        $this->customerRepository = $customerRepository;
        $this->dataObjectHelper = $dataObjectHelper;
        $this->extensibleDataObjectConverter = $extensibleDataObjectConverter;
        $this->currencyFactory = $currencyFactory;
        $this->extensionAttributesJoinProcessor = $extensionAttributesJoinProcessor;
        $this->totalsCollector = $totalsCollector;
        $this->totalsReader = $totalsReader;
        $this->shippingFactory = $shippingFactory;
        $this->shippingAssignmentFactory = $shippingAssignmentFactory;
        $this->orderIncrementIdChecker = $orderIncrementIdChecker ?: ObjectManager::getInstance()
            ->get(OrderIncrementIdChecker::class);
        $this->allowedCountriesReader = $allowedCountriesReader
            ?: ObjectManager::getInstance()->get(AllowedCountries::class);
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $resource,
            $resourceCollection,
            $data
        );
    }

    /**
     * Returns information about quote currency, such as code, exchange rate, and so on.
     *
     * @return CurrencyInterface|null Quote currency information. Otherwise, null.
     * @codeCoverageIgnoreStart
     */
    public function getCurrency()
    {
        $currency = $this->getData(self::KEY_CURRENCY);
        if (!$currency) {
            $currency = $this->currencyFactory->create()
                ->setGlobalCurrencyCode($this->getGlobalCurrencyCode())
                ->setBaseCurrencyCode($this->getBaseCurrencyCode())
                ->setStoreCurrencyCode($this->getStoreCurrencyCode())
                ->setQuoteCurrencyCode($this->getQuoteCurrencyCode())
                ->setStoreToBaseRate($this->getStoreToBaseRate())
                ->setStoreToQuoteRate($this->getStoreToQuoteRate())
                ->setBaseToGlobalRate($this->getBaseToGlobalRate())
                ->setBaseToQuoteRate($this->getBaseToQuoteRate());
            $this->setData(self::KEY_CURRENCY, $currency);
        }
        return $currency;
    }

    /**
     * @inheritdoc
     */
    public function setCurrency(CurrencyInterface $currency = null)
    {
        return $this->setData(self::KEY_CURRENCY, $currency);
    }

    /**
     * @inheritdoc
     */
    public function getCreatedAt()
    {
        return $this->_getData(self::KEY_CREATED_AT);
    }

    /**
     * @inheritdoc
     */
    public function setCreatedAt($createdAt)
    {
        return $this->setData(self::KEY_CREATED_AT, $createdAt);
    }

    /**
     * @inheritdoc
     */
    public function getUpdatedAt()
    {
        return $this->_getData(self::KEY_UPDATED_AT);
    }

    /**
     * @inheritdoc
     */
    public function getConvertedAt()
    {
        return $this->_getData(self::KEY_CONVERTED_AT);
    }

    /**
     * @inheritdoc
     */
    public function setConvertedAt($convertedAt)
    {
        return $this->setData(self::KEY_CONVERTED_AT, $convertedAt);
    }

    /**
     * @inheritdoc
     */
    public function getIsActive()
    {
        return $this->_getData(self::KEY_IS_ACTIVE);
    }

    /**
     * @inheritdoc
     */
    public function setIsActive($isActive)
    {
        return $this->setData(self::KEY_IS_ACTIVE, $isActive);
    }

    /**
     * @inheritdoc
     */
    public function getItemsCount()
    {
        return $this->_getData(self::KEY_ITEMS_COUNT);
    }

    /**
     * @inheritdoc
     */
    public function setItemsCount($itemsCount)
    {
        return $this->setData(self::KEY_ITEMS_COUNT, $itemsCount);
    }

    /**
     * @inheritdoc
     */
    public function getItemsQty()
    {
        return $this->_getData(self::KEY_ITEMS_QTY);
    }

    /**
     * @inheritdoc
     */
    public function setItemsQty($itemsQty)
    {
        return $this->setData(self::KEY_ITEMS_QTY, $itemsQty);
    }

    /**
     * @inheritdoc
     */
    public function getOrigOrderId()
    {
        return $this->_getData(self::KEY_ORIG_ORDER_ID);
    }

    /**
     * @inheritdoc
     */
    public function setOrigOrderId($origOrderId)
    {
        return $this->setData(self::KEY_ORIG_ORDER_ID, $origOrderId);
    }

    /**
     * @inheritdoc
     */
    public function getCustomerIsGuest()
    {
        return $this->_getData(self::KEY_CUSTOMER_IS_GUEST);
    }

    /**
     * @inheritdoc
     */
    public function setCustomerIsGuest($customerIsGuest)
    {
        return $this->setData(self::KEY_CUSTOMER_IS_GUEST, $customerIsGuest);
    }

    /**
     * @inheritdoc
     */
    public function getCustomerNote()
    {
        return $this->_getData(self::KEY_CUSTOMER_NOTE);
    }

    /**
     * @inheritdoc
     */
    public function setCustomerNote($customerNote)
    {
        return $this->setData(self::KEY_CUSTOMER_NOTE, $customerNote);
    }

    /**
     * @inheritdoc
     */
    public function getCustomerNoteNotify()
    {
        return $this->_getData(self::KEY_CUSTOMER_NOTE_NOTIFY);
    }

    /**
     * @inheritdoc
     */
    public function setCustomerNoteNotify($customerNoteNotify)
    {
        return $this->setData(self::KEY_CUSTOMER_NOTE_NOTIFY, $customerNoteNotify);
    }

    /**
     * Declare quote store model
     *
     * @param Store $store
     * @return $this
     */
    public function setStore(Store $store)
    {
        $this->setStoreId($store->getId());
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setStoreId($storeId)
    {
        $this->setData(self::KEY_STORE_ID, (int)$storeId);
        return $this;
    }

    /**
     * Get all available store ids for quote
     *
     * @return array
     */
    public function getSharedStoreIds()
    {
        $ids = $this->_getData('shared_store_ids');
        if ($ids === null || !is_array($ids)) {
            $website = $this->getWebsite();
            if ($website) {
                return $website->getStoreIds();
            }
            return $this->getStore()->getWebsite()->getStoreIds();
        }
        return $ids;
    }

    /**
     * Get quote store model object
     *
     * @return  Store
     */
    public function getStore()
    {
        return $this->_storeManager->getStore($this->getStoreId());
    }

    /**
     * @inheritdoc
     */
    public function getStoreId()
    {
        if (!$this->hasStoreId()) {
            return $this->_storeManager->getStore()->getId();
        }
        return (int)$this->_getData(self::KEY_STORE_ID);
    }

    /**
     * Prepare data before save
     *
     * @return $this
     */
    public function beforeSave()
    {
        /**
         * Currency logic
         *
         * global - currency which is set for default in backend
         * base - currency which is set for current website. all attributes that
         *      have 'base_' prefix saved in this currency
         * quote/order - currency which was selected by customer or configured by
         *      admin for current store. currency in which customer sees
         *      price thought all checkout.
         *
         * Rates:
         *      base_to_global & base_to_quote/base_to_order
         */
        $globalCurrencyCode = $this->_config->getValue(
            Currency::XML_PATH_CURRENCY_BASE,
            'default'
        );
        $baseCurrency = $this->getStore()->getBaseCurrency();

        if ($this->hasForcedCurrency()) {
            $quoteCurrency = $this->getForcedCurrency();
        } else {
            $quoteCurrency = $this->getStore()->getCurrentCurrency();
        }

        $this->setGlobalCurrencyCode($globalCurrencyCode);
        $this->setBaseCurrencyCode($baseCurrency->getCode());
        $this->setStoreCurrencyCode($baseCurrency->getCode());
        $this->setQuoteCurrencyCode($quoteCurrency->getCode());

        $this->setBaseToGlobalRate($baseCurrency->getRate($globalCurrencyCode));
        $this->setBaseToQuoteRate($baseCurrency->getRate($quoteCurrency));

        if (!$this->hasChangedFlag() || $this->getChangedFlag() == true) {
            $this->setIsChanged(1);
        } else {
            $this->setIsChanged(0);
        }

        if ($this->_customer) {
            $this->setCustomerId($this->_customer->getId());
        }

        //mark quote if it has virtual products only
        $this->setIsVirtual($this->getIsVirtual());

        if ($this->hasDataChanges()) {
            $this->setUpdatedAt(null);
        }

        parent::beforeSave();

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setIsVirtual($isVirtual)
    {
        return $this->setData(self::KEY_IS_VIRTUAL, $isVirtual);
    }

    //@codeCoverageIgnoreEnd

    /**
     * Check quote for virtual product only
     *
     * @return bool
     * @SuppressWarnings(PHPMD.BooleanGetMethodName)
     */
    public function getIsVirtual()
    {
        return (int)$this->isVirtual();
    }

    /**
     * Check quote for virtual product only
     *
     * @return bool
     */
    public function isVirtual()
    {
        $isVirtual = true;
        $countItems = 0;
        foreach ($this->getItemsCollection() as $_item) {
            /* @var $_item Item */
            if ($_item->isDeleted() || $_item->getParentItemId()) {
                continue;
            }
            $countItems++;
            if (!$_item->getProduct()->getIsVirtual()) {
                $isVirtual = false;
                break;
            }
        }
        return $countItems == 0 ? false : $isVirtual;
    }

    /**
     * Retrieve quote items collection
     *
     * @param bool $useCache
     * @return AbstractCollection
     */
    public function getItemsCollection($useCache = true)
    {
        if ($this->hasItemsCollection() && $useCache) {
            return $this->getData('items_collection');
        }
        if (null === $this->_items || !$useCache) {
            $this->_items = $this->_quoteItemCollectionFactory->create();
            $this->extensionAttributesJoinProcessor->process($this->_items);
            $this->_items->setQuote($this);
        }
        return $this->_items;
    }

    /**
     * @inheritdoc
     */
    public function setUpdatedAt($updatedAt)
    {
        return $this->setData(self::KEY_UPDATED_AT, $updatedAt);
    }

    /**
     * Loading quote data by customer
     *
     * @param Customer|int $customer
     * @deprecated 101.0.0 Deprecated to handle external usages of customer methods
     * @see https://jira.corp.magento.com/browse/MAGETWO-19935
     * @return $this
     */
    public function loadByCustomer($customer)
    {
        /* @TODO: remove this if after external usages of loadByCustomerId are refactored in MAGETWO-19935 */
        if ($customer instanceof Customer || $customer instanceof CustomerInterface) {
            $customerId = $customer->getId();
        } else {
            $customerId = (int)$customer;
        }
        $this->_getResource()->loadByCustomerId($this, $customerId);
        $this->_afterLoad();
        return $this;
    }

    /**
     * Trigger collect totals after loading, if required
     *
     * @return $this
     */
    protected function _afterLoad()
    {
        // collect totals and save me, if required
        if (1 == $this->getTriggerRecollect()) {
            $this->collectTotals()
                ->setTriggerRecollect(0)
                ->save();
        }
        return parent::_afterLoad();
    }

    /**
     * Collect totals
     *
     * @return $this
     */
    public function collectTotals()
    {
        if ($this->getTotalsCollectedFlag()) {
            return $this;
        }

        $total = $this->totalsCollector->collect($this);
        $this->addData($total->getData());

        $this->setTotalsCollectedFlag(true);
        return $this;
    }

    /**
     * Loading only active quote
     *
     * @param int $quoteId
     * @return $this
     */
    public function loadActive($quoteId)
    {
        $this->_getResource()->loadActive($this, $quoteId);
        $this->_afterLoad();
        return $this;
    }

    /**
     * Loading quote by identifier
     *
     * @param int $quoteId
     * @return $this
     */
    public function loadByIdWithoutStore($quoteId)
    {
        $this->_getResource()->loadByIdWithoutStore($this, $quoteId);
        $this->_afterLoad();
        return $this;
    }

    /**
     * Assign customer model object data to quote
     *
     * @param CustomerInterface $customer
     * @return $this
     */
    public function assignCustomer(CustomerInterface $customer)
    {
        return $this->assignCustomerWithAddressChange($customer);
    }

    /**
     * Assign customer model to quote with billing and shipping address change
     *
     * @param CustomerInterface $customer
     * @param Address $billingAddress Quote billing address
     * @param Address $shippingAddress Quote shipping address
     * @return $this
     */
    public function assignCustomerWithAddressChange(
        CustomerInterface $customer,
        Address $billingAddress = null,
        Address $shippingAddress = null
    ) {
        if ($customer->getId()) {
            $this->setCustomer($customer);

            if (null !== $billingAddress) {
                $this->setBillingAddress($billingAddress);
            } else {
                try {
                    $defaultBillingAddress = $this->addressRepository->getById($customer->getDefaultBilling());
                    // phpcs:ignore Magento2.CodeAnalysis.EmptyBlock
                } catch (NoSuchEntityException $e) {
                }
                if (isset($defaultBillingAddress)) {
                    /** @var Address $billingAddress */
                    $billingAddress = $this->_quoteAddressFactory->create();
                    $billingAddress->importCustomerAddressData($defaultBillingAddress);
                    $this->assignAddress($billingAddress);
                }
            }

            if (null === $shippingAddress) {
                try {
                    $defaultShippingAddress = $this->addressRepository->getById($customer->getDefaultShipping());
                    // phpcs:ignore Magento2.CodeAnalysis.EmptyBlock
                } catch (NoSuchEntityException $e) {
                }
                if (isset($defaultShippingAddress)) {
                    /** @var Address $shippingAddress */
                    $shippingAddress = $this->_quoteAddressFactory->create();
                    $shippingAddress->importCustomerAddressData($defaultShippingAddress);
                } else {
                    $shippingAddress = $this->_quoteAddressFactory->create();
                }
            }

            $this->assignAddress($shippingAddress, false);
        }

        return $this;
    }

    /**
     * Set billing address.
     *
     * @param AddressInterface $address
     * @return $this
     */
    public function setBillingAddress(AddressInterface $address = null)
    {
        $old = $this->getAddressesCollection()->getItemById($address->getId())
            ?? $this->getBillingAddress();
        if ($old !== null) {
            $old->addData($address->getData());
        } else {
            $this->addAddress($address->setAddressType(Address::TYPE_BILLING));
        }

        return $this;
    }

    /**
     * Retrieve item model object by item identifier
     *
     * @param   int $itemId
     * @return  Item|false
     */
    public function getItemById($itemId)
    {
        foreach ($this->getItemsCollection() as $item) {
            if ($item->getId() == $itemId) {
                return $item;
            }
        }

        return false;
    }

    /**
     * Retrieve quote address collection
     *
     * @return AbstractCollection
     */
    public function getAddressesCollection(): AbstractCollection
    {
        return $this->_addresses;
    }

    /**
     * TODO: Remove methods related to Address from here and the Interface
     *
     * @return Address|null
     */
    public function getBillingAddress(): Address|null
    {
        return null;
    }

    /**
     * Add address.
     *
     * @param AddressInterface $address
     * @return $this
     */
    public function addAddress(AddressInterface $address)
    {
        $address->setQuote($this);
        if (!$address->getId()) {
            $this->getAddressesCollection()->addItem($address);
        }
        return $this;
    }

    /**
     * Adding new item to quote
     *
     * @param Item $item
     * @return $this
     * @throws LocalizedException
     */
    public function addItem(Item $item)
    {
        $item->setQuote($this);
        if (!$item->getId()) {
            $this->getItemsCollection()->addItem($item);
            $this->_eventManager->dispatch('sales_quote_add_item', ['quote_item' => $item]);
        }
        return $this;
    }

    /**
     * Assign address to quote
     *
     * @param Address $address
     * @param bool $isBillingAddress
     * @return void
     */
    private function assignAddress(Address $address, bool $isBillingAddress = true): void
    {
        if ($this->isAddressAllowedForWebsite($address, $this->getStoreId())) {
            $isBillingAddress
                ? $this->setBillingAddress($address)
                : $this->setShippingAddress($address);
        }
    }

    /**
     * Check is address allowed for store
     *
     * @param Address $address
     * @param int|null $storeId
     * @return bool
     */
    private function isAddressAllowedForWebsite(Address $address, $storeId): bool
    {
        $allowedCountries = $this->allowedCountriesReader->getAllowedCountries(ScopeInterface::SCOPE_STORE, $storeId);

        return in_array($address->getCountryId(), $allowedCountries);
    }

    /**
     * Set shipping address
     *
     * @param AddressInterface $address
     * @return $this
     */
    public function setShippingAddress(AddressInterface $address = null)
    {
        if ($this->getIsMultiShipping()) {
            $this->addAddress($address->setAddressType(Address::TYPE_SHIPPING));
        } else {
            $old = $this->getAddressesCollection()->getItemById($address->getId())
                ?? $this->getShippingAddress();
            if ($old !== null) {
                $old->addData($address->getData());
            } else {
                $this->addAddress($address->setAddressType(Address::TYPE_SHIPPING));
            }
        }

        return $this;
    }

    /**
     * Retrieve quote shipping address
     *
     * @return Address|null
     */
    public function getShippingAddress(): Address|null
    {
        return null;
    }

    /**
     * Substitute customer addresses
     *
     * @param \Magento\Customer\Api\Data\AddressInterface[] $addresses
     * @return $this
     */
    public function setCustomerAddressData(array $addresses)
    {
        foreach ($addresses as $address) {
            if (!$address->getId()) {
                $this->addCustomerAddress($address);
            }
        }

        return $this;
    }

    /**
     * Add address to the customer, created out of a Data Object
     *
     * TODO refactor in scope of MAGETWO-19930
     *
     * @param \Magento\Customer\Api\Data\AddressInterface $address
     * @return $this
     */
    public function addCustomerAddress(\Magento\Customer\Api\Data\AddressInterface $address)
    {
        $addresses = (array)$this->getCustomer()->getAddresses();
        $addresses[] = $address;
        $this->getCustomer()->setAddresses($addresses);
        $this->updateCustomerData($this->getCustomer());
        return $this;
    }

    /**
     * Retrieve customer model object
     *
     * @return CustomerInterface|ExtensibleDataInterface
     */
    public function getCustomer()
    {
        /**
         * @TODO: Remove the method after all external usages are refactored in MAGETWO-19930
         * _customer and _customerFactory variables should be eliminated as well
         */
        if (null === $this->_customer) {
            try {
                $this->_customer = $this->customerRepository->getById($this->getCustomerId());
            } catch (NoSuchEntityException $e) {
                $this->_customer = $this->customerDataFactory->create();
                $this->_customer->setId(null);
            }
        }

        return $this->_customer;
    }

    /**
     * Define customer object
     *
     * Important: This method also copies customer data to quote and removes quote addresses
     *
     * @param CustomerInterface $customer
     * @return $this
     */
    public function setCustomer(CustomerInterface $customer = null)
    {
        /* @TODO: Remove the method after all external usages are refactored in MAGETWO-19930 */
        $this->_customer = $customer;
        $this->setCustomerId($customer->getId());
        $origAddresses = $customer->getAddresses();
        $customer->setAddresses([]);
        $customerDataFlatArray = $this->objectFactory->create(
            $this->extensibleDataObjectConverter->toFlatArray(
                $customer,
                [],
                CustomerInterface::class
            )
        );
        $customer->setAddresses($origAddresses);
        $this->_objectCopyService->copyFieldsetToTarget('customer_account', 'to_quote', $customerDataFlatArray, $this);

        return $this;
    }

    /**
     * Update customer data object
     *
     * @param CustomerInterface $customer
     * @return $this
     */
    public function updateCustomerData(CustomerInterface $customer)
    {
        $quoteCustomer = $this->getCustomer();
        $this->dataObjectHelper->mergeDataObjects(CustomerInterface::class, $quoteCustomer, $customer);
        $this->setCustomer($quoteCustomer);
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getCustomerTaxClassId()
    {
        /**
         * tax class can vary at any time. so instead of using the value from session,
         * we need to retrieve from db every time to get the correct tax class
         */
        //if (!$this->getData('customer_group_id') && !$this->getData('customer_tax_class_id')) {
        $groupId = $this->getCustomerGroupId();
        if ($groupId !== null) {
            $taxClassId = null;
            try {
                $taxClassId = $this->groupRepository->getById($this->getCustomerGroupId())->getTaxClassId();
            } catch (NoSuchEntityException $e) {
                /**
                 * A customer MAY create a quote and AFTER that customer group MAY be deleted.
                 * That breaks a quote because it still refers no a non-existent customer group.
                 * In such a case we should load a new customer group id from the current customer
                 * object and use it to retrieve tax class and update quote.
                 */
                $groupId = $this->getCustomer()->getGroupId();
                $this->setCustomerGroupId($groupId);
                if ($groupId !== null) {
                    $taxClassId = $this->groupRepository->getById($groupId)->getTaxClassId();
                }
            }
            $this->setCustomerTaxClassId($taxClassId);
        }

        return $this->getData(self::KEY_CUSTOMER_TAX_CLASS_ID);
    }

    /**
     * Retrieve customer group id
     *
     * @return int
     */
    public function getCustomerGroupId()
    {
        if ($this->hasData('customer_group_id')) {
            return $this->getData('customer_group_id');
        } elseif ($this->getCustomerId()) {
            return $this->getCustomer()->getGroupId();
        } else {
            return GroupInterface::NOT_LOGGED_IN_ID;
        }
    }

    /**
     * @inheritdoc
     */
    public function setCustomerTaxClassId($customerTaxClassId)
    {
        return $this->setData(self::KEY_CUSTOMER_TAX_CLASS_ID, $customerTaxClassId);
    }

    /**
     * Get address by id.
     *
     * @param int $addressId
     * @return Address|false
     */
    public function getAddressById(int $addressId): Address|false
    {
        return false;
    }

    /**
     * Get address by customer address id.
     *
     * @param int|string $addressId
     * @return Address|false
     */
    public function getAddressByCustomerAddressId(int|string $addressId): Address|false
    {
        return false;
    }

    /**
     * Get quote address by customer address ID.
     *
     * @param int|string $addressId
     * @return Address|false
     */
    public function getShippingAddressByCustomerAddressId($addressId)
    {
        /** @var Address $address */
        foreach ($this->getAddressesCollection() as $address) {
            if (!$address->isDeleted() &&
                $address->getAddressType() == Address::TYPE_SHIPPING &&
                $address->getCustomerAddressId() == $addressId
            ) {
                return $address;
            }
        }
        return false;
    }

    /**
     * Remove address.
     *
     * @param int|string $addressId
     * @return $this
     */
    public function removeAddress($addressId)
    {
        foreach ($this->getAddressesCollection() as $address) {
            if ($address->getId() == $addressId) {
                $address->isDeleted(true);
                break;
            }
        }
        return $this;
    }

    /**
     * Leave no more than one billing and one shipping address, fill them with default data
     *
     * @return $this
     */
    public function removeAllAddresses()
    {
        $addressByType = [];
        $addressesCollection = $this->getAddressesCollection();

        // mark all addresses as deleted
        foreach ($addressesCollection as $address) {
            $type = $address->getAddressType();
            if (!isset($addressByType[$type]) || $addressByType[$type]->getId() > $address->getId()) {
                $addressByType[$type] = $address;
            }
            $address->isDeleted(true);
        }

        // create new billing and shipping addresses filled with default values, set this data to existing records
        foreach ($addressByType as $type => $address) {
            $id = $address->getId();
            $emptyAddress = $this->_getAddressByType($type);
            $address->setData($emptyAddress->getData())->setId($id)->isDeleted(false);
            $emptyAddress->setDeleteImmediately(true);
        }

        // remove newly created billing and shipping addresses from collection to avoid senseless delete queries
        foreach ($addressesCollection as $key => $item) {
            if ($item->getDeleteImmediately()) {
                $addressesCollection->removeItemByKey($key);
            }
        }

        return $this;
    }

    /**
     * Retrieve quote address by type
     *
     * @param   string $type
     * @return  Address|null
     */
    protected function _getAddressByType(string $type): Address|null
    {
        return null;
    }

    /**
     * Add shipping address.
     *
     * @param AddressInterface $address
     * @return $this
     */
    public function addShippingAddress(AddressInterface $address)
    {
        $this->setShippingAddress($address);
        return $this;
    }

    /**
     * Checking items availability
     *
     * @return bool
     */
    public function hasItems()
    {
        return count($this->getAllItems()) > 0;
    }

    /**
     * Retrieve quote items array
     *
     * @return array
     */
    public function getAllItems()
    {
        $items = [];
        /** @var Item $item */
        foreach ($this->getItemsCollection() as $item) {
            $product = $item->getProduct();
            if (!$item->isDeleted() && ($product && (int)$product->getStatus() !== ProductStatus::STATUS_DISABLED)) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * Checking availability of items with decimal qty
     *
     * @return bool
     */
    public function hasItemsWithDecimalQty()
    {
        foreach ($this->getAllItems() as $item) {
            $stockItemDo = $this->stockRegistry->getStockItem(
                $item->getProduct()->getId(),
                $item->getStore()->getWebsiteId()
            );
            if ($stockItemDo->getItemId() && $stockItemDo->getIsQtyDecimal()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Checking product exist in Quote
     *
     * @param int $productId
     * @return bool
     */
    public function hasProductId($productId)
    {
        foreach ($this->getAllItems() as $item) {
            if ($item->getProductId() == $productId) {
                return true;
            }
        }

        return false;
    }

    /**
     * Mark all quote items as deleted (empty quote)
     *
     * @return $this
     */
    public function removeAllItems()
    {
        foreach ($this->getItemsCollection() as $itemId => $item) {
            if ($item->getId() === null) {
                $this->getItemsCollection()->removeItemByKey($itemId);
            } else {
                $item->isDeleted(true);
            }
        }
        return $this;
    }

    /**
     * Updates quote item with new configuration
     *
     * $params sets how current item configuration must be taken into account and additional options.
     * It's passed to \Magento\Catalog\Helper\Product->addParamsToBuyRequest() to compose resulting buyRequest.
     *
     * Basically it can hold
     * - 'current_config', \Magento\Framework\DataObject or array - current buyRequest that configures product in this
     * item, used to restore currently attached files
     * - 'files_prefix': string[a-z0-9_] - prefix that was added at frontend to names of file options (file inputs),
     *   so they won't intersect with other submitted options
     *
     * For more options see \Magento\Catalog\Helper\Product->addParamsToBuyRequest()
     *
     * @param int $itemId
     * @param DataObject $buyRequest
     * @param null|array|DataObject $params
     * @return Item
     * @throws LocalizedException
     *
     * @see \Magento\Catalog\Helper\Product::addParamsToBuyRequest()
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function updateItem($itemId, $buyRequest, $params = null)
    {
        $item = $this->getItemById($itemId);
        if (!$item) {
            throw new LocalizedException(
                __('This is the wrong quote item id to update configuration.')
            );
        }
        $productId = $item->getProduct()->getId();

        //We need to create new clear product instance with same $productId
        //to set new option values from $buyRequest
        $product = clone $this->productRepository->getById($productId, false, $this->getStore()->getId());

        if (!$params) {
            $params = new DataObject();
        } elseif (is_array($params)) {
            $params = new DataObject($params);
        }
        $params->setCurrentConfig($item->getBuyRequest());
        $buyRequest = $this->_catalogProduct->addParamsToBuyRequest($buyRequest, $params);

        $buyRequest->setResetCount(true);
        $resultItem = $this->addProduct($product, $buyRequest);

        if (is_string($resultItem)) {
            throw new LocalizedException(__($resultItem));
        }

        if ($resultItem->getParentItem()) {
            $resultItem = $resultItem->getParentItem();
        }

        if ($resultItem->getId() != $itemId) {
            /**
             * Product configuration didn't stick to original quote item
             * It either has same configuration as some other quote item's product or completely new configuration
             */
            $this->removeItem($itemId);
            $items = $this->getAllItems();
            foreach ($items as $item) {
                if ($item->getProductId() == $productId && $item->getId() != $resultItem->getId()) {
                    if ($resultItem->compare($item)) {
                        // Product configuration is same as in other quote item
                        $resultItem->setQty($resultItem->getQty() + $item->getQty());
                        $this->removeItem($item->getId());
                        break;
                    }
                }
            }
        } else {
            $resultItem->setQty($buyRequest->getQty());
        }

        return $resultItem;
    }

    /**
     * Add product. Returns error message if product type instance can't prepare product.
     *
     * @param mixed $product
     * @param null|float|DataObject $request
     * @param null|string $processMode
     * @return Item|string
     * @throws LocalizedException
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function addProduct(
        Product $product,
        $request = null,
        $processMode = AbstractType::PROCESS_MODE_FULL
    ) {
        if ($request === null) {
            $request = 1;
        }
        if (is_numeric($request)) {
            $request = $this->objectFactory->create(['qty' => $request]);
        }
        if (!$request instanceof DataObject) {
            throw new LocalizedException(
                __('We found an invalid request for adding product to quote.')
            );
        }

        if (!$product->isSalable()) {
            throw new LocalizedException(
                __('Product that you are trying to add is not available.')
            );
        }

        $cartCandidates = $product->getTypeInstance()->prepareForCartAdvanced($request, $product, $processMode);

        /**
         * Error message
         */
        if (is_string($cartCandidates) || $cartCandidates instanceof Phrase) {
            return (string)$cartCandidates;
        }

        /**
         * If prepare process return one object
         */
        if (!is_array($cartCandidates)) {
            $cartCandidates = [$cartCandidates];
        }

        $parentItem = null;
        $errors = [];
        $item = null;
        $items = [];
        foreach ($cartCandidates as $candidate) {
            // Child items can be sticked together only within their parent
            $stickWithinParent = $candidate->getParentProductId() ? $parentItem : null;
            $candidate->setStickWithinParent($stickWithinParent);

            $item = $this->getItemByProduct($candidate);
            if (!$item) {
                $item = $this->itemProcessor->init($candidate, $request);
                $item->setQuote($this);
                $item->setOptions($candidate->getCustomOptions());
                $item->setProduct($candidate);
                // Add only item that is not in quote already
                $this->addItem($item);
            }
            $items[] = $item;

            /**
             * As parent item we should always use the item of first added product
             */
            if (!$parentItem) {
                $parentItem = $item;
            }
            if ($parentItem && $candidate->getParentProductId() && !$item->getParentItem()) {
                $item->setParentItem($parentItem);
            }

            $this->itemProcessor->prepare($item, $request, $candidate);

            // collect errors instead of throwing first one
            if ($item->getHasError()) {
                $this->deleteItem($item);
                foreach ($item->getMessage(false) as $message) {
                    if (!in_array($message, $errors)) {
                        // filter duplicate messages
                        $errors[] = $message;
                    }
                }
                break;
            }
        }
        if (!empty($errors)) {
            throw new LocalizedException(__(implode("\n", $errors)));
        }

        $this->_eventManager->dispatch('sales_quote_product_add_after', ['items' => $items]);
        return $parentItem;
    }

    /**
     * Delete quote item. If it does not have identifier then it will be only removed from collection
     *
     * @param Item $item
     * @return $this
     */
    public function deleteItem(Item $item)
    {
        if ($item->getId()) {
            $this->removeItem($item->getId());
        } else {
            $quoteItems = $this->getItemsCollection();
            $items = [$item];
            if ($item->getHasChildren()) {
                foreach ($item->getChildren() as $child) {
                    $items[] = $child;
                }
            }
            foreach ($quoteItems as $key => $quoteItem) {
                foreach ($items as $item) {
                    if ($quoteItem->compare($item)) {
                        $quoteItems->removeItemByKey($key);
                    }
                }
            }
        }

        return $this;
    }

    /**
     * Remove quote item by item identifier
     *
     * @param int $itemId
     * @return $this
     */
    public function removeItem($itemId)
    {
        $item = $this->getItemById($itemId);

        if ($item) {
            $item->setQuote($this);
            $item->isDeleted(true);
            if ($item->getHasChildren()) {
                foreach ($item->getChildren() as $child) {
                    $child->isDeleted(true);
                }
            }

            $parent = $item->getParentItem();
            if ($parent) {
                $parent->isDeleted(true);
            }

            $this->_eventManager->dispatch('sales_quote_remove_item', ['quote_item' => $item]);
        }

        return $this;
    }

    /**
     * Get items summary qty.
     *
     * @return int
     */
    public function getItemsSummaryQty()
    {
        $qty = $this->getData('all_items_qty');
        if (null === $qty) {
            $qty = 0;
            foreach ($this->getAllItems() as $item) {
                if ($item->getParentItem()) {
                    continue;
                }

                $children = $item->getChildren();
                if ($children && $item->isShipSeparately()) {
                    foreach ($children as $child) {
                        $qty += $child->getQty() * $item->getQty();
                    }
                } else {
                    $qty += $item->getQty();
                }
            }
            $this->setData('all_items_qty', $qty);
        }
        return $qty;
    }

    /**
     * Get item virtual qty.
     *
     * @return int
     */
    public function getItemVirtualQty()
    {
        $qty = $this->getData('virtual_items_qty');
        if (null === $qty) {
            $qty = 0;
            foreach ($this->getAllItems() as $item) {
                if ($item->getParentItem()) {
                    continue;
                }

                $children = $item->getChildren();
                if ($children && $item->isShipSeparately()) {
                    foreach ($children as $child) {
                        if ($child->getProduct()->getIsVirtual()) {
                            $qty += $child->getQty();
                        }
                    }
                } else {
                    if ($item->getProduct()->getIsVirtual()) {
                        $qty += $item->getQty();
                    }
                }
            }
            $this->setData('virtual_items_qty', $qty);
        }
        return $qty;
    }

    /**
     * Sets payment to current quote
     *
     * @param PaymentInterface $payment
     * @return PaymentInterface
     */
    public function setPayment(PaymentInterface $payment)
    {
        if (!$this->getIsMultiPayment() && ($old = $this->getPayment())) {
            $payment->setId($old->getId());
        }
        $this->addPayment($payment);

        return $payment;
    }

    /**
     * Get payment.
     *
     * @return Payment
     */
    public function getPayment()
    {
        if (null === $this->_currentPayment || !$this->_currentPayment) {
            $this->_currentPayment = $this->_quotePaymentCollectionFactory->create()
                ->setQuoteFilter($this->getId())
                ->getFirstItem();
        }
        if ($payment = $this->_currentPayment) {
            if ($this->getId()) {
                $payment->setQuote($this);
            }
            if (!$payment->isDeleted()) {
                return $payment;
            }
        }
        $payment = $this->_quotePaymentFactory->create();
        $this->addPayment($payment);
        return $payment;
    }

    /**
     * Adds a payment to quote
     *
     * @param PaymentInterface $payment
     * @return $this
     */
    protected function addPayment(PaymentInterface $payment)
    {
        $payment->setQuote($this);
        if (!$payment->getId()) {
            $this->getPaymentsCollection()->addItem($payment);
        }
        return $this;
    }

    /**
     * Get payments collection.
     *
     * @return AbstractCollection
     */
    public function getPaymentsCollection()
    {
        if (null === $this->_payments) {
            $this->_payments = $this->_quotePaymentCollectionFactory->create()->setQuoteFilter($this->getId());

            if ($this->getId()) {
                foreach ($this->_payments as $payment) {
                    $payment->setQuote($this);
                }
            }
        }
        return $this->_payments;
    }

    /*********************** PAYMENTS ***************************/

    /**
     * Remove payment.
     *
     * @return $this
     */
    public function removePayment()
    {
        $this->getPayment()->isDeleted(true);
        return $this;
    }

    /**
     * Get all quote totals (sorted by priority)
     *
     * @return AddressTotal[]
     */
    public function getTotals()
    {
        return $this->totalsReader->fetch($this, $this->getData());
    }

    /**
     * Retrieve current quote errors
     *
     * @return array
     */
    public function getErrors()
    {
        $errors = [];
        foreach ($this->getMessages() as $message) {
            /* @var $error AbstractMessage */
            if ($message->getType() == MessageInterface::TYPE_ERROR) {
                $errors[] = $message;
            }
        }
        return $errors;
    }

    /**
     * Retrieve current quote messages
     *
     * @return array
     */
    public function getMessages()
    {
        $messages = $this->getData('messages');
        if (null === $messages) {
            $messages = [];
            $this->setData('messages', $messages);
        }
        return $messages;
    }

    /**
     * Sets flag, whether this quote has some error associated with it.
     * When TRUE - also adds 'unknown' error information to list of quote errors.
     * When FALSE - clears whole list of quote errors.
     * It's recommended to use addErrorInfo() instead - to be able to remove error statuses later.
     *
     * @param bool $flag
     * @return $this
     * @see addErrorInfo()
     */
    public function setHasError($flag)
    {
        if ($flag) {
            $this->addErrorInfo();
        } else {
            $this->_clearErrorInfo();
        }
        return $this;
    }

    /**
     * Adds error information to the quote. Automatically sets error flag.
     *
     * @param string $type An internal error type ('error', 'qty', etc.), passed then to adding messages routine
     * @param string|null $origin Usually a name of module, that embeds error
     * @param int|null $code Error code, unique for origin, that sets it
     * @param string|null $message Error message
     * @param DataObject|null $additionalData Any additional data, that caller would like to store
     * @return $this
     */
    public function addErrorInfo(
        $type = 'error',
        $origin = null,
        $code = null,
        $message = null,
        $additionalData = null
    ) {
        if (!isset($this->_errorInfoGroups[$type])) {
            $this->_errorInfoGroups[$type] = $this->_statusListFactory->create();
        }

        $this->_errorInfoGroups[$type]->addItem($origin, $code, $message, $additionalData);

        if ($message !== null) {
            $this->addMessage($message, $type);
        }
        $this->_setHasError(true);

        return $this;
    }

    /**
     * Add message.
     *
     * @param string $message
     * @param string $index
     * @return $this
     */
    public function addMessage($message, $index = 'error')
    {
        $messages = $this->getData('messages');
        if (null === $messages) {
            $messages = [];
        }

        if (isset($messages[$index])) {
            return $this;
        }

        $message = $this->messageFactory->create(MessageInterface::TYPE_ERROR, $message);

        $messages[$index] = $message;
        $this->setData('messages', $messages);
        return $this;
    }

    /**
     * Sets flag, whether this quote has some error associated with it.
     *
     * @codeCoverageIgnore
     *
     * @param bool $flag
     * @return $this
     */
    protected function _setHasError($flag)
    {
        return $this->setData('has_error', $flag);
    }

    /**
     * Clears list of errors, associated with this quote. Also automatically removes error-flag from oneself.
     *
     * @return $this
     */
    protected function _clearErrorInfo()
    {
        $this->_errorInfoGroups = [];
        $this->_setHasError(false);
        return $this;
    }

    /**
     * Removes error infos, that have parameters equal to passed in $params.
     * $params can have following keys (if not set - then any item is good for this key):
     *   'origin', 'code', 'message'
     *
     * @param string $type An internal error type ('error', 'qty', etc.), passed then to adding messages routine
     * @param array $params
     * @return $this
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function removeErrorInfosByParams($type, $params)
    {
        if ($type && !isset($this->_errorInfoGroups[$type])) {
            return $this;
        }

        $errorLists = [];
        if ($type) {
            $errorLists[] = $this->_errorInfoGroups[$type];
        } else {
            $errorLists = $this->_errorInfoGroups;
        }

        foreach ($errorLists as $type => $errorList) {
            $removedItems = $errorList->removeItemsByParams($params);
            foreach ($removedItems as $item) {
                if ($item['message'] !== null) {
                    $this->removeMessageByText($type, $item['message']);
                }
            }
        }

        $errorsExist = false;
        foreach ($this->_errorInfoGroups as $errorListCheck) {
            if ($errorListCheck->getItems()) {
                $errorsExist = true;
                break;
            }
        }
        if (!$errorsExist) {
            $this->_setHasError(false);
        }

        return $this;
    }

    /**
     * Removes message by text
     *
     * @param string $type
     * @param string $text
     * @return $this
     */
    public function removeMessageByText($type, $text)
    {
        $messages = $this->getData('messages');
        if (null === $messages) {
            $messages = [];
        }

        if (!isset($messages[$type])) {
            return $this;
        }

        $message = $messages[$type];
        if ($message instanceof AbstractMessage) {
            $message = $message->getText();
        } elseif (!is_string($message)) {
            return $this;
        }
        if ($message == $text) {
            unset($messages[$type]);
            $this->setData('messages', $messages);
        }
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getItems()
    {
        return $this->_getData(self::KEY_ITEMS);
    }

    /**
     * @inheritdoc
     */
    public function setItems(array $items = null)
    {
        return $this->setData(self::KEY_ITEMS, $items);
    }

    /**
     * Generate new increment order id and associate it with current quote
     *
     * @return $this
     */
    public function reserveOrderId()
    {
        if (!$this->getReservedOrderId()) {
            $this->setReservedOrderId($this->_getResource()->getReservedOrderId($this));
        } else {
            //checking if reserved order id was already used for some order
            //if yes reserving new one if not using old one
            if ($this->orderIncrementIdChecker->isIncrementIdUsed($this->getReservedOrderId())) {
                $this->setReservedOrderId($this->_getResource()->getReservedOrderId($this));
            }
        }
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getReservedOrderId()
    {
        return $this->_getData(self::KEY_RESERVED_ORDER_ID);
    }

    /**
     * @inheritdoc
     */
    public function setReservedOrderId($reservedOrderId)
    {
        return $this->setData(self::KEY_RESERVED_ORDER_ID, $reservedOrderId);
    }

    /**
     * Validate minimum amount.
     *
     * @param bool $multishipping
     * @return bool
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function validateMinimumAmount($multishipping = false)
    {
        $storeId = $this->getStoreId();
        $minOrderActive = $this->_scopeConfig->isSetFlag(
            'sales/minimum_order/active',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        if (!$minOrderActive) {
            return true;
        }
        $includeDiscount = $this->_scopeConfig->getValue(
            'sales/minimum_order/include_discount_amount',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        $minOrderMulti = $this->_scopeConfig->isSetFlag(
            'sales/minimum_order/multi_address',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        $minAmount = $this->_scopeConfig->getValue(
            'sales/minimum_order/amount',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        $taxInclude = $this->_scopeConfig->getValue(
            'sales/minimum_order/tax_including',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        $addresses = $this->getAllAddresses();

        if (!$multishipping) {
            foreach ($addresses as $address) {
                /* @var $address Address */
                if (!$address->validateMinimumAmount()) {
                    return false;
                }
            }
            return true;
        }

        if (!$minOrderMulti) {
            foreach ($addresses as $address) {
                $taxes = $taxInclude
                    ? $address->getBaseTaxAmount() + $address->getBaseDiscountTaxCompensationAmount()
                    : 0;
                foreach ($address->getQuote()->getItemsCollection() as $item) {
                    /** @var Item $item */
                    $amount = $includeDiscount ?
                        $item->getBaseRowTotal() - $item->getBaseDiscountAmount() + $taxes :
                        $item->getBaseRowTotal() + $taxes;

                    if ($amount < $minAmount) {
                        return false;
                    }
                }
            }
        } else {
            $baseTotal = 0;
            foreach ($addresses as $address) {
                $taxes = $taxInclude
                    ? $address->getBaseTaxAmount() + $address->getBaseDiscountTaxCompensationAmount()
                    : 0;
                $baseTotal += $includeDiscount ?
                    $address->getBaseSubtotalWithDiscount() + $taxes :
                    $address->getBaseSubtotal() + $taxes;
            }
            if ($baseTotal < $minAmount) {
                return false;
            }
        }
        return true;
    }

    /**
     * Get all quote addresses
     *
     * @return Address[]
     */
    public function getAllAddresses(): array
    {
        return [];
    }

    /**
     * Has a virtual products on quote
     *
     * @return bool
     */
    public function hasVirtualItems()
    {
        $hasVirtual = false;
        foreach ($this->getItemsCollection() as $item) {
            if ($item->getParentItemId()) {
                continue;
            }
            if ($item->getProduct()->isVirtual()) {
                $hasVirtual = true;
            }
        }
        return $hasVirtual;
    }

    /**
     * Merge quotes
     *
     * @param Quote $quote
     * @return $this
     */
    public function merge(Quote $quote)
    {
        $this->_eventManager->dispatch(
            $this->_eventPrefix . '_merge_before',
            [$this->_eventObject => $this, 'source' => $quote]
        );

        foreach ($quote->getAllVisibleItems() as $item) {
            $found = false;
            foreach ($this->getAllItems() as $quoteItem) {
                if ($quoteItem->compare($item)) {
                    $quoteItem->setQty($quoteItem->getQty() + $item->getQty());
                    $this->itemProcessor->merge($item, $quoteItem);
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $newItem = clone $item;
                $this->addItem($newItem);
                if ($item->getHasChildren()) {
                    foreach ($item->getChildren() as $child) {
                        $newChild = clone $child;
                        $newChild->setParentItem($newItem);
                        $this->addItem($newChild);
                    }
                }
            }
        }

        /**
         * Init shipping and billing address if quote is new
         */
        if (!$this->getId()) {
            $this->getShippingAddress();
            $this->getBillingAddress();
        }

        if ($quote->getCouponCode()) {
            $this->setCouponCode($quote->getCouponCode());
        }

        $this->_eventManager->dispatch(
            $this->_eventPrefix . '_merge_after',
            [$this->_eventObject => $this, 'source' => $quote]
        );

        return $this;
    }

    /**
     * Get array of all items what can be display directly
     *
     * @return Item[]
     */
    public function getAllVisibleItems(): array
    {
        $items = [];
        foreach ($this->getItemsCollection() as $item) {
            if (!$item->isDeleted() && !$item->getParentItemId() && !$item->getParentItem()) {
                $items[] = $item;
            }
        }
        return $items;
    }

    /**
     * Checks if it was set
     *
     * @return bool
     */
    public function addressCollectionWasSet()
    {
        return null !== $this->_addresses;
    }

    /**
     * Checks if it was set
     *
     * @return bool
     */
    public function itemsCollectionWasSet()
    {
        return null !== $this->_items;
    }

    /**
     * Checks if it was set
     *
     * @return bool
     */
    public function paymentsCollectionWasSet()
    {
        return null !== $this->_payments;
    }

    /**
     * Checks if it was set
     *
     * @return bool
     */
    public function currentPaymentWasSet()
    {
        return null !== $this->_currentPayment;
    }

    /**
     * Return checkout method code
     *
     * @param boolean $originalMethod if true return defined method from beginning
     * @return string
     */
    public function getCheckoutMethod($originalMethod = false)
    {
        if ($this->getCustomerId() && !$originalMethod) {
            return self::CHECKOUT_METHOD_LOGIN_IN;
        }
        return $this->_getData(self::KEY_CHECKOUT_METHOD);
    }

    /**
     * Get quote items assigned to different quote addresses populated per item qty.
     *
     * @return array
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function getShippingAddressesItems()
    {
        if ($this->shippingAddressesItems !== null) {
            return $this->shippingAddressesItems;
        }
        $items = [];
        $addresses = $this->getAllAddresses();
        foreach ($addresses as $address) {
            foreach ($address->getAllItems() as $item) {
                if ($item->getParentItemId()) {
                    continue;
                }
                if ($item->getProduct()->getIsVirtual()) {
                    $items[] = $item;
                    continue;
                }
                if ($item->getQty() > 1) {
                    //DB table `quote_item` qty value can not be set to 1, if having more than 1 child references
                    //in table `quote_address_item`.
                    if ($item->getItemId() !== null
                        && count($this->getQuoteShippingAddressItemsByQuoteItemId($item->getItemId())) > 1) {
                        continue;
                    }
                    for ($itemIndex = 0, $itemQty = $item->getQty(); $itemIndex < $itemQty; $itemIndex++) {
                        if ($itemIndex === 0) {
                            $addressItem = $item;
                        } else {
                            $addressItem = clone $item;
                        }
                        $addressItem->setQty(1)->setCustomerAddressId($address->getCustomerAddressId())->save();
                        $items[] = $addressItem;
                    }
                } else {
                    $item->setCustomerAddressId($address->getCustomerAddressId());
                    $items[] = $item;
                }
            }
        }
        $this->shippingAddressesItems = $items;
        return $items;
    }

    /**
     * Returns quote address items
     *
     * @param int $itemId
     * @return array
     */
    private function getQuoteShippingAddressItemsByQuoteItemId(int $itemId): array
    {
        $addressItems = [];
        if ($this->isMultipleShippingAddresses()) {
            $addresses = $this->getAllShippingAddresses();
            foreach ($addresses as $address) {
                foreach ($address->getAllItems() as $item) {
                    if ($item->getParentItemId() || $item->getProduct()->getIsVirtual()) {
                        continue;
                    }
                    if ((int)$item->getQuoteItemId() === $itemId) {
                        $addressItems[] = $item;
                    }
                }
            }
        }

        return $addressItems;
    }

    /**
     * Check if there are more than one shipping address
     *
     * @return bool
     */
    public function isMultipleShippingAddresses()
    {
        return count($this->getAllShippingAddresses()) > 1;
    }

    /**
     * Get all shipping addresses.
     *
     * @return array
     */
    public function getAllShippingAddresses()
    {
        return [];
    }

    /**
     * Sets the payment method that is used to process the cart.
     *
     * @codeCoverageIgnore
     *
     * @param string $checkoutMethod
     * @return $this
     */
    public function setCheckoutMethod($checkoutMethod)
    {
        return $this->setData(self::KEY_CHECKOUT_METHOD, $checkoutMethod);
    }

    /**
     * Prevent quote from saving
     *
     * @codeCoverageIgnore
     *
     * @return $this
     */
    public function preventSaving()
    {
        $this->_preventSaving = true;
        return $this;
    }

    /**
     * Check if model can be saved
     *
     * @codeCoverageIgnore
     *
     * @return bool
     */
    public function isPreventSaving()
    {
        return $this->_preventSaving;
    }

    /**
     * Retrieve existing extension attributes object or create a new one.
     *
     * @return CartExtensionInterface|null
     */
    public function getExtensionAttributes()
    {
        return $this->_getExtensionAttributes();
    }

    /**
     * Set an extension attributes object.
     *
     * @param CartExtensionInterface $extensionAttributes
     * @return $this
     */
    public function setExtensionAttributes(CartExtensionInterface $extensionAttributes)
    {
        return $this->_setExtensionAttributes($extensionAttributes);
    }

    /**
     * Init resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(\Magento\Quote\Model\ResourceModel\Quote::class);
    }

    /**
     * Adding catalog product object data to quote
     *
     * @param Product $product
     * @param int $qty
     * @return Item
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function _addCatalogProduct(Product $product, $qty = 1)
    {
        $newItem = false;
        $item = $this->getItemByProduct($product);
        if (!$item) {
            $item = $this->_quoteItemFactory->create();
            $item->setQuote($this);
            if ($this->_appState->getAreaCode() === FrontNameResolver::AREA_CODE) {
                $item->setStoreId($this->getStore()->getId());
            } else {
                $item->setStoreId($this->_storeManager->getStore()->getId());
            }
            $newItem = true;
        }

        /**
         * We can't modify existing child items
         */
        if ($item->getId() && $product->getParentProductId()) {
            return $item;
        }

        $item->setOptions($product->getCustomOptions())->setProduct($product);

        // Add only item that is not in quote already (there can be other new or already saved item
        if ($newItem) {
            $this->addItem($item);
        }

        return $item;
    }

    /**
     * Retrieve quote item by product id
     *
     * @param   Product $product
     * @return  Item|bool
     */
    public function getItemByProduct($product)
    {
        /** @var Item[] $items */
        $items = $this->getItemsCollection()->getItemsByColumnValue('product_id', $product->getId());
        foreach ($items as $item) {
            if (!$item->isDeleted()
                && $item->getProduct()
                && $item->getProduct()->getStatus() !== ProductStatus::STATUS_DISABLED
                && $item->representProduct($product)
            ) {
                return $item;
            }
        }
        return false;
    }
}
