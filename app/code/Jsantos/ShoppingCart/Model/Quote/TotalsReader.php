<?php

declare(strict_types=1);

namespace Jsantos\ShoppingCart\Model\Quote;

use Jsantos\ShoppingCart\Model\Quote;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Model\Quote\Address\Total\ReaderInterface;
use Magento\Quote\Model\Quote\Address\TotalFactory;
use Magento\Quote\Model\Quote\TotalsCollectorList;

class TotalsReader
{
    /**
     * @param TotalFactory $totalFactory
     * @param TotalsCollectorList $collectorList
     */
    public function __construct(
        protected TotalFactory $totalFactory,
        protected TotalsCollectorList $collectorList
    ) {
    }

    /**
     * Fetch totals
     *
     * @param Quote $quote
     * @param array $total
     * @return Total[]
     */
    public function fetch(Quote $quote, array $total): array
    {
        $output = [];
        $total = $this->totalFactory->create()->setData($total);
        /** @var ReaderInterface $reader */
        foreach ($this->collectorList->getCollectors($quote->getStoreId()) as $reader) {
            $data = $reader->fetch($quote, $total);
            if ($data === null || empty($data)) {
                continue;
            }

            $totalInstance = $this->convert($data);
            if (is_array($totalInstance)) {
                foreach ($totalInstance as $item) {
                    $output = $this->merge($item, $output);
                }
            } else {
                $output = $this->merge($totalInstance, $output);
            }
        }
        return $output;
    }

    /**
     * Convert Total
     *
     * @param Total|array $total
     * @return Total|Total[]
     */
    protected function convert(Total|array $total): array|Total
    {
        if ($total instanceof Total) {
            return $total;
        }

        if (count(array_column($total, 'code')) > 0) {
            $totals = [];
            foreach ($total as $item) {
                $totals[] = $this->totalFactory->create()->setData($item);
            }
            return $totals;
        }

        return $this->totalFactory->create()->setData($total);
    }

    /**
     * Merge totals
     *
     * @param Total $totalInstance
     * @param Total[] $output
     * @return Total[]
     */
    protected function merge(Total $totalInstance, array $output): array
    {
        if (array_key_exists($totalInstance->getCode(), $output)) {
            $output[$totalInstance->getCode()] = $output[$totalInstance->getCode()]->addData(
                $totalInstance->getData()
            );
        } else {
            $output[$totalInstance->getCode()] = $totalInstance;
        }
        return $output;
    }
}
