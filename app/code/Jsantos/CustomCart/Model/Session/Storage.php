<?php

declare(strict_types=1);

namespace Jsantos\CustomCart\Model\Session;

use Magento\Framework\Session\Storage as MagentoSessionStorage;

class Storage extends MagentoSessionStorage
{
    /**
     * @param string $namespace
     * @param array $data
     * phpcs:disable Generic.CodeAnalysis.UselessOverridingMethod.Found
     */
    public function __construct(
        $namespace = 'customcart',
        array $data = []
    ) {
        parent::__construct($namespace, $data);
    }
}
