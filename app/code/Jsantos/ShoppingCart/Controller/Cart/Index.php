<?php

declare(strict_types=1);

namespace Jsantos\ShoppingCart\Controller\Cart;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\PageFactory;

class Index implements HttpGetActionInterface
{
    /**
     * Constructor
     *
     * @param PageFactory $resultPageFactory
     */
    public function __construct(
        protected PageFactory $resultPageFactory
    ) {
    }

    /**
     * Execute view action
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        return $this->resultPageFactory->create();
    }
}
