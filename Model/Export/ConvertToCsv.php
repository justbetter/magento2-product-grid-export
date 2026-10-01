<?php

namespace JustBetter\ProductGridExport\Model\Export;

use JustBetter\ProductGridExport\Model\LazySearchResultIterator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Ui\Component\MassAction\Filter;

class ConvertToCsv extends \Magento\Ui\Model\Export\ConvertToCsv
{
    /**
     * @var MetadataProvider
     */
    protected $metadataProvider;

    /**
     * Yield the header row followed by one row per product in the grid.
     *
     * @return \Generator<int, array<mixed>>
     * @throws LocalizedException
     */
    public function getGenerator(): \Generator
    {
        $component = $this->filter->getComponent();

        if ($component->getContext()->getRequestParam(Filter::EXCLUDED_PARAM) === 'false') {
            $this->filter->prepareComponent($component);
        }
        $this->filter->applySelectionOnTargetProvider();
        $dataProvider = $component->getContext()->getDataProvider();
        $fields = $this->metadataProvider->getFields($component);

        $columnsWithType = $this->metadataProvider->getColumnsWithDataType($component);
        $items = new LazySearchResultIterator($dataProvider->getSearchResult(), $this->pageSize ?? 200);

        yield $this->metadataProvider->getHeaders($component);
        foreach ($items as $item) {
            $this->metadataProvider->convertDate($item, $component->getName());
            yield $this->metadataProvider->getRowDataBasedOnColumnType($item, $fields, $columnsWithType, []);
        }
    }

    /**
     * Returns CSV file
     *
     * @return array<string, mixed>
     * @throws LocalizedException
     */
    public function getCsvFile()
    {
        $component = $this->filter->getComponent();

        $name = hash('sha256', microtime());
        $file = 'export/' . $component->getName() . $name . '.csv';

        $directory = $this->directory;
        if (!$directory instanceof WriteInterface) {
            throw new LocalizedException(__('The export directory is not writable.'));
        }
        $directory->create('export');
        $stream = $directory->openFile($file, 'w+');
        $stream->lock();

        $data = $this->getGenerator();
        foreach ($data as $line) {
            $stream->writeCsv($line);
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
