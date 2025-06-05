<?php

namespace JustBetter\ProductGridExport\Model\Export;

use Magento\Framework\View\Element\UiComponentInterface;
use Magento\Ui\Component\MassAction\Filter;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Ui\Model\BookmarkManagement;
use Magento\Eav\Api\AttributeSetRepositoryInterface as AttributeSetRepository;
use Magento\Store\Api\WebsiteRepositoryInterface as WebsiteRepository;

class MetadataProvider extends \Magento\Ui\Model\Export\MetadataProvider
{
    /**
     * @var BookmarkManagement
     */
    protected $_bookmarkManagement;

    protected $attributeSetRepository;

    protected $websiteRepository;

    /**
     * @var array $columnsType
     */
    protected $columnsType;

    /**
     * MetadataProvider constructor.
     * @param Filter $filter
     * @param TimezoneInterface $localeDate
     * @param ResolverInterface $localeResolver
     * @param string $dateFormat
     * @param BookmarkManagement $bookmarkManagement
     * @param AttributeSetRepository $attributeSetRepository
     * @param WebsiteRepository $websiteRepository
     * @param array $data
     */
    public function __construct(
        Filter $filter,
        TimezoneInterface $localeDate,
        ResolverInterface $localeResolver,
        BookmarkManagement $bookmarkManagement,
        AttributeSetRepository $attributeSetRepository,
        WebsiteRepository $websiteRepository,
        $dateFormat = 'M j, Y H:i:s A',
        array $data = [])
    {
        parent::__construct($filter, $localeDate, $localeResolver, $dateFormat, $data);
        $this->_bookmarkManagement = $bookmarkManagement;
        $this->attributeSetRepository = $attributeSetRepository;
        $this->websiteRepository = $websiteRepository;
    }

    protected function getActiveColumns($component){
        $bookmark = $this->_bookmarkManagement->getByIdentifierNamespace('current', $component->getName());

        $config = $bookmark->getConfig();
        // Remove all invisible columns as well as ids, and actions columns.
        $columns = array_filter($config['current']['columns'], fn($config, $key) => $config['visible'] && !in_array($key, ['ids', 'actions']), ARRAY_FILTER_USE_BOTH);;

        // Sort by position in grid.
        uksort($columns, fn($a, $b) =>
            (isset($config['current']['positions'][$a]) ? $config['current']['positions'][$a] : PHP_INT_MAX)
            <=>
            (isset($config['current']['positions'][$b]) ? $config['current']['positions'][$b] : PHP_INT_MAX)
        );

        return array_keys($columns);
    }

    /**
     * @param UiComponentInterface $component
     * @return UiComponentInterface[]
     * @throws \Exception
     */
    protected function getColumns(UiComponentInterface $component) : array
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
     * @param UiComponentInterface $component
     * @return string[]
     * @throws \Exception
     */
    public function getColumnsWithDataType(UiComponentInterface $component) : array
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
     *
     * @param \Magento\Catalog\Model\Product $document
     * @param string[] $fields
     * @param string[] $columnsType
     *
     * @return array
     *
     */
    public function getRowDataBasedOnColumnType($document, $fields, $columnsType, $options): array{
        $rowData =
            array_map(
                function($field) use ($columnsType, $document, $options) {
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

    public function getRowData($document, $fields, $options): array{
        $rowData = array_map(fn($field) => $this->getColumnData($document, $field), $fields);
        return $rowData;
    }

    public function getColumnData($document, $field)
    {
        $value = $document->getData($field);

        return $value;
    }

    /**
     *
     * handler of select fields attribute
     *
     * @param \Magento\Catalog\Model\Product $_productItem
     * @param string $field
     *
     * @return string $columnData
     *
     */
    protected function handleSelectField(\Magento\Catalog\Model\Product $_productItem, string $field): string|array {
        if (trim((string) $_productItem->getAttributeText($field))) {
            $columnData = trim((string) $_productItem->getAttributeText($field));
        }  else {
            $columnData = $this->getColumnData($_productItem, $field);
        }
        return (string) $columnData;
    }


    /**
     *
     * @param \Magento\Catalog\Model\Product $_productItem
     * @param string $field
     *
     * @return string $attributeSetName
     *
     */
    protected function getAttributeSetName(\Magento\Catalog\Model\Product $_productItem, string $field):string {
        $attributeSetId = $_productItem->getData($field);
        /** @var $_attributeSet \Magento\Eav\Api\Data\AttributeSetInterface */
        $_attributeSet = $this->attributeSetRepository->get($attributeSetId);
        $attributeSetName = ($_attributeSet) ? $_attributeSet->getAttributeSetName() : '';
        return $attributeSetName;
    }

    /**
     *
     * @param \Magento\Catalog\Model\Product $_productItem
     * @param string $field
     *
     * @return array $websiteNames
     *
     */
    protected function getWebsiteName(\Magento\Catalog\Model\Product $_productItem, string $field) : array {
        $websiteIds = $_productItem->getData($field);
        if (!$websiteIds) {
            return [];
        }
        if (!is_array($websiteIds)) {
            $websiteIds = [$websiteIds];
        }

        return array_map(
            function ($websiteId) {
                return $this->websiteRepository->getById($websiteId)?->getName() ?? '';
            },
            $websiteIds
        );
    }

}
