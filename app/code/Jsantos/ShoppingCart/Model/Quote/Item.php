<?php

declare(strict_types=1);

namespace Jsantos\ShoppingCart\Model\Quote;

use Jsantos\ShoppingCart\Api\Data\CartItemExtensionInterface;
use Jsantos\ShoppingCart\Api\Data\CartItemInterface;
use Jsantos\ShoppingCart\Api\Data\ProductOptionInterface;
use Jsantos\ShoppingCart\Model\Quote;
use Jsantos\ShoppingCart\Model\Quote\Item\AbstractItem;
use Jsantos\ShoppingCart\Model\Quote\Item\Option;
use Jsantos\ShoppingCart\Model\Quote\Item\OptionFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Locale\FormatInterface;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\Item\Compare;
use Magento\Quote\Model\Quote\Item\Option\ComparatorInterface;
use Magento\Sales\Model\Status\ListFactory;
use Magento\Sales\Model\Status\ListStatus;

/**
 * Sales Quote Item Model
 *
 * @api
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.ExcessivePublicCount)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 * @since 100.0.2
 */
class Item extends AbstractItem implements CartItemInterface
{
    /**
     * Prefix of model events names
     *
     * @var string
     */
    protected $_eventPrefix = 'sales_quote_item';

    /**
     * Parameter name in event
     *
     * In observe method you can use $observer->getEvent()->getObject() in this case
     *
     * @var string
     */
    protected $_eventObject = 'item';

    /**
     * Quote model object
     *
     * @var Quote
     */
    protected $_quote;

    /**
     * Item options array
     *
     * @var array
     */
    protected $_options = [];

    /**
     * Item options by code cache
     *
     * @var array
     */
    protected array $_optionsByCode = [];

    /**
     * Not Represent option
     *
     * @var array
     */
    protected $_notRepresentOptions = ['info_buyRequest'];

    /**
     * Flag stating that options were successfully saved
     *
     * @var bool
     */
    protected $_flagOptionsSaved;

    /**
     * Array of errors associated with this quote item
     *
     * @var ListStatus
     */
    protected $_errorInfos;

    /**
     * @var FormatInterface
     */
    protected $_localeFormat;

    /**
     * @var OptionFactory
     */
    protected $_itemOptionFactory;

    /**
     * @var Compare
     */
    protected $quoteItemCompare;

    /**
     * @var StockRegistryInterface
     * @deprecated 101.0.0
     * @see nothing
     */
    protected $stockRegistry;

    /**
     * Serializer interface instance.
     *
     * @var Json
     */
    private $serializer;

    /**
     * Item options comparator
     *
     * @var ComparatorInterface
     */
    private $itemOptionComparator;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param ExtensionAttributesFactory $extensionFactory
     * @param AttributeValueFactory $customAttributeFactory
     * @param ProductRepositoryInterface $productRepository
     * @param PriceCurrencyInterface $priceCurrency
     * @param ListFactory $statusListFactory
     * @param FormatInterface $localeFormat
     * @param Item\OptionFactory $itemOptionFactory
     * @param Item\Compare $quoteItemCompare
     * @param StockRegistryInterface $stockRegistry
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     *
     * @param Json|null $serializer
     * @param ComparatorInterface|null $itemOptionComparator
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ExtensionAttributesFactory $extensionFactory,
        AttributeValueFactory $customAttributeFactory,
        ProductRepositoryInterface $productRepository,
        PriceCurrencyInterface $priceCurrency,
        ListFactory $statusListFactory,
        FormatInterface $localeFormat,
        OptionFactory $itemOptionFactory,
        Compare $quoteItemCompare,
        StockRegistryInterface $stockRegistry,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = [],
        Json $serializer = null,
        ?ComparatorInterface $itemOptionComparator = null
    ) {
        $this->_errorInfos = $statusListFactory->create();
        $this->_localeFormat = $localeFormat;
        $this->_itemOptionFactory = $itemOptionFactory;
        $this->quoteItemCompare = $quoteItemCompare;
        $this->stockRegistry = $stockRegistry;
        $this->serializer = $serializer ?: ObjectManager::getInstance()
            ->get(Json::class);
        $this->itemOptionComparator = $itemOptionComparator
            ?: ObjectManager::getInstance()->get(ComparatorInterface::class);
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $productRepository,
            $priceCurrency,
            $resource,
            $resourceCollection,
            $data
        );
    }

    /**
     * Quote Item Before Save prepare data process
     *
     * @return $this
     */
    public function beforeSave(): static
    {
        parent::beforeSave();
        $this->setIsVirtual($this->getProduct()->getIsVirtual());
        if ($this->getQuote()) {
            $this->setQuoteId($this->getQuote()->getId());
        }
        return $this;
    }

    /**
     * Retrieve quote model object
     *
     * @codeCoverageIgnore
     *
     * @return Quote
     */
    public function getQuote(): Quote
    {
        return $this->_quote;
    }

    /**
     * Declare quote model object
     *
     * @param  Quote $quote
     * @return $this
     */
    public function setQuote(Quote $quote): static
    {
        $this->_quote = $quote;
        $this->setQuoteId($quote->getId());
        $this->setStoreId($quote->getStoreId());
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setQuoteId(string $quoteId): static
    {
        return $this->setData(self::KEY_QUOTE_ID, $quoteId);
    }

    /**
     * Retrieve address model
     *
     * @return Address
     */
    public function getAddress(): Address
    {
        if ($this->getQuote()->isVirtual()) {
            $address = $this->getQuote()->getBillingAddress();
        } else {
            $address = $this->getQuote()->getShippingAddress();
        }

        return $address;
    }

    /**
     * Adding quantity to quote item
     *
     * @param float $qty
     * @return $this
     */
    public function addQty($qty)
    {
        /**
         * We can't modify quantity of existing items which have parent
         * This qty declared just once during add process and is not editable
         */
        if (!$this->getParentItem() || !$this->getId()) {
            $qty = $this->_prepareQty($qty);
            $this->setQtyToAdd($qty);
            $this->setPreviousQty($this->getQty());
            $this->setQty($this->getQty() + $qty);
        }
        return $this;
    }

    /**
     * Prepare quantity
     *
     * @param float|int $qty
     * @return int|float
     */
    protected function _prepareQty($qty)
    {
        $qty = $this->_localeFormat->getNumber($qty);
        $qty = $qty > 0 ? $qty : 1;
        return $qty;
    }

    /**
     * @inheritdoc
     */
    public function getQty()
    {
        return $this->getData(self::KEY_QTY);
    }

    /**
     * Declare quote item quantity
     *
     * @param float $qty
     * @return $this
     */
    public function setQty($qty)
    {
        $qty = $this->_prepareQty($qty);
        $oldQty = $this->_getData(self::KEY_QTY);
        $this->setData(self::KEY_QTY, $qty);

        // TODO: Reimplement event
        //$this->_eventManager->dispatch('sales_quote_item_qty_set_after', ['item' => $this]);

        if ($this->getQuote() && $this->getQuote()->getIgnoreOldQty()) {
            return $this;
        }

        if ($this->getUseOldQty()) {
            $this->setData(self::KEY_QTY, $oldQty);
        }

        return $this;
    }

    /**
     * Set option product with Qty
     *
     * @codeCoverageIgnore
     *
     * @param array $qtyOptions
     * @return $this
     */
    public function setQtyOptions($qtyOptions)
    {
        return $this->setData('qty_options', $qtyOptions);
    }

    /**
     * Check product representation in item
     *
     * @param   Product $product
     * @return  bool
     */
    public function representProduct($product)
    {
        $itemProduct = $this->getProduct();
        if (!$product || $itemProduct->getId() != $product->getId()) {
            return false;
        }

        /**
         * Check maybe product is planned to be a child of some quote item - in this case we limit search
         * only within same parent item
         */
        $stickWithinParent = $product->getStickWithinParent();
        if ($stickWithinParent) {
            if ($this->getParentItem() !== $stickWithinParent) {
                return false;
            }
        }

        // Check options
        $itemOptions = $this->getOptionsByCode();
        $productOptions = $product->getCustomOptions();

        if (!$this->compareOptions($itemOptions, $productOptions)) {
            return false;
        }
        if (!$this->compareOptions($productOptions, $itemOptions)) {
            return false;
        }
        return true;
    }

    /**
     * Get all item options as array with codes in array key
     *
     * @codeCoverageIgnore
     *
     * @return array
     */
    public function getOptionsByCode()
    {
        return $this->_optionsByCode;
    }

    /**
     * Check if two options array are identical
     * First options array is prerogative
     * Second options array checked against first one
     *
     * @param array $options1
     * @param array $options2
     * @return bool
     */
    public function compareOptions($options1, $options2)
    {
        foreach ($options1 as $option) {
            $code = $option->getCode();
            if (in_array($code, $this->_notRepresentOptions)) {
                continue;
            }
            if (!isset($options2[$code])
                || !$this->itemOptionComparator->compare($options2[$code], $option)
            ) {
                return false;
            }
        }
        return true;
    }

    /**
     * Compare items
     *
     * @param   \Magento\Quote\Model\Quote\Item $item
     * @return  bool
     */
    public function compare($item)
    {
        return $this->quoteItemCompare->compare($this, $item);
    }

    /**
     * Get item product type
     *
     * @return string
     */
    public function getProductType()
    {
        $option = $this->getOptionByCode(self::KEY_PRODUCT_TYPE);
        if ($option) {
            return $option->getValue();
        }
        $product = $this->getProduct();
        if ($product) {
            return $product->getTypeId();
        }
        // $product should always exist or there will be an error in getProduct()
        return $this->_getData(self::KEY_PRODUCT_TYPE);
    }

    /**
     * Get item option by code
     *
     * @param   string $code
     * @return  Option || null
     */
    public function getOptionByCode($code)
    {
        if (isset($this->_optionsByCode[$code]) && !$this->_optionsByCode[$code]->isDeleted()) {
            return $this->_optionsByCode[$code];
        }
        return null;
    }

    /**
     * Return real product type of item
     *
     * @codeCoverageIgnore
     *
     * @return string
     */
    public function getRealProductType()
    {
        return $this->_getData(self::KEY_PRODUCT_TYPE);
    }

    /**
     * Convert Quote Item to array
     *
     * @param array $arrAttributes
     * @return array
     */
    public function toArray(array $arrAttributes = [])
    {
        $data = parent::toArray($arrAttributes);

        $product = $this->getProduct();
        if ($product) {
            $data['product'] = $product->toArray();
        }
        return $data;
    }

    /**
     * Can specify specific actions for ability to change given quote options values
     * Example: cataloginventory decimal qty validation may change qty to int,
     * so need to change quote item qty option value.
     *
     * @param DataObject $option
     * @param int|float|null $value
     * @return $this
     */
    public function updateQtyOption(DataObject $option, $value)
    {
        $optionProduct = $option->getProduct();
        $options = $this->getQtyOptions();

        if (isset($options[$optionProduct->getId()])) {
            $options[$optionProduct->getId()]->setValue($value);
        }

        $this->getProduct()->getTypeInstance()->updateQtyOption(
            $this->getOptions(),
            $option,
            $value,
            $this->getProduct()
        );

        return $this;
    }

    /**
     * Retrieve option product with Qty
     *
     * Return array
     * 'qty'        => the qty
     * 'product'    => the product model
     *
     * @return array
     */
    public function getQtyOptions()
    {
        $qtyOptions = $this->getData('qty_options');
        if ($qtyOptions === null) {
            $productIds = [];
            $qtyOptions = [];
            foreach ($this->getOptions() as $option) {
                /** @var $option Option */
                if (is_object($option->getProduct())
                    && $option->getProduct()->getId() != $this->getProduct()->getId()
                ) {
                    $productIds[$option->getProduct()->getId()] = $option->getProduct()->getId();
                }
            }

            foreach ($productIds as $productId) {
                $option = $this->getOptionByCode('product_qty_' . $productId);
                if ($option) {
                    $qtyOptions[$productId] = $option;
                }
            }

            $this->setData('qty_options', $qtyOptions);
        }

        return $qtyOptions;
    }

    /**
     * Get all item options
     *
     * @codeCoverageIgnore
     *
     * @return Option[]
     */
    public function getOptions()
    {
        return $this->_options;
    }

    /**
     * Initialize quote item options
     *
     * @param array $options
     * @return $this
     */
    public function setOptions($options)
    {
        if (is_array($options)) {
            foreach ($options as $option) {
                $this->addOption($option);
            }
        }
        return $this;
    }

    /**
     * Add option to item
     *
     * @param Option|DataObject|array $option
     * @return $this
     * @throws LocalizedException
     */
    public function addOption($option)
    {
        if (is_array($option)) {
            $option = $this->_itemOptionFactory->create()->setData($option)->setItem($this);
        } elseif ($option instanceof DataObject &&
            !$option instanceof Option
        ) {
            $option = $this->_itemOptionFactory->create()->setData(
                $option->getData()
            )->setProduct(
                $option->getProduct()
            )->setItem(
                $this
            );
        } elseif ($option instanceof Option) {
            $option->setItem($this);
        } else {
            throw new LocalizedException(__('We found an invalid item option format.'));
        }

        $exOption = $this->getOptionByCode($option->getCode());
        if ($exOption) {
            $exOption->addData($option->getData());
        } else {
            $this->_addOptionCode($option);
            $this->_options[] = $option;
        }
        return $this;
    }

    /**
     * Setup product for quote item
     *
     * @param Product $product
     * @return $this
     */
    public function setProduct($product)
    {
        if ($this->getQuote()) {
            $product->setStoreId($this->getQuote()->getStoreId());
            $product->setCustomerGroupId($this->getQuote()->getCustomerGroupId());
        }
        $this->setData('product', $product)
            ->setProductId($product->getId())
            ->setProductType($product->getTypeId())
            ->setSku($this->getProduct()->getSku())
            ->setName($product->getName())
            ->setWeight($this->getProduct()->getWeight())
            ->setTaxClassId($product->getTaxClassId())
            ->setBaseCost($product->getCost());

        $stockItem = $product->getExtensionAttributes()->getStockItem();
        $this->setIsQtyDecimal($stockItem ? $stockItem->getIsQtyDecimal() : false);

        $this->_eventManager->dispatch(
            'sales_quote_item_set_product',
            ['product' => $product, 'quote_item' => $this]
        );

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setName($name)
    {
        return $this->setData(self::KEY_NAME, $name);
    }

    /**
     * @inheritdoc
     */
    public function setSku($sku)
    {
        return $this->setData(self::KEY_SKU, $sku);
    }

    /**
     * @inheritdoc
     */
    public function setProductType($productType)
    {
        return $this->setData(self::KEY_PRODUCT_TYPE, $productType);
    }

    /**
     * @inheritdoc
     */
    public function getSku()
    {
        return $this->getData(self::KEY_SKU);
    }

    /**
     * @inheritdoc
     */
    public function getName()
    {
        return $this->getData(self::KEY_NAME);
    }

    /**
     * @inheritdoc
     *
     * @return CartItemExtensionInterface|null
     */
    public function getExtensionAttributes()
    {
        return $this->_getExtensionAttributes();
    }

    /**
     * Register option code
     *
     * @param Option $option
     * @return $this
     * @throws LocalizedException
     */
    protected function _addOptionCode($option)
    {
        if (!isset($this->_optionsByCode[$option->getCode()])) {
            $this->_optionsByCode[$option->getCode()] = $option;
        } else {
            throw new LocalizedException(
                __('An item option with code %1 already exists.', $option->getCode())
            );
        }
        return $this;
    }

    /**
     * Remove option from item options
     *
     * @param string $code
     * @return $this
     */
    public function removeOption($code)
    {
        $option = $this->getOptionByCode($code);
        if ($option) {
            $option->isDeleted(true);
        }
        return $this;
    }

    /**
     * Mar option save requirement
     *
     * @codeCoverageIgnore
     *
     * @param bool $flag
     * @return void
     */
    public function setIsOptionsSaved($flag)
    {
        $this->_flagOptionsSaved = $flag;
    }

    /**
     * Were options saved
     *
     * @codeCoverageIgnore
     *
     * @return bool
     */
    public function isOptionsSaved()
    {
        return $this->_flagOptionsSaved;
    }

    /**
     * Save item options after item saved
     *
     * @return \Magento\Quote\Model\Quote\Item
     */
    public function afterSave()
    {
        $this->saveItemOptions();
        return parent::afterSave();
    }

    /**
     * Save item options
     *
     * @return $this
     */
    public function saveItemOptions()
    {
        foreach ($this->_options as $index => $option) {
            if ($option->isDeleted()) {
                $option->delete();
                unset($this->_options[$index]);
                unset($this->_optionsByCode[$option->getCode()]);
            } else {
                if (!$option->getItem() || !$option->getItem()->getId()) {
                    $option->setItem($this);
                }
                $option->save();
            }
        }

        $this->_flagOptionsSaved = true;
        // Report to watchers that options were saved

        return $this;
    }

    /**
     * Clone quote item
     *
     * @return $this
     */
    public function __clone()
    {
        parent::__clone();
        $options = $this->getOptions();
        $this->_quote = null;
        $this->_options = [];
        $this->_optionsByCode = [];
        foreach ($options as $option) {
            $this->addOption(clone $option);
        }
        return $this;
    }

    /**
     * Get formatted buy request.
     *
     * Returns object, holding request received from product view page with keys and options for configured product.
     *
     * @return DataObject
     */
    public function getBuyRequest()
    {
        $option = $this->getOptionByCode('info_buyRequest');
        $data = $option ? $this->serializer->unserialize($option->getValue()) : [];
        $buyRequest = new DataObject($data);

        // Overwrite standard buy request qty, because item qty could have changed since adding to quote
        $buyRequest->setOriginalQty($buyRequest->getQty())->setQty($this->getQty() * 1);

        return $buyRequest;
    }

    /**
     * Sets flag, whether this quote item has some error associated with it.
     * When TRUE - also adds 'unknown' error information to list of quote item errors.
     * When FALSE - clears whole list of quote item errors.
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
     * Adds error information to the quote item.
     *
     * Automatically sets error flag.
     *
     * @param string|null $origin Usually a name of module, that embeds error
     * @param int|null $code Error code, unique for origin, that sets it
     * @param string|null $message Error message
     * @param DataObject|null $additionalData Any additional data, that caller would like to store
     * @return $this
     */
    public function addErrorInfo($origin = null, $code = null, $message = null, $additionalData = null)
    {
        $this->_errorInfos->addItem($origin, $code, $message, $additionalData);
        if ($message !== null) {
            $this->setMessage($message);
        }
        $this->_setHasError(true);

        return $this;
    }

    /**
     * Sets flag, whether this quote item has some error associated with it.
     *
     * @param bool $flag
     * @return Item
     */
    protected function _setHasError($flag)
    {
        return $this->setData('has_error', $flag);
    }

    /**
     * Clears list of errors, associated with this quote item.
     *
     * Also automatically removes error-flag from oneself.
     *
     * @return $this
     */
    protected function _clearErrorInfo()
    {
        $this->_errorInfos->clear();
        $this->_setHasError(false);
        return $this;
    }

    /**
     * Retrieves all error infos, associated with this item
     *
     * @return array
     */
    public function getErrorInfos()
    {
        return $this->_errorInfos->getItems();
    }

    /**
     * Removes error infos, that have parameters equal to passed in $params.
     * $params can have following keys (if not set - then any item is good for this key):
     *   'origin', 'code', 'message'
     *
     * @param array $params
     * @return $this
     */
    public function removeErrorInfosByParams($params)
    {
        $removedItems = $this->_errorInfos->removeItemsByParams($params);
        foreach ($removedItems as $item) {
            if ($item['message'] !== null) {
                $this->removeMessageByText($item['message']);
            }
        }

        if (!$this->_errorInfos->getItems()) {
            $this->_setHasError(false);
        }

        return $this;
    }

    /**
     * @inheritdoc
     * @codeCoverageIgnoreStart
     */
    public function getItemId()
    {
        return $this->getData(self::KEY_ITEM_ID);
    }

    /**
     * @inheritdoc
     */
    public function setItemId($itemID)
    {
        return $this->setData(self::KEY_ITEM_ID, $itemID);
    }

    /**
     * @inheritdoc
     */
    public function getPrice()
    {
        return $this->getData(self::KEY_PRICE);
    }

    /**
     * @inheritdoc
     */
    public function setPrice($price)
    {
        return $this->setData(self::KEY_PRICE, $price);
    }

    /**
     * @inheritdoc
     */
    public function getQuoteId()
    {
        return $this->getData(self::KEY_QUOTE_ID);
    }

    /**
     * Returns product option
     *
     * @return ProductOptionInterface|null
     */
    public function getProductOption()
    {
        return $this->getData(self::KEY_PRODUCT_OPTION);
    }

    /**
     * Sets product option
     *
     * @param \Magento\Quote\Api\Data\ProductOptionInterface $productOption
     * @return $this
     */
    public function setProductOption(ProductOptionInterface $productOption)
    {
        return $this->setData(self::KEY_PRODUCT_OPTION, $productOption);
    }

    /**
     * @inheritdoc
     *
     * @param CartItemExtensionInterface $extensionAttributes
     * @return $this
     */
    public function setExtensionAttributes(CartItemExtensionInterface $extensionAttributes)
    {
        return $this->_setExtensionAttributes($extensionAttributes);
    }

    //@codeCoverageIgnoreEnd

    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(\Magento\Quote\Model\ResourceModel\Quote\Item::class);
    }

    /**
     * Checks that item model has data changes.
     *
     * Call save item options if model isn't need to save in DB
     *
     * @return boolean
     */
    protected function _hasModelChanged()
    {
        if (!$this->hasDataChanges()) {
            return false;
        }

        return $this->_getResource()->hasDataChanged($this);
    }
}
