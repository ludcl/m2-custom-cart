<?php

declare(strict_types=1);

namespace Jsantos\CustomCart\Model;

use Jsantos\CustomCart\Api\CustomcartRepositoryInterface;
use Jsantos\CustomCart\Api\Data\CustomcartInterface;
use Jsantos\CustomCart\Api\Data\CustomcartSearchResultsInterface;
use Magento\Framework\Api\SearchCriteriaInterface;

class CustomcartRepository implements CustomcartRepositoryInterface
{

    /**
     * @inheritDoc
     */
    public function save(CustomcartInterface $customcart): CustomcartInterface
    {
        // TODO: Implement save() method.
    }

    /**
     * @inheritDoc
     */
    public function getById($id): CustomcartInterface
    {
        // TODO: Implement getById() method.
    }

    /**
     * @inheritDoc
     */
    public function getList(SearchCriteriaInterface $searchCriteria): CustomcartSearchResultsInterface
    {
        // TODO: Implement getList() method.
    }

    /**
     * @inheritDoc
     */
    public function delete(CustomcartInterface $customcart): bool
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
