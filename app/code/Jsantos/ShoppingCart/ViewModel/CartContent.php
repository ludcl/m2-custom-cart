<?php

declare(strict_types=1);

namespace Jsantos\ShoppingCart\ViewModel;

use Magento\Framework\Phrase;
use Magento\Framework\View\Element\Block\ArgumentInterface;

class CartContent implements ArgumentInterface
{

    /**
     * To be written later...
     *
     * @return Phrase
     */
    public function getMessage(): Phrase
    {
        return __('Pending Cart Content...');
    }
}
