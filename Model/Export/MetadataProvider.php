<?php

namespace JustBetter\ProductGridExport\Model\Export;

use Exception;
use JustBetter\ProductGridExport\Helper\QuantityPerSource;
use Magento\Catalog\Model\Product;
use Magento\Framework\Api\Search\DocumentInterface;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\View\Element\UiComponentInterface;
use Magento\Ui\Component\Listing;
use Magento\Ui\Component\MassAction\Filter;
use Magento\Ui\Model\BookmarkManagement;
use Magento\Ui\Model\Export\MetadataProvider as MagentoMetadataProvider;

class MetadataProvider extends MagentoMetadataProvider
{
    public function __construct(
        Filter $filter,
        TimezoneInterface $localeDate,
        ResolverInterface $localeResolver,
        protected BookmarkManagement $bookmarkManagement,
        protected QuantityPerSource $quantityPerSource,
        $dateFormat = 'M j, Y H:i:s A',
        array $data = [])
    {
        parent::__construct(
            $filter,
            $localeDate,
            $localeResolver,
            $dateFormat,
            $data
        );
    }

    protected function getActiveColumns(Listing $component): array
    {
        $bookmark = $this->bookmarkManagement->getByIdentifierNamespace('current', $component->getName());

        $config = $bookmark->getConfig();
        // Remove all invisible columns as well as ids, and actions columns.
        $columns = array_filter($config['current']['columns'], fn($config, $key) => $config['visible'] && !in_array($key, ['ids', 'actions']), ARRAY_FILTER_USE_BOTH);
        // Sort by position in grid.
        uksort($columns, fn($a, $b) => $config['current']['positions'][$a] <=> $config['current']['positions'][$b]);

        return array_keys($columns);
    }

    /**
     * @return UiComponentInterface[]
     * @throws Exception
     */
    protected function getColumns(UiComponentInterface $component): array
    {
        if (!isset($this->columns[$component->getName()])) {

            $activeColumns = $this->getActiveColumns($component);

            $columns = $this->getColumnsComponent($component);
            $components = $columns->getChildComponents();

            foreach ($activeColumns as $columnName) {
                $column = $components[$columnName] ?? null;

                if (isset($column) && $column->getData('config/label') && $column->getData('config/dataType') !== 'actions') {
                    $this->columns[$component->getName()][$column->getName()] = $column;
                }
            }
        }

        return $this->columns[$component->getName()];
    }

    public function getRowData(DocumentInterface|Product $document, $fields, $options): array
    {
        return array_values(array_map(fn($field) => $this->getColumnData($document, $field), $fields));
    }

    public function getColumnData(DocumentInterface|Product $document, string $field): string
    {
        $value = $document->getData($field);
        $sku = $document->getData('sku');

        if ($field == 'quantity_per_source' && $sku !== null) {
            $value = $this->quantityPerSource->execute($sku);
        }

        if (is_array($value)) {
            return implode(', ', $value);
        }

        return $value;
    }
}
