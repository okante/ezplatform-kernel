<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace eZ\Bundle\EzPublishCoreBundle\Imagine\Filter\Loader;

use Imagine\Exception\InvalidArgumentException;
use Imagine\Image\ImageInterface;

/**
 * Filter loader for geometry/scalewidthdownonly filter.
 * Proxy to ThumbnailFilterLoader.
 */
class ScaleWidthDownOnlyFilterLoader extends FilterLoaderWrapped
{
    public function load(ImageInterface $image, array $options = [])
    {
        if (empty($options)) {
            throw new InvalidArgumentException('Missing width option');
        }

        return $this->innerLoader->load(
            $image,
            [
                'size' => [$options[0], null],
                'mode' => 'inset',
            ]
        );
    }
}
