<?php

namespace JustBetter\ProductGridExport\Helper;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\InventoryApi\Api\Data\SourceInterface;
use Magento\InventoryApi\Api\GetSourceItemsBySkuInterface;
use Magento\InventoryApi\Api\SourceRepositoryInterface;

class QuantityPerSource
{
    private array $sourcesBySourceCodes = [];

    public function __construct(
        protected GetSourceItemsBySkuInterface $getSourceItemsBySku,
        protected SearchCriteriaBuilder $searchCriteriaBuilder,
        protected SourceRepositoryInterface $sourceRepository
    ) {
    }

    public function execute(string $sku): ?array {
        return $this->getQuantityPerSourceItemString($sku);
    }

    private function getQuantityPerSourceItemString(string $sku): ?array
    {
        $data = $this->getQuantityPerSourceItemData($sku);

        if (!$data) {
            return null;
        }

        $result = [];
        foreach ($data as $index => $item) {
            $result[] = $item['source_name'] . ': ' . (int)$item['qty'];
        }

        return $result;
    }

    private function getQuantityPerSourceItemData(string $sku): array
    {
        $sourceItems = $this->getSourceItemsBySku->execute($sku);
        $sourcesBySourceCode = $this->getSourcesBySourceItems($sourceItems);

        $itemData = [];
        foreach ($sourceItems as $sourceItem) {
            $source = $sourcesBySourceCode[$sourceItem->getSourceCode()];
            $itemData[] = [
                'source_name' => $source->getName(),
                'source_code' => $sourceItem->getSourceCode(),
                'qty' => (float) $sourceItem->getQuantity(),
            ];
        }

        return $itemData;
    }

    private function getSourcesBySourceItems(array $sourceItems): array
    {
        $newSourceCodes = $sourcesBySourceCodes = [];

        foreach ($sourceItems as $sourceItem) {
            $sourceCode = $sourceItem->getSourceCode();
            if (isset($this->sourcesBySourceCodes[$sourceCode])) {
                $sourcesBySourceCodes[$sourceCode] = $this->sourcesBySourceCodes[$sourceCode];
            } else {
                $newSourceCodes[] = $sourceCode;
            }
        }

        if ($newSourceCodes) {
            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter(SourceInterface::SOURCE_CODE, $newSourceCodes, 'in')
                ->create();
            $newSources = $this->sourceRepository->getList($searchCriteria)->getItems();

            foreach ($newSources as $source) {
                $this->sourcesBySourceCodes[$source->getSourceCode()] = $source;
                $sourcesBySourceCodes[$source->getSourceCode()] = $source;
            }
        }

        return $sourcesBySourceCodes;
    }
}
