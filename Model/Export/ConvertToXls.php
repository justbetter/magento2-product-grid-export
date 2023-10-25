<?php

namespace JustBetter\ProductGridExport\Model\Export;

use Magento\Ui\Model\Export\ConvertToXml;

class ConvertToXls extends ConvertToXml
{

    /**
     * Returns XLS file
     *
     * @return array
     * @throws \Magento\Framework\Exception\FileSystemException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getXlsFile()
    {
        $component = $this->filter->getComponent();

        $name = md5(microtime());
        $file = 'export/'. $component->getName() . $name . '.xls';

        $this->filter->applySelectionOnTargetProvider();
        $dataProvider = $component->getContext()->getDataProvider();

        $searchResult = $dataProvider->getSearchResult();

        $searchResultItems = $searchResult->getItems();
        $searchResultIterator = $this->iteratorFactory->create(['items' => $searchResultItems]);
        $excel = $this->excelFactory->create([
            'iterator' => $searchResultIterator,
            'rowCallback'=> [$this, 'getRowData'],
        ]);

        $this->directory->create('export');
        $stream = $this->directory->openFile($file, 'w+');
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

    public function getRowData($item) : array{
        return $this->metadataProvider->getRowData($item, $this->metadataProvider->getFields($this->filter->getComponent()), []);
    }

}
