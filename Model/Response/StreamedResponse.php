<?php

declare(strict_types=1);

namespace JustBetter\ProductGridExport\Model\Response;

use InvalidArgumentException;
use Magento\Framework\App\Http\Context;
use Magento\Framework\App\PageCache\NotCacheableInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Response\Http;

use Magento\Framework\Session\Config\ConfigInterface;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\Stdlib\DateTime;

/**
 * @SuppressWarnings(PHPMD.CookieAndSessionMisuse)
 *
 * StreamedResponse represents a streamed HTTP response.
 *
 * A StreamedResponse uses a callback for its content.
 *
 * The callback should use the standard PHP functions like echo
 * to stream the response back to the client. The flush() function
 * can also be used if needed.
 *
 * @see flush()
 *
 * @see \Symfony\Component\HttpFoundation\StreamedResponse
 */
class StreamedResponse extends Http implements NotCacheableInterface
{
    private const DEFAULT_RAW_CONTENT_TYPE = 'application/octet-stream';

    /**
     * @var array
     */
    private array $options = [
        // File name to send to the client
        'fileName' => null,
        'contentType' => null,
        'callback' => null,
        // Whether to send the file as attachment
        'attachment' => true
    ];

    /**
     * @param HttpRequest $request
     * @param CookieManagerInterface $cookieManager
     * @param CookieMetadataFactory $cookieMetadataFactory
     * @param Context $context
     * @param DateTime $dateTime
     * @param ConfigInterface $sessionConfig
     * @param Http $response
     * @param array $options
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        HttpRequest $request,
        CookieManagerInterface $cookieManager,
        CookieMetadataFactory $cookieMetadataFactory,
        Context $context,
        DateTime $dateTime,
        ConfigInterface $sessionConfig,
        private Http $response,
        array $options = []
    ) {
        parent::__construct($request, $cookieManager, $cookieMetadataFactory, $context, $dateTime, $sessionConfig);
        $this->options = array_merge($this->options, $options);
        $this->options['contentType'] ??= self::DEFAULT_RAW_CONTENT_TYPE;

        if (!isset($this->options['callback'])) {
            throw new InvalidArgumentException("callback is required.");
        }
    }

    /**
     * @inheritDoc
     */
    public function sendResponse()
    {
        $forceHeaders = true;

        $this->response->setHttpResponseCode(200);
        if ($this->options['attachment']) {
            $this->response->setHeader(
                'Content-Disposition',
                'attachment; filename="' . $this->options['fileName'] . '"',
                $forceHeaders
            );
        }

        $this->response
            ->setHeader('X-Accel-Buffering', 'no', $forceHeaders)
            ->setHeader('Content-Type', $this->options['contentType'], $forceHeaders)
            ->setHeader('Pragma', 'public', $forceHeaders)
            ->setHeader('Cache-Control', 'must-revalidate, post-check=0, pre-check=0', $forceHeaders)
            ->setHeader('Last-Modified', date('r'), $forceHeaders);

        $this->response->sendHeaders();
        if (!$this->request->isHead()) {
            $this->sendCallback();
        }
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function setHeader($name, $value, $replace = false)
    {
        $this->response->setHeader($name, $value, $replace);
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getHeader($name)
    {
        return $this->response->getHeader($name);
    }

    /**
     * @inheritDoc
     */
    public function clearHeader($name)
    {
        $this->response->clearHeader($name);
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function setBody($value)
    {
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function appendBody($value)
    {
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getContent()
    {
        return '';
    }

    /**
     * @inheritDoc
     */
    public function setContent($value)
    {
        return $this;
    }

    /**
     * Sends file content to the client
     *
     * @return void
     */
    public function sendCallback(): void
    {
        $this->options['callback']();
    }
}
