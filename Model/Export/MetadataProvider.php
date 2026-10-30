<?php

namespace JustBetter\ProductGridExport\Model\Export;

use Magento\Catalog\Model\Product;
use Magento\Eav\Api\AttributeSetRepositoryInterface as AttributeSetRepository;
use Magento\Framework\DataObject;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\View\Element\UiComponentInterface;
use Magento\Store\Api\WebsiteRepositoryInterface as WebsiteRepository;
use Magento\Ui\Component\MassAction\Filter;
use Magento\Ui\Model\BookmarkManagement;

class MetadataProvider extends \Magento\Ui\Model\Export\MetadataProvider
{
    /**
     * @var BookmarkManagement
     */
    protected $_bookmarkManagement;

    /**
     * @var AttributeSetRepository
     */
    protected $attributeSetRepository;

    /**
     * @var WebsiteRepository
     */
    protected $websiteRepository;

    /**
     * @var array<string, string>
     */
    protected $columnsType;

    /**
     * MetadataProvider constructor.
     *
     * @param Filter $filter
     * @param TimezoneInterface $localeDate
     * @param ResolverInterface $localeResolver
     * @param BookmarkManagement $bookmarkManagement
     * @param AttributeSetRepository $attributeSetRepository
     * @param WebsiteRepository $websiteRepository
     * @param string $dateFormat
     * @param mixed[] $data
     */
    public function __construct(
        Filter $filter,
        TimezoneInterface $localeDate,
        ResolverInterface $localeResolver,
        BookmarkManagement $bookmarkManagement,
        AttributeSetRepository $attributeSetRepository,
        WebsiteRepository $websiteRepository,
        $dateFormat = 'M j, Y H:i:s A',
        array $data = []
    ) {
        parent::__construct($filter, $localeDate, $localeResolver, $dateFormat, $data);
        $this->_bookmarkManagement = $bookmarkManagement;
        $this->attributeSetRepository = $attributeSetRepository;
        $this->websiteRepository = $websiteRepository;
    }

    /**
     * Get the codes of the visible columns, in the order of the admin's current grid view.
     *
     * @param UiComponentInterface $component
     * @return string[]
     */
    protected function getActiveColumns(UiComponentInterface $component): array
    {
        $bookmark = $this->_bookmarkManagement->getByIdentifierNamespace('current', $component->getName());

        $config = $bookmark->getConfig();
        // Remove all invisible columns as well as ids, and actions columns.
        $columns = array_filter(
            $config['current']['columns'],
            fn($config, $key) => $config['visible'] && !in_array($key, ['ids', 'actions']),
            ARRAY_FILTER_USE_BOTH
        );

        // Sort by position in grid.
        uksort(
            $columns,
            fn($a, $b) => ($config['current']['positions'][$a] ?? PHP_INT_MAX)
                <=> ($config['current']['positions'][$b] ?? PHP_INT_MAX)
        );

        return array_keys($columns);
    }

    /**
     * Get the visible grid columns that have a label, in grid order.
     *
     * @param UiComponentInterface $component
     * @return UiComponentInterface[]
     * @throws \Exception
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

    /**
     * Get the data type of every visible grid column, keyed by column code.
     *
     * @param UiComponentInterface $component
     * @return array<string, string>
     * @throws \Exception
     */
    public function getColumnsWithDataType(UiComponentInterface $component): array
    {
        $this->columnsType = [];
        $activeColumns = $this->getActiveColumns($component);
        $columns = $this->getColumnsComponent($component);
        $components = $columns->getChildComponents();

        foreach ($activeColumns as $columnName) {
            $column = $components[$columnName] ?? null;
            if (isset($column) && $column->getData('config/label') && $column->getData('config/dataType') !== 'actions') {
                $this->columnsType[$column->getName()] = $column->getData('config/dataType');
            }
        }
        return $this->columnsType;
    }

    /**
     * Get the row values of a product, with labels for select, attribute set and website columns.
     *
     * @param Product $document
     * @param string[] $fields
     * @param string[] $columnsType
     * @param mixed[] $options
     * @return array<string, mixed>
     */
    public function getRowDataBasedOnColumnType($document, $fields, $columnsType, $options): array
    {
        $rowData = array_map(
            function ($field) use ($columnsType, $document, $options) {
                $columnData = match (true) {
                    $field == 'shared_catalog' => '',
                    $field == 'attribute_set_id' => $this->getAttributeSetName($document, $field),
                    $field == 'websites' => $this->getWebsiteName($document, $field),
                    isset($columnsType[$field]) && $columnsType[$field] == 'select' => $this->handleSelectField($document, $field),
                    isset($columnsType[$field]) && $columnsType[$field] == 'multiselect' => $document->getAttributeText($field),
                    default => $this->getColumnData($document, $field)
                };

                if (($options['no_arrays'] ?? true) && is_array($columnData)) {
                    $columnData = implode(', ', $this->flattenArray($columnData));
                }

                return $columnData;
            },
            array_combine($fields, $fields)
        );

        return $rowData;
    }

    /**
     * Flatten a nested array into a list of its values.
     *
     * @param array<mixed> $array
     * @param float|int $depth
     * @return array<int, mixed>
     */
    public function flattenArray($array, $depth = INF): array
    {
        $result = [];

        foreach ($array as $item) {
            if (! is_array($item)) {
                $result[] = $item;
            } else {
                $values = $depth === 1
                    ? array_values($item)
                    : $this->flattenArray($item, $depth - 1);

                foreach ($values as $value) {
                    $result[] = $value;
                }
            }
        }

        return $result;
    }

    /**
     * Get the raw row values of a document.
     *
     * @param DataObject $document
     * @param string[] $fields
     * @param mixed[] $options
     * @return array<mixed>
     */
    public function getRowData($document, $fields, $options): array
    {
        $rowData = array_map(fn($field) => $this->getColumnData($document, $field), $fields);
        return $rowData;
    }

    /**
     * Get the raw value of a field.
     *
     * @param DataObject $document
     * @param string $field
     * @return mixed
     */
    public function getColumnData($document, $field)
    {
        $value = $document->getData($field);

        return $value;
    }

    /**
     * Get the option label of a select attribute, or its raw value when it has no label.
     *
     * @param Product $_productItem
     * @param string $field
     * @return string
     */
    protected function handleSelectField(Product $_productItem, string $field): string
    {
        $label = $_productItem->getAttributeText($field);
        if (!is_array($label) && trim((string) $label)) {
            return trim((string) $label);
        }

        $columnData = $this->getColumnData($_productItem, $field);

        return is_scalar($columnData) ? (string) $columnData : '';
    }

    /**
     * Get the name of the product's attribute set.
     *
     * @param Product $_productItem
     * @param string $field
     * @return string
     */
    protected function getAttributeSetName(Product $_productItem, string $field): string
    {
        $attributeSetId = $_productItem->getData($field);
        $_attributeSet = $this->attributeSetRepository->get($attributeSetId);
        $attributeSetName = ($_attributeSet) ? $_attributeSet->getAttributeSetName() : '';
        return $attributeSetName;
    }

    /**
     * Get the names of the product's websites.
     *
     * @param Product $_productItem
     * @param string $field
     * @return string[]
     */
    protected function getWebsiteName(Product $_productItem, string $field): array
    {
        $websiteIds = $_productItem->getData($field);
        if (!$websiteIds) {
            return [];
        }
        if (!is_array($websiteIds)) {
            $websiteIds = [$websiteIds];
        }

        return array_map(
            function ($websiteId) {
                return (string) $this->websiteRepository->getById($websiteId)->getName();
            },
            $websiteIds
        );
    }
}
