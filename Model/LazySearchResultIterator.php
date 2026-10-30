<?php

namespace JustBetter\ProductGridExport\Model;

use Generator;
use Iterator;
use Magento\Eav\Model\Entity\Collection\AbstractCollection;
use Magento\Framework\Exception\LocalizedException;

/**
 * Iterates a collection page by page, so only one page of items is loaded at a time.
 *
 * @implements Iterator<int, mixed>
 */
class LazySearchResultIterator implements Iterator
{
    /**
     * @var Generator<int, mixed>
     */
    protected Generator $generator;

    /**
     * Start iterating the search result from its first page.
     *
     * @param object $searchResult
     * @param int $pageSize
     * @throws LocalizedException
     */
    public function __construct(object $searchResult, int $pageSize)
    {
        if (!$searchResult instanceof AbstractCollection) {
            throw new LocalizedException(__('The product grid export requires a collection based data provider.'));
        }

        $searchResult->setCurPage(1)->setPageSize($pageSize);
        $this->generator = $this->getGenerator($searchResult);
    }

    /**
     * Yield the items of every page, starting at the current page of the collection.
     *
     * @param AbstractCollection $collection
     * @return Generator<int, mixed>
     */
    protected function getGenerator(AbstractCollection $collection): Generator
    {
        $lastPage = (int) $collection->getLastPageNumber();

        for ($page = (int) $collection->getCurPage(); $page <= $lastPage; $page++) {
            $items = $collection->setCurPage($page)
                ->clear() // If we do not clear, getItems will return stale results.
                ->getItems();

            yield from $items;
        }
    }

    /**
     * @inheritDoc
     */
    public function rewind(): void
    {
        $this->generator->rewind();
    }

    /**
     * @inheritDoc
     */
    public function valid(): bool
    {
        return $this->generator->valid();
    }

    /**
     * @inheritDoc
     */
    public function current(): mixed
    {
        return $this->generator->current();
    }

    /**
     * @inheritDoc
     */
    public function key(): mixed
    {
        return $this->generator->key();
    }

    /**
     * @inheritDoc
     */
    public function next(): void
    {
        $this->generator->next();
    }
}
