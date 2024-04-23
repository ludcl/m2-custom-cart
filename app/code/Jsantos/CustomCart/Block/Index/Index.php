<?php

declare(strict_types=1);

namespace Jsantos\CustomCart\Block\Index;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Block\Product\View;
use Magento\Catalog\Helper\Image;
use Magento\Catalog\Helper\Product as ProductHelper;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ProductTypes\ConfigInterface;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Customer\Model\Session;
use Magento\Framework\Json\EncoderInterface;
use Magento\Framework\Locale\FormatInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Stdlib\StringUtils;
use Magento\Framework\Url\EncoderInterface as UrlEncoderInterface;
use Magento\Catalog\Block\Product\Context;

class Index extends View
{

    /**
     * @param CollectionFactory $collectionFactory
     * @param Image $imageHelper
     * @param Context $context
     * @param UrlEncoderInterface $urlEncoder
     * @param EncoderInterface $jsonEncoder
     * @param StringUtils $string
     * @param ProductHelper $productHelper
     * @param ConfigInterface $productTypeConfig
     * @param FormatInterface $localeFormat
     * @param Session $customerSession
     * @param ProductRepositoryInterface $productRepository
     * @param PriceCurrencyInterface $priceCurrency
     * @param array $data
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        protected CollectionFactory $collectionFactory,
        protected Image $imageHelper,
        Context $context,
        UrlEncoderInterface $urlEncoder,
        EncoderInterface $jsonEncoder,
        StringUtils $string,
        ProductHelper $productHelper,
        ConfigInterface $productTypeConfig,
        FormatInterface $localeFormat,
        Session $customerSession,
        ProductRepositoryInterface $productRepository,
        PriceCurrencyInterface $priceCurrency,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $urlEncoder,
            $jsonEncoder,
            $string,
            $productHelper,
            $productTypeConfig,
            $localeFormat,
            $customerSession,
            $productRepository,
            $priceCurrency,
            $data
        );
    }

    /**
     * Get Product collection of saleable and visible simple products
     *
     * @return Collection
     */
    public function getProductList(): Collection
    {
        $productCollection = $this->collectionFactory->create();
        $productCollection->addAttributeToFilter('type_id', 'simple')
            ->addAttributeToFilter('visibility', Visibility::VISIBILITY_BOTH)
            ->addStoreFilter()
            ->addAttributeToSelect(['name', 'price', 'image', 'product_url'])
            ->setPageSize(10)
            ->setCurPage(1);

        return $productCollection;
    }

    /**
     * Get Product Image URL
     *
     * @param Product $product
     * @return string
     */
    public function getProductImage(Product $product): string
    {
        return $this->imageHelper->init($product, 'product_base_image')->getUrl();
    }

    public function getToCustomCartUrl(){
        return $this->getUrl('customcart/index/add');
    }
}
