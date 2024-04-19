<?php

declare(strict_types=1);

namespace Jsantos\ShoppingCart\Controller\Cart;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Psr\Log\LoggerInterface;

class Checkout implements HttpPostActionInterface
{
    /**
     * Constructor
     *
     * @param JsonFactory $jsonFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        protected JsonFactory $jsonFactory,
        protected LoggerInterface $logger,
    ) {
    }

    /**
     * Execute view action
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $resultJson = $this->jsonFactory->create();
        $data = [
            'message' => 'Pending content...',
            'success' => true
        ];
        return $resultJson->setData($data);
    }
}
