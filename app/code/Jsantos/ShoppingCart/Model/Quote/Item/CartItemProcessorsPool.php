<?php

declare(strict_types=1);

namespace Jsantos\ShoppingCart\Model\Quote\Item;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\ObjectManager\ConfigInterface;
use Magento\Framework\ObjectManager\ResetAfterRequestInterface;

/**
 * @deprecated 100.1.0
 * @see Nothing
 */
class CartItemProcessorsPool implements ResetAfterRequestInterface
{
    /**
     * @var CartItemProcessorInterface[]
     */
    private $cartItemProcessors = [];

    /**
     * @var ConfigInterface
     */
    private $objectManagerConfig;

    /**
     * @param ConfigInterface $objectManagerConfig
     * @deprecated 100.1.0
     * @see Nothing
     */
    public function __construct(ConfigInterface $objectManagerConfig)
    {
        $this->objectManagerConfig = $objectManagerConfig;
    }

    /**
     * Get cart item processors.
     *
     * @return CartItemProcessorInterface[]
     * @deprecated 100.1.0
     * @see Nothing
     */
    public function getCartItemProcessors(): array
    {
        if (!empty($this->cartItemProcessors)) {
            return $this->cartItemProcessors;
        }

        $typePreference = $this->objectManagerConfig->getPreference(Repository::class);
        $arguments = $this->objectManagerConfig->getArguments($typePreference);
        if (isset($arguments['cartItemProcessors'])) {
            // Workaround for compiled mode.
            $processors = $arguments['cartItemProcessors']['_vac_'] ?? $arguments['cartItemProcessors'];
            foreach ($processors as $name => $processor) {
                $className = $processor['instance'] ?? $processor['_i_'];
                $this->cartItemProcessors[$name] = ObjectManager::getInstance()->get($className);
            }
        }

        return $this->cartItemProcessors;
    }

    /**
     * @inheritDoc
     */
    public function _resetState(): void
    {
        $this->cartItemProcessors = [];
    }
}
