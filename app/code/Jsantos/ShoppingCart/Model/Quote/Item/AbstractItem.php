<?php

declare(strict_types=1);

namespace Jsantos\ShoppingCart\Model\Quote\Item;

use Exception;
use Jsantos\ShoppingCart\Model\Quote;
use Jsantos\ShoppingCart\Model\Quote\Item;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Configuration\Item\ItemInterface;
use Magento\Catalog\Model\Product\Type\AbstractType;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractExtensibleModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Registry;
use Magento\Quote\Model\Quote\Address;
use Magento\Store\Model\Store;

/**
 * Quote item abstract model
 *
 * Price attributes:
 *  - price - initial item price, declared during product association
 *  - original_price - product price before any calculations
 *  - calculation_price - prices for item totals calculation
 *  - custom_price - new price that can be declared by user and recalculated during calculation process
 *  - original_custom_price - original defined value of custom price without any conversion
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @since 100.0.2
 */
abstract class AbstractItem extends AbstractExtensibleModel implements
    ItemInterface
{
    /**
     * @var Item|null
     */
    protected ?Item $_parentItem = null;

    /**
     * @var AbstractItem[]
     */
    protected array $_children = [];

    /**
     * @var array
     */
    protected array $_messages = [];

    /**
     * List of custom options
     *
     * @var array
     */
    protected array $_optionsByCode;

    /**
     * @var ProductRepositoryInterface
     */
    protected ProductRepositoryInterface $productRepository;

    /**
     * @var PriceCurrencyInterface
     */
    protected PriceCurrencyInterface $priceCurrency;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param ExtensionAttributesFactory $extensionFactory
     * @param AttributeValueFactory $customAttributeFactory
     * @param ProductRepositoryInterface $productRepository
     * @param PriceCurrencyInterface $priceCurrency
     * @param AbstractResource $resource
     * @param AbstractDb $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ExtensionAttributesFactory $extensionFactory,
        AttributeValueFactory $customAttributeFactory,
        ProductRepositoryInterface $productRepository,
        PriceCurrencyInterface $priceCurrency,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $resource,
            $resourceCollection,
            $data
        );
        $this->productRepository = $productRepository;
        $this->priceCurrency = $priceCurrency;
    }

    /**
     * Retrieve address model
     *
     * @return Address
     */
    abstract public function getAddress(): Address;

    /**
     * Returns special download params (if needed) for custom option with type = 'file'
     * Needed to implement \Magento\Catalog\Model\Product\Configuration\Item\Interface.
     * Return null, as quote item needs no additional configuration.
     *
     * @return null|DataObject
     */
    public function getFileDownloadParams(): ?DataObject
    {
        return null;
    }

    /**
     * Specify parent item id before saving data
     *
     * @return $this
     */
    public function beforeSave(): static
    {
        parent::beforeSave();
        if ($this->getParentItem()) {
            $this->setParentItemId($this->getParentItem()->getId());
        }
        return $this;
    }

    /**
     * Get parent item
     *
     * @return Item|null
     */
    public function getParentItem(): ?Item
    {
        return $this->_parentItem;
    }

    /**
     * Set parent item
     *
     * @param  Item $parentItem
     * @return $this
     */
    public function setParentItem($parentItem): static
    {
        if ($parentItem) {
            $this->_parentItem = $parentItem;
            $parentItem->addChild($this);
        }
        return $this;
    }

    /**
     * Add child item
     *
     * @param AbstractItem $child
     * @return $this
     */
    public function addChild($child)
    {
        $this->setHasChildren(true);
        $this->_children[] = $child;
        return $this;
    }

    /**
     * Removes message by text
     *
     * @param string $text
     * @return $this
     */
    public function removeMessageByText($text)
    {
        foreach ($this->_messages as $key => $message) {
            if ($message == $text) {
                unset($this->_messages[$key]);
            }
        }
        return $this;
    }

    /**
     * Checking item data
     *
     * @return $this
     */
    public function checkData()
    {
        $this->setHasError(false);
        $this->clearMessage();
        $qty = $this->_getData('qty');

        try {
            $this->setQty($qty);
        } catch (LocalizedException $e) {
            $this->setHasError(true);
            $this->setMessage($e->getMessage());
        } catch (Exception $e) {
            $this->setHasError(true);
            $this->setMessage(__('Item qty declaration error'));
        }

        try {
            $this->getProduct()->getTypeInstance()->checkProductBuyState($this->getProduct());
        } catch (LocalizedException $e) {
            $this->setHasError(true)->setMessage($e->getMessage());
            $this->getQuote()->setHasError(
                true
            )->addMessage(
                __('Some of the products below do not have all the required options.')
            );
        } catch (Exception $e) {
            $this->setHasError(true)->setMessage(__('Something went wrong during the item options declaration.'));
            $this->getQuote()->setHasError(true)->addMessage(__('We found an item options declaration error.'));
        }

        if ($this->getProduct()->getHasError()) {
            $this->setHasError(true)->setMessage(__('Some of the selected options are not currently available.'));
            $this->getQuote()->setHasError(true)->addMessage($this->getProduct()->getMessage(), 'options');
        }

        if ($this->getHasConfigurationUnavailableError()) {
            $this->setHasError(
                true
            )->setMessage(
                __('Selected option(s) or their combination is not currently available.')
            );
            $this->getQuote()->setHasError(
                true
            )->addMessage(
                __('Some item options or their combination are not currently available.'),
                'unavailable-configuration'
            );
            $this->unsHasConfigurationUnavailableError();
        }

        return $this;
    }

    /**
     * Clears all messages
     *
     * @return $this
     */
    public function clearMessage()
    {
        $this->unsMessage();
        // For older compatibility, when we kept message inside data array
        $this->_messages = [];
        return $this;
    }

    /**
     * Adds message(s) for quote item. Duplicated messages are not added.
     *
     * @param  mixed $messages
     * @return $this
     */
    public function setMessage($messages)
    {
        $messagesExists = $this->getMessage(false);
        if (!is_array($messages)) {
            $messages = [$messages];
        }
        foreach ($messages as $message) {
            if (!in_array($message, $messagesExists)) {
                $this->addMessage($message);
            }
        }
        return $this;
    }

    /**
     * Get messages array of quote item
     *
     * @param   bool $string flag for converting messages to string
     * @return  array|string
     */
    public function getMessage($string = true)
    {
        if ($string) {
            return join("\n", $this->_messages);
        }
        return $this->_messages;
    }

    /**
     * Add message of quote item to array of messages
     *
     * @param string $message
     * @return $this
     */
    public function addMessage($message)
    {
        $this->_messages[] = $message;
        return $this;
    }

    /**
     * Retrieve product model object associated with item
     *
     * @return Product
     */
    public function getProduct()
    {
        $product = $this->_getData('product');
        if ($product === null && $this->getProductId()) {
            $product = clone $this->productRepository->getById(
                $this->getProductId(),
                false,
                $this->getQuote()->getStoreId()
            );
            $this->setProduct($product);
        }

        /**
         * Reset product final price because it related to custom options
         */
        $product->setFinalPrice(null);
        if (is_array($this->_optionsByCode)) {
            $product->setCustomOptions($this->_optionsByCode);
        }
        return $product;
    }

    /**
     * Retrieve Quote instance
     *
     * @return Quote
     */
    abstract public function getQuote();

    /**
     * Calculate item row total price
     *
     * @return $this
     */
    public function calcRowTotal(): static
    {
        $qty = $this->getTotalQty();
        // Round unit price before multiplying to prevent losing 1 cent on subtotal
        $total = $this->priceCurrency->round($this->getCalculationPriceOriginal()) * $qty;
        $baseTotal = $this->priceCurrency->round($this->getBaseCalculationPriceOriginal()) * $qty;

        $this->setRowTotal($this->priceCurrency->round($total));
        $this->setBaseRowTotal($this->priceCurrency->round($baseTotal));
        return $this;
    }

    /**
     * Get total item quantity (include parent item relation)
     *
     * @return  int|float
     */
    public function getTotalQty()
    {
        if ($this->getParentItem()) {
            return $this->getQty() * $this->getParentItem()->getQty();
        }
        return $this->getQty();
    }

    /**
     * Get original (not related with parent item) item quantity
     *
     * @return  int|float
     */
    public function getQty()
    {
        return $this->_getData('qty');
    }

    /**
     * Get item price used for quote calculation process.
     *
     * This method get original custom price applied before tax calculation
     *
     * @return float
     */
    public function getCalculationPriceOriginal(): float
    {
        $price = $this->_getData('calculation_price');
        if ($price === null) {
            if ($this->hasOriginalCustomPrice()) {
                $price = $this->getOriginalCustomPrice();
            } else {
                $price = $this->getConvertedPrice();
            }
            $this->setData('calculation_price', $price);
        }
        return $price;
    }

    /**
     * Get item price converted to quote currency
     *
     * @return float
     */
    public function getConvertedPrice()
    {
        $price = $this->_getData('converted_price');
        if ($price === null) {
            $price = $this->priceCurrency->convert($this->getPrice(), $this->getStore());
            $this->setData('converted_price', $price);
        }
        return $price;
    }

    /**
     * Get item price. Item price currency is website base currency.
     *
     * @return float
     */
    public function getPrice()
    {
        return $this->_getData('price');
    }

    /**
     * Retrieve store model object
     *
     * @return Store
     */
    public function getStore()
    {
        return $this->getQuote()->getStore();
    }

    /**
     * Get original calculation price used for quote calculation in base currency.
     *
     * @return float
     */
    public function getBaseCalculationPriceOriginal(): float
    {
        if (!$this->hasBaseCalculationPrice()) {
            if ($this->hasOriginalCustomPrice()) {
                $price = (double)$this->getOriginalCustomPrice();
                if ($price) {
                    $rate = $this->priceCurrency->convert($price, $this->getStore()) / $price;
                    $price = $price / $rate;
                }
            } else {
                $price = $this->getPrice();
            }
            $this->setBaseCalculationPrice($price);
        }
        return $this->_getData('base_calculation_price');
    }

    /**
     * Get item price used for quote calculation process.
     *
     * This method get custom price (if it is defined) or original product final price
     *
     * @return float
     */
    public function getCalculationPrice(): float
    {
        $price = $this->_getData('calculation_price');
        if ($price === null) {
            if ($this->hasCustomPrice()) {
                $price = $this->getCustomPrice();
            } else {
                $price = $this->getConvertedPrice();
            }
            $this->setData('calculation_price', $price);
        }
        return $price;
    }

    /**
     * Get calculation price used for quote calculation in base currency.
     *
     * @return float
     */
    public function getBaseCalculationPrice(): float
    {
        if (!$this->hasBaseCalculationPrice()) {
            if ($this->hasCustomPrice()) {
                $price = (double)$this->getCustomPrice();
                if ($price) {
                    $rate = $this->priceCurrency->convert($price, $this->getStore()) / $price;
                    $price = $price / $rate;
                }
            } else {
                $price = $this->getPrice();
            }
            $this->setBaseCalculationPrice($price);
        }
        return $this->_getData('base_calculation_price');
    }

    /**
     * Get original price (retrieved from product) for item.
     *
     * Original price value is in quote selected currency
     *
     * @return float
     */
    public function getOriginalPrice(): float
    {
        $price = $this->_getData('original_price');
        if ($price === null) {
            $price = $this->priceCurrency->convert($this->getBaseOriginalPrice(), $this->getStore());
            $this->setData('original_price', $price);
        }
        return $price;
    }

    /**
     * Get Original item price (got from product) in base website currency
     *
     * @return float
     */
    public function getBaseOriginalPrice(): float
    {
        return $this->_getData('base_original_price');
    }

    /**
     * Set original price to item (calculation price will be refreshed too)
     *
     * @param float $price
     * @return AbstractItem
     */
    public function setOriginalPrice($price): AbstractItem
    {
        return $this->setData('original_price', $price);
    }

    /**
     * Specify custom item price (used in case when we have apply not product price to item)
     *
     * @param float $value
     * @return \Magento\Quote\Model\Quote\Item\AbstractItem
     */
    public function setCustomPrice($value)
    {
        $this->setCalculationPrice($value);
        $this->setBaseCalculationPrice(null);
        return $this->setData('custom_price', $value);
    }

    /**
     * Specify item price (base calculation price and converted price will be refreshed too)
     *
     * @param float $value
     * @return $this
     */
    public function setPrice($value)
    {
        $this->setBaseCalculationPrice(null);
        $this->setConvertedPrice(null);
        return $this->setData('price', $value);
    }

    /**
     * Set new value for converted price
     *
     * @param float $value
     * @return $this
     */
    public function setConvertedPrice($value)
    {
        $this->setCalculationPrice(null);
        $this->setData('converted_price', $value);
        return $this;
    }

    /**
     * Clone quote item
     *
     * @return $this
     */
    public function __clone()
    {
        $this->setId(null);
        $this->_parentItem = null;
        $this->_children = [];
        $this->_messages = [];
        return $this;
    }

    /**
     * Checking can we ship product separately
     *
     * Checking can we ship product separately (each child separately)
     * or each parent product item can be shipped only like one item
     *
     * @return bool
     */
    public function isShipSeparately()
    {
        if ($this->getParentItem()) {
            $shipmentType = $this->getParentItem()->getProduct()->getShipmentType();
        } else {
            $shipmentType = $this->getProduct()->getShipmentType();
        }

        if (null !== $shipmentType &&
            (int)$shipmentType === AbstractType::SHIPMENT_SEPARATELY
        ) {
            return true;
        }
        return false;
    }

    /**
     * Returns the total discount amounts of all the child items.
     *
     * If there are no children, returns the discount amount of this item.
     *
     * @return float
     */
    public function getTotalDiscountAmount()
    {
        $totalDiscountAmount = 0;
        /* \Magento\Quote\Model\Quote\Item\AbstractItem[] */
        $children = $this->getChildren();
        if (!empty($children) && $this->isChildrenCalculated()) {
            foreach ($children as $child) {
                $totalDiscountAmount += $child->getDiscountAmount();
            }
        } else {
            $totalDiscountAmount = $this->getDiscountAmount();
        }
        return $totalDiscountAmount;
    }

    /**
     * Get child items
     *
     * @return AbstractItem[]
     */
    public function getChildren()
    {
        return $this->_children;
    }

    /**
     * Checking if there children calculated or parent item when we have parent quote item and its children
     *
     * @return bool
     */
    public function isChildrenCalculated()
    {
        if ($this->getParentItem()) {
            $calculate = $this->getParentItem()->getProduct()->getPriceType();
        } else {
            $calculate = $this->getProduct()->getPriceType();
        }

        if (null !== $calculate &&
            (int)$calculate === AbstractType::CALCULATE_CHILD
        ) {
            return true;
        }
        return false;
    }
}
