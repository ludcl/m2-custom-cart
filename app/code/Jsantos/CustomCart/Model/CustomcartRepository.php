<?php

declare(strict_types=1);

namespace Jsantos\CustomCart\Model;

use Jsantos\CustomCart\Api\CustomcartRepositoryInterface;
use Jsantos\CustomCart\Api\Data\CustomcartInterface;
use Jsantos\CustomCart\Model\ResourceModel\Customcart as CustomcartResourceModel;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

class CustomcartRepository implements CustomcartRepositoryInterface
{

    /**
     * @param CustomcartFactory $customcartFactory
     * @param CustomcartResourceModel $resourceModel
     */
    public function __construct(
        private CustomcartFactory $customcartFactory,
        private CustomcartResourceModel $resourceModel
    ) {
    }

    /**
     * @inheritDoc
     */
    public function save(CustomcartInterface $customcart): CustomcartInterface
    {
        try {
            $this->resourceModel->save($customcart);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(__('Could not save the custom cart.'));
        }

        return $customcart;
    }

    /**
     * @inheritDoc
     */
    public function getById($id): CustomcartInterface
    {
        $customcart = $this->customcartFactory->create();
        $this->resourceModel->load($customcart, $id);

        if (!$customcart->getId()) {
            throw new NoSuchEntityException(__('Custom cart with id "%1" does not exist.', $id));
        }

        return $customcart;
    }

    /**
     * @inheritDoc
     */
    public function delete(CustomcartInterface $customcart): bool
    {
        try {
            $this->resourceModel->delete($customcart);
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(__('Could not delete the custom cart.'));
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function deleteById($id): bool
    {
        $customcart = $this->getById($id);
        $this->delete($customcart);

        return true;
    }

    /**
     * @inheritDoc
     */
    public function getByCustomerId(int $customerId): CustomcartInterface
    {
        $customcart = $this->customcartFactory->create();
        $this->resourceModel->load($customcart, $customerId, CustomcartInterface::CUSTOMER_ID);

        if (!$customcart->getId()) {
            throw new NoSuchEntityException(__('Custom cart with customer_id "%1" does not exist.', $customerId));
        }

        return $customcart;
    }
}
