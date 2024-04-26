<?php

declare(strict_types=1);

namespace Jsantos\CustomCart\Model;

use Jsantos\CustomCart\Api\CustomcartItemRepositoryInterface;
use Jsantos\CustomCart\Api\Data\CustomcartItemInterface;
use Jsantos\CustomCart\Model\ResourceModel\CustomcartItem as CustomcartItemResourceModel;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

class CustomcartItemRepository implements CustomcartItemRepositoryInterface
{
    /**
     * @param CustomcartItemFactory $customcartItemFactory
     * @param CustomcartItemResourceModel $resourceModel
     */
    public function __construct(
        private CustomcartItemFactory $customcartItemFactory,
        private CustomcartItemResourceModel $resourceModel
    ) {
    }

    /**
     * @inheritDoc
     */
    public function save(CustomcartItemInterface $customcartItem): CustomcartItemInterface
    {
        try {
            $this->resourceModel->save($customcartItem);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(__('Could not save the custom cart item.'));
        }

        return $customcartItem;
    }

    /**
     * @inheritDoc
     */
    public function getById($id): CustomcartItemInterface
    {
        $customcartItem = $this->customcartItemFactory->create();
        $this->resourceModel->load($customcartItem, $id);

        if (!$customcartItem->getId()) {
            throw new NoSuchEntityException(__('Custom cart item with id "%1" does not exist.', $id));
        }

        return $customcartItem;
    }

    /**
     * @inheritDoc
     */
    public function delete(CustomcartItemInterface $customcartItem): bool
    {
        try {
            $this->resourceModel->delete($customcartItem);
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(__('Could not delete the custom cart item.'));
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function deleteById($id): bool
    {
        $customcartItem = $this->getById($id);
        $this->delete($customcartItem);

        return true;
    }
}
