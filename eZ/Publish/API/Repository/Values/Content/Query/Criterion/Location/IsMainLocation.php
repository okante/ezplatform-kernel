<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace eZ\Publish\API\Repository\Values\Content\Query\Criterion\Location;

use eZ\Publish\API\Repository\Values\Content\Query\Criterion\Location;
use eZ\Publish\API\Repository\Values\Content\Query\Criterion\Operator;
use eZ\Publish\API\Repository\Values\Content\Query\Criterion\Operator\Specifications;
use eZ\Publish\SPI\Repository\Values\Filter\FilteringCriterion;
use InvalidArgumentException;

/**
 * A criterion that matches Location based on if it is main Location or not.
 */
class IsMainLocation extends Location implements FilteringCriterion
{
    /**
     * Main constant: is main.
     */
    public const MAIN = 0;

    /**
     * Main constant: is not main.
     */
    public const NOT_MAIN = 1;

    /**
     * Creates a new IsMainLocation criterion.
     *
     * @throws \InvalidArgumentException
     *
     * @param int $value one of self::MAIN and self::NOT_MAIN
     */
    public function __construct($value)
    {
        if ($value !== self::MAIN && $value !== self::NOT_MAIN) {
            throw new InvalidArgumentException("Invalid main status value $value");
        }

        parent::__construct(null, null, $value);
    }

    public function getSpecifications(): array
    {
        return [
            new Specifications(
                Operator::EQ,
                Specifications::FORMAT_SINGLE,
                Specifications::TYPE_INTEGER
            ),
        ];
    }

    /**
     * @deprecated since 7.2, will be removed in 8.0. Use the constructor directly instead.
     */
    public static function createFromQueryBuilder($target, $operator, $value)
    {
        @trigger_error('The ' . __METHOD__ . ' method is deprecated since version 7.2 and will be removed in 8.0.', E_USER_DEPRECATED);

        return new self($value);
    }
}
