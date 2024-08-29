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
        $columns = array_filter($config['current']['columns'], function($column, $key) use ($config) {
            return isset($config['current']['positions'][$key]) && $column['visible'] && !in_array($key, ['ids', 'actions']);
        }, ARRAY_FILTER_USE_BOTH);
        // Sort by position in grid.
        uksort($columns, fn($a, $b) => $config['current']['positions'][$a] <=> $config['current']['positions'][$b]);

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
        $rowData = array_values(
            array_map(
                function($field) use ($columnsType,$document) {
                    if ($field == 'attribute_set_id') {
                        $columnData = $this->getAttributeSetName($document, $field);
                    } elseif ($field == 'websites') {
                        $columnData = $this->getWebsiteName($document, $field);
                    } elseif (isset($columnsType[$field]) && $columnsType[$field] == 'select')  {
                        // $columnData = $this->handleSelectField($document, $field);
                        $columnData = (trim($document->getAttributeText($field))) ? trim($document->getAttributeText($field)) : $this->getColumnData($document, $field);  
                    } elseif (isset($columnsType[$field]) && $columnsType[$field] == 'multiselect')  {
                        $columnData = is_array($document->getAttributeText($field)) ? implode(',',$document->getAttributeText($field)) : $document->getAttributeText($field);
                    } else {
                        $columnData = $this->getColumnData($document, $field);
                    }
                    return $columnData;
                }, 
            $fields)
        );
        return $rowData;
    }

    public function getRowData($document, $fields, $options): array{
        $rowData = array_values(array_map(fn($field) => $this->getColumnData($document, $field), $fields));
        return $rowData;
    }

    public function getColumnData($document, $field)
    {
        $value = $document->getData($field);

        if (is_array($value)) {
            return implode(', ', $value);
        }

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
    protected function handleSelectField(\Magento\Catalog\Model\Product $_productItem, string $field):string {
        if (trim($_productItem->getAttributeText($field))) {
            $columnData = trim($_productItem->getAttributeText($field)); 
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
     * @return string $websiteName
     * 
     */
    protected function getWebsiteName(\Magento\Catalog\Model\Product $_productItem, string $field):string {
        $websiteId = $this->getColumnData($_productItem,$field);
        /** @var $_website \Magento\Store\Api\Data\WebsiteInterface */
        $_website = $this->websiteRepository->getById($websiteId);
        $websiteName = ($_website) ? $_website->getName() : '';
        return $websiteName;
    }

}
