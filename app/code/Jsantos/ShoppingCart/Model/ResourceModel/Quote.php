<?php

declare(strict_types=1);

namespace Jsantos\ShoppingCart\Model\ResourceModel;

class Quote extends \Magento\Quote\Model\ResourceModel\Quote
{
    /** @var string Main table name */
    protected const string MAIN_TABLE = 'custom_quote';

    /** @var string Main table primary key field name */
    protected const string ID_FIELD_NAME = 'entity_id';

    /**
     * @inheritdoc
     */
    protected function _construct(): void
    {
        $this->_init(self::MAIN_TABLE, self::ID_FIELD_NAME);
    }
}
