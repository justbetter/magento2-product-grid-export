<?php

namespace JustBetter\ProductGridExport\Plugin\Model\Export;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Ui\Component\MassAction\Filter;
use Magento\Ui\Model\BookmarkManagement;
use Magento\Ui\Model\Export\MetadataProvider;

/**
 * Class ConvertToCsv
 */
class ConvertToCsv
{
    /**
     * @var DirectoryList
     */
    protected $directory;

    /**
     * @param Filesystem $filesystem
     * @param Filter $filter
     * @param MetadataProvider $metadataProvider
     * @param BookmarkManagement $bookmarkManagement
     * @param int $pageSize
     * @throws FileSystemException
     */
    public function __construct(
        Filesystem $filesystem,
        protected Filter $filter,
        protected MetadataProvider $metadataProvider,
        protected BookmarkManagement $bookmarkManagement,
        protected $pageSize = 200
    ) {
        $this->directory = $filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
    }

    /**
     * Returns CSV file
     *
     * @param Magento\Ui\Model\Export\ConvertToCsv $subject
     * @param callable $proceed
     *
     * @return array
     * @throws LocalizedException
     */
    public function aroundGetCsvFile($subject, callable $proceed)
    {
        $component = $this->filter->getComponent();
        if ($component->getName() !== 'product_listing') {
            return $proceed();
        }
        // md5() here is not for cryptographic use.
        // phpcs:ignore Magento2.Security.InsecureFunction
        $name = md5(microtime());
        $file = 'export/'. $component->getName() . $name . '.csv';

        $this->filter->applySelectionOnTargetProvider();
        $dataProvider = $component->getContext()->getDataProvider();
        $fields = $this->getActiveColumns($component);

        $this->directory->create('export');
        $stream = $this->directory->openFile($file, 'w+');
        $stream->lock();
        $stream->writeCsv($fields);
        $page = 1;

        $searchCriteria = $dataProvider->getSearchResult()
            ->setCurPage($page)
            ->setPageSize($this->pageSize);
        $totalCount = (int) $dataProvider->getSearchResult()->getSize();
        while ($totalCount > 0) {
            $items = $dataProvider->getSearchResult()->getItems();
            
            foreach ($items as $item) {
                $this->metadataProvider->convertDate($item, $component->getName());
                $stream->writeCsv(array_values(array_map(fn ($field) => is_array($value = $item->getData($field)) ? implode(', ', $value) : $value, $fields)));
            }
            $searchCriteria->setCurPage(++$page);
            $totalCount = $totalCount - $this->pageSize;
        }
        $stream->unlock();
        $stream->close();

        return [
            'type' => 'filename',
            'value' => $file,
            'rm' => true  // can delete file after use
        ];
    }

    /**
     * Get active columns sorted by positions.
     *
     * @param Magento\Ui\Component\Listing $component
     *
     * @return array
     */
    protected function getActiveColumns($component)
    {
        $bookmark = $this->bookmarkManagement->getByIdentifierNamespace('current', $component->getName());

        $config = $bookmark->getConfig();
        // Remove all invisible columns as well as ids, and actions columns.
        $columns = array_filter($config['current']['columns'], fn($config, $key) => $config['visible'] && !in_array($key, ['ids', 'actions']), ARRAY_FILTER_USE_BOTH);

        // Sort by position in grid.
        uksort($columns, fn($a, $b) => $config['current']['positions'][$a] <=> $config['current']['positions'][$b]);

        return array_keys($columns);
    }
}
