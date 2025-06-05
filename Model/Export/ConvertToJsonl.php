<?php

namespace JustBetter\ProductGridExport\Model\Export;

use JustBetter\ProductGridExport\Model\LazySearchResultIterator;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Ui\Component\MassAction\Filter;

class ConvertToJsonl
{
    /**
     * @var \Magento\Framework\Filesystem\Directory\WriteInterface
     */
    protected $directory;

    /**
     * @param Filesystem $filesystem
     * @param Filter $filter
     * @param MetadataProvider $metadataProvider
     * @param int $pageSize
     * @throws FileSystemException
     */
    public function __construct(
        protected Filesystem $filesystem,
        protected Filter $filter,
        protected MetadataProvider $metadataProvider,
        protected $pageSize = 200
    ) {
        $this->directory = $filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        $this->metadataProvider = $metadataProvider;
    }

    /**
     * @return \Generator<array>
     */
    public function getGenerator(): \Generator
    {
        $component = $this->filter->getComponent();
        $this->filter->applySelectionOnTargetProvider();

        $dataProvider = $component->getContext()->getDataProvider();
        $fields = $this->metadataProvider->getFields($component);
        $columnsWithType= $this->metadataProvider->getColumnsWithDataType($component);
        $page = 1;

        $searchResult = $dataProvider->getSearchResult()
            ->setCurPage($page)
            ->setPageSize($this?->pageSize ?? 200);

        $items = LazySearchResultIterator::getGenerator($searchResult);
        foreach ($items as $item) {
            $this->metadataProvider->convertDate($item, $component->getName());
            yield $this->metadataProvider->getRowDataBasedOnColumnType($item, $fields, $columnsWithType, ['no_arrays' => false]);;
        }
    }

    /**
     * Returns jsonl (json-lines) file
     *
     * @return array
     * @throws LocalizedException
     */
    public function getJsonlFile()
    {
        $component = $this->filter->getComponent();

        $name = md5(microtime());
        $file = 'export/'. $component->getName() . $name . '.jsonl';

        $this->directory->create('export');
        /** @var \Magento\Framework\Filesystem\File\WriteInterface $stream */
        $stream = $this->directory->openFile($file, 'w+');

        $stream->lock();
        $items = \Indykoning\Jsonl\Jsonl::encode($this->getGenerator());
        foreach ($items as $item) {
            $stream->write($item . PHP_EOL);
        }
        $stream->unlock();
        $stream->close();

        return [
            'type' => 'filename',
            'value' => $file,
            'rm' => true  // can delete file after use
        ];
    }
}
