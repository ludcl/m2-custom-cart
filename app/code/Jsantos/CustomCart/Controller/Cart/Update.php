<?php

declare(strict_types=1);

namespace Jsantos\CustomCart\Controller\Cart;

use Jsantos\CustomCart\Api\CustomcartItemRepositoryInterface;
use Jsantos\CustomCart\Api\CustomcartRepositoryInterface;
use Jsantos\CustomCart\Api\Data\CustomcartItemInterface;
use Jsantos\CustomCart\Api\Data\CustomcartItemInterfaceFactory;
use Jsantos\CustomCart\Model\Session;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Checkout\Model\Cart\RequestQuantityProcessor;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote\Item;
use Psr\Log\LoggerInterface;

/**
 * Ajax update shopping cart.
 */
class Update extends Action implements HttpPostActionInterface
{

    /**
     * @var RequestQuantityProcessor
     */
    private $quantityProcessor;

    /**
     * @var FormKeyValidator
     */
    private $formKeyValidator;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var Json
     */
    private $json;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var CartRepositoryInterface
     */
    private $quoteRepository;

    /**
     * UpdateAjax constructor.
     *
     * @param CustomcartItemInterfaceFactory $customcartItemFactory
     * @param CustomcartItemRepositoryInterface $customcartItemRepository
     * @param CustomcartRepositoryInterface $customcartRepository
     * @param ProductRepositoryInterface $productRepository
     * @param Session $customcartSession
     * @param Context $context
     * @param RequestQuantityProcessor $quantityProcessor
     * @param FormKeyValidator $formKeyValidator
     * @param CheckoutSession $checkoutSession
     * @param Json $json
     * @param LoggerInterface $logger
     * @param CartRepositoryInterface $quoteRepository
     */
    public function __construct(
        protected CustomcartItemInterfaceFactory $customcartItemFactory,
        protected CustomcartItemRepositoryInterface $customcartItemRepository,
        protected CustomcartRepositoryInterface $customcartRepository,
        protected ProductRepositoryInterface $productRepository,
        protected Session $customcartSession,
        Context $context,
        RequestQuantityProcessor $quantityProcessor,
        FormKeyValidator $formKeyValidator,
        CheckoutSession $checkoutSession,
        Json $json,
        LoggerInterface $logger,
        CartRepositoryInterface $quoteRepository
    ) {
        $this->quantityProcessor = $quantityProcessor;
        $this->formKeyValidator = $formKeyValidator;
        $this->checkoutSession = $checkoutSession;
        $this->json = $json;
        $this->logger = $logger;
        $this->quoteRepository = $quoteRepository;
        parent::__construct($context);
    }

    /**
     * Controller execute method
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @return void
     */
    public function execute(): void
    {
        try {
            $this->validateRequest();
            $this->validateFormKey();

            if ($this->getRequest()->getParam('product') !== null) {
                $product = $this->getRequest()->getParam('product');
                $qty = $this->getRequest()->getParam('qty');

                if ($product && $qty) {
                    $this->addProduct((int) $product, (int) $qty);
                }
                $customcart = $this->customcartSession->getCustomcart();
                $customcart->collectTotals();
                $this->customcartRepository->save($customcart);
                $this->jsonResponse();

                return;
            }

            $cartData = $this->getRequest()->getParam('cart');

            $this->validateCartData($cartData);

            $cartData = $this->quantityProcessor->process($cartData);
            $customcart = $this->customcartSession->getCustomcart();

            foreach ($cartData as $itemId => $itemInfo) {
                if ($itemId=='empty_cart') {
                    continue;
                }
                $item = $customcart->getItemById($itemId);
                $qty = isset($itemInfo['qty']) ? (double) $itemInfo['qty'] : 0;

                if ($item) {
                    if (!empty($itemInfo['remove'])
                        || isset($itemInfo['qty']) && $itemInfo['qty'] == '0' && $itemInfo['qty'] !== ''
                    ) {
                        $customcart->removeItem($itemId);
                        continue;
                    }
                    $this->updateItemQuantity($item, $qty);
                }
            }
            if (isset($cartData['empty_cart']) && $cartData['empty_cart']) {
                $customcart->removeAllItems();
            }
            $customcart->collectTotals();
            $this->customcartRepository->save($customcart);

            $this->jsonResponse();
        } catch (LocalizedException $e) {
            $this->jsonResponse($e->getMessage());
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage());
            $this->jsonResponse(
                'Something went wrong while saving the page. Please refresh the page and try again.'
            );
        }
    }

    /**
     * Updates quote item quantity.
     *
     * @param CustomcartItemInterface $item
     * @param float $qty
     * @return void
     * @throws LocalizedException
     */
    private function updateItemQuantity(CustomcartItemInterface $item, float $qty): void
    {
        if ($qty > 0) {
            $this->customcartItemRepository->save(
                $item->setQty($qty)->setRowSubtotal($qty * $item->getPrice())
            );
            $this->customcartRepository->save(
                $this->customcartSession->getCustomcart()->collectTotals()
            );
        }
    }

    /**
     * JSON response builder.
     *
     * @param string $error
     * @return void
     */
    private function jsonResponse(string $error = ''): void
    {
        $this->getResponse()->representJson(
            $this->json->serialize($this->getResponseData($error))
        );
    }

    /**
     * Returns response data.
     *
     * @param string $error
     * @return array
     */
    private function getResponseData(string $error = ''): array
    {
        $response = ['success' => true];

        if (!empty($error)) {
            $response = [
                'success' => false,
                'error_message' => $error,
            ];
        }

        return $response;
    }

    /**
     * Validates the Request HTTP method
     *
     * @return void
     * @throws NotFoundException
     */
    private function validateRequest(): void
    {
        if ($this->getRequest()->isPost() === false) {
            throw new NotFoundException(__('Page Not Found'));
        }
    }

    /**
     * Validates form key
     *
     * @return void
     * @throws LocalizedException
     */
    private function validateFormKey(): void
    {
        if (!$this->formKeyValidator->validate($this->getRequest())) {
            throw new LocalizedException(
                __('Something went wrong while saving the page. Please refresh the page and try again.')
            );
        }
    }

    /**
     * Validates cart data
     *
     * @param array|null $cartData
     * @return void
     * @throws LocalizedException
     */
    private function validateCartData(array $cartData = null): void
    {
        if (!is_array($cartData)) {
            throw new LocalizedException(
                __('Something went wrong while saving the page. Please refresh the page and try again.')
            );
        }
    }

    /**
     * Add product to Custom Cart as a new Item
     *
     * @param int $productId
     * @param int $qty
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function addProduct(int $productId, int $qty): void
    {
        $customcart = $this->customcartSession->getCustomcart();
        $cartId = $customcart->getEntityId();

        if (!$cartId) {
            if ($this->checkoutSession->getQuote()?->getCustomerId()) {
                $customcart->setCustomerId($this->checkoutSession->getQuote()->getCustomerId());
            }
            $customcart->setItemsQty(0);
            $customcart->setSubtotal(0);
            $customcart = $this->customcartRepository->save($customcart);
            $cartId = $customcart->getEntityId();
            $this->customcartSession->setCustomcartId((int) $cartId);
        }

        $item = $customcart->getItemByProductId($productId);

        if ($item) {
            $newQty = $item->getQty() + $qty;
            $this->updateItemQuantity($item, $newQty);
        } else {
            $product = $this->productRepository->getById($productId);
            $item = $this->customcartItemFactory->create();
            $item->setCustomcartId($cartId);
            $item->setProductId($productId);
            $item->setSku($product->getSku());
            $item->setName($product->getName());
            $item->setQty($qty);
            $item->setPrice($product->getPrice());
            $item->setRowSubtotal($product->getPrice() * $qty);
        }

        $customcart->addItem($item)->collectTotals();
        $this->customcartRepository->save($customcart);
    }
}
