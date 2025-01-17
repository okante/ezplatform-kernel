<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace eZ\Publish\API\Repository\Values\UserPreference;

use ArrayIterator;
use eZ\Publish\API\Repository\Values\ValueObject;
use IteratorAggregate;

/**
 * List of user preferences.
 */
class UserPreferenceList extends ValueObject implements IteratorAggregate
{
    /**
     * The total number of user preferences.
     *
     * @var int
     */
    public $totalCount = 0;

    /**
     * List of user preferences.
     *
     * @var \eZ\Publish\API\Repository\Values\UserPreference\UserPreference[]
     */
    public $items = [];

    /**
     * {@inheritdoc}
     */
    public function getIterator(): \Traversable
    {
        return new ArrayIterator($this->items);
    }
}
