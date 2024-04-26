<?php

declare(strict_types=1);

namespace Jsantos\CustomCart\Model;

use Jsantos\CustomCart\Api\CustomcartRepositoryInterface;
use Jsantos\CustomCart\Api\Data\CustomcartInterface;
use LogicException;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\SessionException;
use Magento\Framework\Session\Config\ConfigInterface;
use Magento\Framework\Session\SaveHandlerInterface;
use Magento\Framework\Session\SessionManager;
use Magento\Framework\Session\SessionStartChecker;
use Magento\Framework\Session\SidResolverInterface;
use Magento\Framework\Session\StorageInterface;
use Magento\Framework\Session\ValidatorInterface;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\CookieManagerInterface;

/**
 * Represents the session data for the custom cart
 *
 * @SuppressWarnings(PHPMD.CookieAndSessionMisuse)
 */
class Session extends SessionManager
{
    private const string CUSTOMCART_ID = 'customcart_id';

    /**
     * Customcart instance
     *
     * @var CustomcartInterface|null
     */
    protected ?CustomcartInterface $customcart = null;

    /**
     * Customer Data Object
     *
     * @var CustomerInterface|null
     */
    protected ?CustomerInterface $customer;

    /**
     * @var bool
     */
    private bool $isLoading;

    /**
     * @param CustomcartRepositoryInterface $customcartRepository
     * @param CustomerSession $customerSession
     * @param CustomcartFactory $customcartFactory
     * @param Http $request
     * @param SidResolverInterface $sidResolver
     * @param ConfigInterface $sessionConfig
     * @param SaveHandlerInterface $saveHandler
     * @param ValidatorInterface $validator
     * @param StorageInterface $storage
     * @param CookieManagerInterface $cookieManager
     * @param CookieMetadataFactory $cookieMetadataFactory
     * @param State $appState
     * @param SessionStartChecker|null $sessionStartChecker
     * @throws SessionException
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        private CustomcartRepositoryInterface $customcartRepository,
        private CustomerSession $customerSession,
        private CustomcartFactory $customcartFactory,
        Http $request,
        SidResolverInterface $sidResolver,
        ConfigInterface $sessionConfig,
        SaveHandlerInterface $saveHandler,
        ValidatorInterface $validator,
        StorageInterface $storage,
        CookieManagerInterface $cookieManager,
        CookieMetadataFactory $cookieMetadataFactory,
        State $appState,
        SessionStartChecker $sessionStartChecker = null
    ) {
        parent::__construct(
            $request,
            $sidResolver,
            $sessionConfig,
            $saveHandler,
            $validator,
            $storage,
            $cookieManager,
            $cookieMetadataFactory,
            $appState,
            $sessionStartChecker
        );
    }

    /**
     * Returns custom cart entity
     *
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function getCustomcart(): ?CustomcartInterface
    {
        if ($this->customcart === null) {
            if ($this->isLoading) {
                throw new LogicException("Infinite loop detected, review the trace for the looping path");
            }
            $this->isLoading = true;
            $customcart = $this->customcartFactory->create();

            if ($this->getCustomcartId()) {
                try {
                    $customcart = $this->customcartRepository->getById($this->getCustomcartId());
                    $this->customcart = $customcart;

                    $customerId = $this->customer
                        ? $this->customer->getId()
                        : $this->customerSession->getCustomerId();

                    /**
                     * Check if the customer ID associated with the current customcart object
                     * is different from the current session's customer ID. If so,
                     * create a new customcart object and reset the customcart ID to null.
                     * This ensures the session is linked with the correct customcart.
                     */
                    if ($customcart->getData(CustomcartInterface::CUSTOMER_ID)
                        && (int)$customcart->getData(CustomcartInterface::CUSTOMER_ID) !== (int)$customerId
                    ) {
                        $customcart = $this->customcartFactory->create();
                        $this->setCustomcartId(null);
                    }

                } catch (NoSuchEntityException $e) {
                    $this->setCustomcartId(null);
                }
            }

            if (!$this->getCustomcartId()) {
                if ($this->customerSession->isLoggedIn() || $this->customer) {
                    $customcartByCustomer = $this->getCustomcartByCustomer();

                    if ($customcartByCustomer !== null) {
                        $this->setCustomcartId($customcartByCustomer->getId());
                        $customcart = $customcartByCustomer;
                    }
                }
            }

            if ($this->customer) {
                $customcart->setCustomerId((int)$this->customer->getId());
            } elseif ($this->customerSession->isLoggedIn()) {
                $customcart->setCustomerId($this->customerSession->getCustomerId());
            }

            $this->customcart = $customcart;
            $this->isLoading = false;
        }

        return $this->customcart;
    }

    /**
     * Return the current customcart's ID
     *
     * @return int
     * @codeCoverageIgnore
     */
    public function getCustomcartId(): int
    {
        return (int) $this->getData(self::CUSTOMCART_ID);
    }

    /**
     * Set the current session's customcart id
     *
     * @param int|null $customcartId
     * @return void
     * @codeCoverageIgnore
     */
    public function setCustomcartId(?int $customcartId): void
    {
        $this->storage->setData(self::CUSTOMCART_ID, $customcartId);
    }

    /**
     * Set customer data (To be used in tests)
     *
     * @param CustomerInterface|null $customer
     * @return Session
     * @codeCoverageIgnore
     */
    public function setCustomerData(?CustomerInterface $customer): static
    {
        $this->customer = $customer;
        return $this;
    }

    /**
     * Get customcart for logged-in users.
     *
     * @return CustomcartInterface|null
     */
    public function getCustomcartByCustomer(): ?CustomcartInterface
    {
        $customerId = $this->customer
            ? $this->customer->getId()
            : $this->customerSession->getCustomerId();

        try {
            $customcart = $this->customcartRepository->getByCustomerId($customerId);
        } catch (NoSuchEntityException|LocalizedException $e) {
            $customcart = null;
        }

        return $customcart;
    }

    /**
     * Load data for customer quote and merge with current quote
     *
     * @return $this
     * @throws LocalizedException
     */
    public function loadCustomerCustomcart(): static
    {
        if (!$this->customerSession->getCustomerId()) {
            return $this;
        }

        try {
            $customerCustomcart = $this->customcartRepository->getByCustomerId($this->customerSession->getCustomerId());
        } catch (LocalizedException|NoSuchEntityException $e) {
            $customerCustomcart = $this->customcartFactory->create();
        }

        if ($customerCustomcart->getEntityId()
            && $this->getCustomcartId() != $customerCustomcart->getEntityId()
        ) {
            if ($this->getCustomcartId()) {
                $customcart = $this->getCustomcart();
                $this->customcartRepository->save(
                    $customerCustomcart->merge($customcart)->collectTotals()
                );
                $newCustomcart = $this->customcartRepository->getById($customerCustomcart->getEntityId());
                $customerCustomcart = $newCustomcart;
            }

            $this->setCustomcartId($customerCustomcart->getId());

            if ($this->customcart) {
                $this->customcartRepository->delete($this->customcart);
            }

            $this->customcart = $customerCustomcart;
        } else {
            $this->getCustomcart()->setCustomerId($this->customerSession->getCustomerId())->collectTotals();
            $this->customcartRepository->save($this->getCustomcart());
        }

        return $this;
    }

    /**
     * Destroy/end a session and unset all data associated with it
     *
     * @return $this
     */
    public function clearCustomcart(): static
    {
        $this->customcart = null;
        $this->setCustomcartId(null);
        return $this;
    }

    /**
     * Unset all session data and quote
     *
     * @return $this
     */
    public function clearStorage(): static
    {
        parent::clearStorage();
        $this->customcart = null;
        return $this;
    }
}
