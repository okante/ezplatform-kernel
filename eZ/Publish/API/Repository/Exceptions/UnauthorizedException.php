<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace eZ\Publish\API\Repository\Exceptions;

use Exception;
use eZ\Publish\API\Repository\Exceptions\Exception as RepositoryException;

/**
 * This Exception is thrown if the user has is not allowed to perform a service operation.
 */
abstract class UnauthorizedException extends Exception implements RepositoryException
{
}
