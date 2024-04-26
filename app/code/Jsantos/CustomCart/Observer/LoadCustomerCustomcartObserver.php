<?php

declare(strict_types=1);

namespace Jsantos\CustomCart\Observer;

use Exception;
use Jsantos\CustomCart\Model\Session;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;

/**
 * @SuppressWarnings(PHPMD.CookieAndSessionMisuse)
 */
class LoadCustomerCustomcartObserver implements ObserverInterface
{
    /**
     * @param Session $customcartSession
     * @param ManagerInterface $messageManager
     * @codeCoverageIgnore
     */
    public function __construct(
        protected Session $customcartSession,
        protected ManagerInterface $messageManager
    ) {
    }

    /**
     * Load customer customcart in Session on customer login
     *
     * @param Observer $observer
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @SuppressWarnings(PHPMD.UnusedExpression)
     */
    public function execute(Observer $observer): void
    {
        try {
            $this->customcartSession->loadCustomerCustomcart();
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (Exception $e) {
            $this->messageManager->addExceptionMessage($e, __('Load customer customcart error'));
        }
    }
}
