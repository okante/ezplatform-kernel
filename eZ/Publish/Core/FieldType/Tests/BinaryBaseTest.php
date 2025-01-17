<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace eZ\Publish\Core\FieldType\Tests;

use eZ\Publish\Core\Base\Exceptions\InvalidArgumentException;
use eZ\Publish\Core\FieldType\Validator\FileExtensionBlackListValidator;
use eZ\Publish\Core\FieldType\Value;
use eZ\Publish\Core\MVC\ConfigResolverInterface;

/**
 * Base class for binary field types.
 *
 * @group fieldType
 */
abstract class BinaryBaseTest extends FieldTypeTest
{
    protected $blackListedExtensions = [
        'php',
        'php3',
        'phar',
        'phpt',
        'pht',
        'phtml',
        'pgif',
    ];

    protected function getValidatorConfigurationSchemaExpectation()
    {
        return [
            'FileSizeValidator' => [
                'maxFileSize' => [
                    'type' => 'int',
                    'default' => null,
                ],
            ],
        ];
    }

    protected function getConfigResolverMock()
    {
        $configResolver = $this
            ->createMock(ConfigResolverInterface::class);

        $configResolver
            ->method('getParameter')
            ->with('io.file_storage.file_type_blacklist')
            ->willReturn($this->blackListedExtensions);

        return $configResolver;
    }

    protected function getBlackListValidatorMock()
    {
        return $this
            ->getMockBuilder(FileExtensionBlackListValidator::class)
            ->setConstructorArgs([
                $this->getConfigResolverMock(),
            ])
            ->setMethods(null)
            ->getMock();
    }

    protected function getSettingsSchemaExpectation()
    {
        return [];
    }

    public function provideInvalidInputForAcceptValue()
    {
        return [
            [
                $this->getMockForAbstractClass(Value::class),
                InvalidArgumentException::class,
            ],
            [
                ['id' => '/foo/bar'],
                InvalidArgumentException::class,
            ],
        ];
    }

    /**
     * Provide data sets with validator configurations which are considered
     * valid by the {@link validateValidatorConfiguration()} method.
     *
     * Returns an array of data provider sets with a single argument: A valid
     * set of validator configurations.
     *
     * For example:
     *
     * <code>
     *  return array(
     *      array(
     *          array(),
     *      ),
     *      array(
     *          array(
     *              'IntegerValueValidator' => array(
     *                  'minIntegerValue' => 0,
     *                  'maxIntegerValue' => 23,
     *              )
     *          )
     *      ),
     *      // ...
     *  );
     * </code>
     *
     * @return array
     */
    public function provideValidValidatorConfiguration()
    {
        return [
            [
                [],
            ],
            [
                [
                    'FileSizeValidator' => [
                        'maxFileSize' => 2342,
                    ],
                ],
            ],
            [
                [
                    'FileSizeValidator' => [
                        'maxFileSize' => null,
                    ],
                ],
            ],
        ];
    }

    /**
     * Provide data sets with validator configurations which are considered
     * invalid by the {@link validateValidatorConfiguration()} method. The
     * method must return a non-empty array of validation errors when receiving
     * one of the provided values.
     *
     * Returns an array of data provider sets with a single argument: A valid
     * set of validator configurations.
     *
     * For example:
     *
     * <code>
     *  return array(
     *      array(
     *          array(
     *              'NonExistentValidator' => array(),
     *          ),
     *      ),
     *      array(
     *          array(
     *              // Typos
     *              'InTEgervALUeVALIdator' => array(
     *                  'minIntegerValue' => 0,
     *                  'maxIntegerValue' => 23,
     *              )
     *          )
     *      ),
     *      array(
     *          array(
     *              'IntegerValueValidator' => array(
     *                  // Incorrect value types
     *                  'minIntegerValue' => true,
     *                  'maxIntegerValue' => false,
     *              )
     *          )
     *      ),
     *      // ...
     *  );
     * </code>
     *
     * @return array
     */
    public function provideInvalidValidatorConfiguration()
    {
        return [
            [
                [
                    'NonExistingValidator' => [],
                ],
            ],
            [
                // maxFileSize must be int or bool
                [
                    'FileSizeValidator' => [
                        'maxFileSize' => 'foo',
                    ],
                ],
            ],
            [
                // maxFileSize is required for this validator
                [
                    'FileSizeValidator' => [],
                ],
            ],
        ];
    }
}
