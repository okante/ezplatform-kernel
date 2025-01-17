<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace eZ\Publish\SPI\FieldType;

use eZ\Publish\SPI\Persistence\Content\Field;
use eZ\Publish\SPI\Persistence\Content\VersionInfo;

/**
 * Field Type External Storage gateway base class.
 *
 * @template T of \eZ\Publish\SPI\FieldType\StorageGateway
 */
abstract class GatewayBasedStorage implements FieldStorage
{
    /**
     * Field Type External Storage Gateway.
     *
     * @var \eZ\Publish\SPI\FieldType\StorageGateway
     * @phpstan-var T
     */
    protected $gateway;

    /**
     * @param \eZ\Publish\SPI\FieldType\StorageGateway $gateway
     * @phpstan-param T $gateway
     */
    public function __construct(StorageGateway $gateway)
    {
        $this->gateway = $gateway;
    }

    /**
     * This method is used exclusively by Legacy Storage to copy external data of existing field in main language to
     * the untranslatable field not passed in create or update struct, but created implicitly in storage layer.
     *
     * By default the method falls back to the {@link \eZ\Publish\SPI\FieldType\FieldStorage::storeFieldData()}.
     * External storages implement this method as needed.
     *
     * @param \eZ\Publish\SPI\Persistence\Content\VersionInfo $versionInfo
     * @param \eZ\Publish\SPI\Persistence\Content\Field $field
     * @param \eZ\Publish\SPI\Persistence\Content\Field $originalField
     * @param array $context
     *
     * @return bool|null Same as {@link \eZ\Publish\SPI\FieldType\FieldStorage::storeFieldData()}.
     */
    public function copyLegacyField(VersionInfo $versionInfo, Field $field, Field $originalField, array $context)
    {
        return $this->storeFieldData($versionInfo, $field, $context);
    }
}
