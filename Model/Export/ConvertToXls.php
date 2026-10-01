<?php

namespace JustBetter\ProductGridExport\Model\Export;

use JustBetter\ProductGridExport\Model\LazySearchResultIterator;
use Magento\Catalog\Model\Product;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Ui\Component\MassAction\Filter;
use Magento\Ui\Model\Export\ConvertToXml;

class ConvertToXls extends ConvertToXml
{
    /**
     * @var MetadataProvider
     */
    protected $metadataProvider;

    /**
     * @var string[]
     */
    protected $fields;

    /**
     * @var array<string, string>
     */
    protected array $columnsWithType = [];

    /**
     * @var int
     */
    protected int $pageSize = 200;

    /**
     * Returns XLS file
     *
     * @return array<string, mixed>
     * @throws \Magento\Framework\Exception\FileSystemException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getXlsFile()
    {
        $component = $this->filter->getComponent();

        $name = hash('sha256', microtime());
        $file = 'export/' . $component->getName() . $name . '.xls';

        if ($component->getContext()->getRequestParam(Filter::EXCLUDED_PARAM) === 'false') {
            $this->filter->prepareComponent($component);
        }
        $this->filter->applySelectionOnTargetProvider();
        $dataProvider = $component->getContext()->getDataProvider();

        $this->columnsWithType = $this->metadataProvider->getColumnsWithDataType($component);
        $this->fields = $this->metadataProvider->getFields($this->filter->getComponent());

        $excel = $this->excelFactory->create([
            'iterator' => new LazySearchResultIterator($dataProvider->getSearchResult(), $this->pageSize),
            'rowCallback' => [$this, 'getRowDataBasedOnColumnType'],
        ]);

        $directory = $this->directory;
        if (!$directory instanceof WriteInterface) {
            throw new LocalizedException(__('The export directory is not writable.'));
        }
        $directory->create('export');
        $stream = $directory->openFile($file, 'w+');
        $stream->lock();
        $excel->setDataHeader($this->metadataProvider->getHeaders($component));
        $excel->write($stream, $component->getName() . '.xls');

        $stream->unlock();
        $stream->close();

        return [
            'type' => 'filename',
            'value' => $file,
            'rm' => true  // can delete file after use
        ];
    }

    /**
     * Get the row values of a product, with readable labels.
     *
     * @param Product $item
     * @return array<mixed>
     */
    public function getRowDataBasedOnColumnType($item): array
    {
        return $this->metadataProvider->getRowDataBasedOnColumnType($item, $this->fields, $this->columnsWithType, []);
    }

    /**
     * Get the raw row values of a product.
     *
     * @param DataObject $item
     * @return array<mixed>
     */
    public function getRowData($item): array
    {
        return $this->metadataProvider->getRowData($item, $this->metadataProvider->getFields($this->filter->getComponent()), []);
    }
}
