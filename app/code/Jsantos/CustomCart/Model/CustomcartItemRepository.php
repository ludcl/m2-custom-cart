<?php

declare(strict_types=1);

namespace Jsantos\CustomCart\Model;

use Jsantos\CustomCart\Api\CustomcartItemRepositoryInterface;
use Jsantos\CustomCart\Api\Data\CustomcartItemInterface;
use Jsantos\CustomCart\Api\Data\CustomcartItemSearchResultsInterface;
use Magento\Framework\Api\SearchCriteriaInterface;

class CustomcartItemRepository implements CustomcartItemRepositoryInterface
{

    /**
     * @inheritDoc
     */
    public function save(CustomcartItemInterface $customcartItem): CustomcartItemInterface
    {
        // TODO: Implement save() method.
    }

    /**
     * @inheritDoc
     */
    public function getById($id): CustomcartItemInterface
    {
        // TODO: Implement getById() method.
    }

    /**
     * @inheritDoc
     */
    public function getList(SearchCriteriaInterface $searchCriteria): CustomcartItemSearchResultsInterface
    {
        // TODO: Implement getList() method.
    }

    /**
     * @inheritDoc
     */
    public function delete(CustomcartItemInterface $customcartItem): bool
    {
        // TODO: Implement delete() method.
    }

    /**
     * @inheritDoc
     */
    public function deleteById($id): bool
    {
        // TODO: Implement deleteById() method.
    }
}
