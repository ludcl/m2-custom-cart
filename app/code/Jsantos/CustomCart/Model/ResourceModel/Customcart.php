<?php

declare(strict_types=1);

namespace Jsantos\CustomCart\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Customcart extends AbstractDb
{
    /** @var string Main table name */
    protected const string MAIN_TABLE = 'customcart';

    /** @var string Main table primary key field name */
    protected const string ID_FIELD_NAME = 'entity_id';

    /**
     * Customcart Resource Model constructor
     *
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(self::MAIN_TABLE, self::ID_FIELD_NAME);
    }
}
