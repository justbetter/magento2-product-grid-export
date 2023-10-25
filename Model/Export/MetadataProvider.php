<?php

namespace JustBetter\ProductGridExport\Model\Export;

use Magento\Framework\View\Element\UiComponentInterface;
use Magento\Ui\Component\MassAction\Filter;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Ui\Model\BookmarkManagement;

class MetadataProvider extends \Magento\Ui\Model\Export\MetadataProvider
{
    /**
     * @var BookmarkManagement
     */
    protected $_bookmarkManagement;

    /**
     * MetadataProvider constructor.
     * @param Filter $filter
     * @param TimezoneInterface $localeDate
     * @param ResolverInterface $localeResolver
     * @param string $dateFormat
     * @param BookmarkManagement $bookmarkManagement
     * @param array $data
     */
    public function __construct(
        Filter $filter,
        TimezoneInterface $localeDate,
        ResolverInterface $localeResolver,
        BookmarkManagement $bookmarkManagement,
        $dateFormat = 'M j, Y H:i:s A',
        array $data = [])
    {
        parent::__construct($filter, $localeDate, $localeResolver, $dateFormat, $data);
        $this->_bookmarkManagement = $bookmarkManagement;
    }

    protected function getActiveColumns($component){
        $bookmark = $this->_bookmarkManagement->getByIdentifierNamespace('current', $component->getName());

        $config = $bookmark->getConfig();
        // Remove all invisible columns as well as ids, and actions columns.
        $columns = array_filter($config['current']['columns'], fn($config, $key) => $config['visible'] && !in_array($key, ['ids', 'actions']), ARRAY_FILTER_USE_BOTH);;
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
            foreach ($columns->getChildComponents() as $column) {
                if ($column->getData('config/label') && $column->getData('config/dataType') !== 'actions') {
                    if(in_array($column->getName(), $activeColumns)) {
                        $this->columns[$component->getName()][$column->getName()] = $column;
                    }
                }
            }
        }
        return $this->columns[$component->getName()];
    }


    public function getRowData($document, $fields, $options): array{
        return array_values(array_map(fn($field) => is_array($value = $document->getData($field)) ? implode(', ', $value) : $value, $fields));
    }
}
