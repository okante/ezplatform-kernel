<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace eZ\Publish\API\Repository\Values\Bookmark;

use ArrayIterator;
use eZ\Publish\API\Repository\Values\ValueObject;
use IteratorAggregate;
use Traversable;

/**
 * List of bookmarked locations.
 */
class BookmarkList extends ValueObject implements IteratorAggregate
{
    /**
     * The total number of bookmarks.
     *
     * @var int
     */
    public $totalCount = 0;

    /**
     * List of bookmarked locations.
     *
     * @var \eZ\Publish\API\Repository\Values\Content\Location[]
     */
    public $items = [];

    /**
     * {@inheritdoc}
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }
}
