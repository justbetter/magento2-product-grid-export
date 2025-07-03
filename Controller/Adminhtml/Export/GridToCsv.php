<?php

namespace JustBetter\ProductGridExport\Controller\Adminhtml\Export;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use JustBetter\ProductGridExport\Model\Export\ConvertToCsv;
use JustBetter\ProductGridExport\Model\Response\StreamedResponseFactory;
use Psr\Log\LoggerInterface;

class GridToCsv extends Action
{
    /**
     * @param Context $context
     * @param ConvertToCsv $converter
     * @param StreamedResponseFactory $streamedResponseFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        protected ConvertToCsv $converter,
        protected StreamedResponseFactory $streamedResponseFactory,
        protected LoggerInterface $logger
    ){
        parent::__construct($context);
    }

    /**
     * Export data provider to CSV
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     * @return \Magento\Framework\App\ResponseInterface
     */
    public function execute()
    {
        $data = $this->converter->getGenerator();
        return $this->streamedResponseFactory->create([
            'options' => [
                'fileName' => 'export.csv',
                'callback' => function () use ($data) {
                    set_time_limit(0);
                    $handle = fopen('php://output', 'w');
                    try {
                        foreach ($data as $chunk) {
                            fputcsv($handle, $chunk);
                            ob_flush();
                            flush();
                        }
                    } catch (\Exception $e) {
                        $this->logger->critical($e);
                    } finally {
                        fclose($handle);
                    }
                }
            ]
        ]);
    }

}
