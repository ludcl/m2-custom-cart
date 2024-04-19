<?php

declare(strict_types=1);

namespace Jsantos\ShoppingCart\CustomerData;

use Magento\Customer\CustomerData\SectionSourceInterface;
use Psr\Log\LoggerInterface;

class Customcart implements SectionSourceInterface
{
    /**
     * Constructor
     *
     * @param LoggerInterface $logger
     */
    public function __construct(
        protected LoggerInterface $logger
    ) {
    }

    /**
     * @inheritdoc
     */
    public function getSectionData(): array
    {
        return [];
    }
}
