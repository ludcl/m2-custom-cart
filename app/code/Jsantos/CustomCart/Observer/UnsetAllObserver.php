<?php

declare(strict_types=1);

namespace Jsantos\CustomCart\Observer;

use Jsantos\CustomCart\Model\Session;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * @SuppressWarnings(PHPMD.CookieAndSessionMisuse)
 */
class UnsetAllObserver implements ObserverInterface
{
    /**
     * @param Session $customcartSession
     * @codeCoverageIgnore
     */
    public function __construct(
        protected Session $customcartSession
    ) {
    }

    /**
     * Clear customcart session on customer log-out
     *
     * @param Observer $observer
     * @return void
     * @codeCoverageIgnore
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function execute(Observer $observer): void
    {
        $this->customcartSession->clearCustomcart()->clearStorage();
    }
}
