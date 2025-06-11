<?php

namespace JustBetter\ProductGridExport\Controller\Adminhtml\Export;

use Indykoning\Jsonl\Jsonl;
use JustBetter\ProductGridExport\Model\Export\ConvertToJsonl;
use JustBetter\ProductGridExport\Model\Response\StreamedResponseFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Psr\Log\LoggerInterface;

class GridToJsonl extends Action
{
    /**
     * @param Context $context
     * @param ConvertToCsv $converter
     * @param StreamedResponseFactory $streamedResponseFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        protected ConvertToJsonl $converter,
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
        $data = Jsonl::encode($this->converter->getGenerator());

        return $this->streamedResponseFactory->create([
            'options' => [
                'fileName' => 'export.jsonl',
                'callback' => function () use ($data) {
                    try {
                        foreach ($data as $chunk) {
                            echo $chunk;
                            ob_flush();
                            flush();
                        }
                    } catch (\Exception $e) {
                        $this->logger->critical($e);
                    }
                }
            ]
        ]
        );
    }
}
