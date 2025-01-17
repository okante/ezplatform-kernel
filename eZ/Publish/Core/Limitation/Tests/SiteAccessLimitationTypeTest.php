<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace eZ\Publish\Core\Limitation\Tests;

use eZ\Publish\API\Repository\Values\User\Limitation;
use eZ\Publish\API\Repository\Values\User\Limitation\ObjectStateLimitation;
use eZ\Publish\API\Repository\Values\User\Limitation\SiteAccessLimitation;
use eZ\Publish\API\Repository\Values\ValueObject;
use eZ\Publish\Core\Limitation\SiteAccessLimitationType;
use eZ\Publish\Core\MVC\Symfony\SiteAccess;

/**
 * Test Case for LimitationType.
 */
class SiteAccessLimitationTypeTest extends Base
{
    /** @var \eZ\Publish\Core\MVC\Symfony\SiteAccess\SiteAccessServiceInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $siteAccessServiceMock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siteAccessServiceMock = $this->createMock(SiteAccess\SiteAccessServiceInterface::class);
        $this->siteAccessServiceMock
            ->method('getAll')
            ->willReturn([
                new SiteAccess('ezdemo_site'),
                new SiteAccess('eng'),
                new SiteAccess('fre'),
            ]);
    }

    /**
     * @return \eZ\Publish\Core\Limitation\SiteAccessLimitationType
     */
    public function testConstruct()
    {
        return new SiteAccessLimitationType(
            $this->siteAccessServiceMock
        );
    }

    /**
     * @return array
     */
    public function providerForTestAcceptValue()
    {
        return [
            [new SiteAccessLimitation()],
            [new SiteAccessLimitation([])],
            [
                new SiteAccessLimitation(
                    [
                        'limitationValues' => [
                            sprintf('%u', crc32('ezdemo_site')),
                            sprintf('%u', crc32('eng')),
                            sprintf('%u', crc32('fre')),
                        ],
                    ]
                ),
            ],
            [
                new SiteAccessLimitation(
                    [
                        'limitationValues' => [
                            crc32('ezdemo_site'),
                            crc32('eng'),
                            crc32('fre'),
                        ],
                    ]
                ),
            ],
        ];
    }

    /**
     * @depends testConstruct
     * @dataProvider providerForTestAcceptValue
     *
     * @param \eZ\Publish\API\Repository\Values\User\Limitation\SiteAccessLimitation $limitation
     * @param \eZ\Publish\Core\Limitation\SiteAccessLimitationType $limitationType
     */
    public function testAcceptValue(SiteAccessLimitation $limitation, SiteAccessLimitationType $limitationType)
    {
        $limitationType->acceptValue($limitation);
    }

    /**
     * @return array
     */
    public function providerForTestAcceptValueException()
    {
        return [
            [new ObjectStateLimitation()],
            [new SiteAccessLimitation(['limitationValues' => [true]])],
        ];
    }

    /**
     * @depends testConstruct
     * @dataProvider providerForTestAcceptValueException
     *
     * @param \eZ\Publish\API\Repository\Values\User\Limitation $limitation
     * @param \eZ\Publish\Core\Limitation\SiteAccessLimitationType $limitationType
     */
    public function testAcceptValueException(Limitation $limitation, SiteAccessLimitationType $limitationType)
    {
        $this->expectException(\eZ\Publish\API\Repository\Exceptions\InvalidArgumentException::class);

        $limitationType->acceptValue($limitation);
    }

    /**
     * @return array
     */
    public function providerForTestValidateError()
    {
        return [
            [new SiteAccessLimitation(), 0],
            [new SiteAccessLimitation([]), 0],
            [
                new SiteAccessLimitation(
                    [
                        'limitationValues' => ['2339567439'],
                    ]
                ),
                1,
            ],
            [new SiteAccessLimitation(['limitationValues' => [true]]), 1],
            [
                new SiteAccessLimitation(
                    [
                        'limitationValues' => [
                            '2339567439',
                            false,
                        ],
                    ]
                ),
                2,
            ],
            [
                new SiteAccessLimitation(
                    [
                        'limitationValues' => [
                            sprintf('%u', crc32('ezdemo_site')),
                            sprintf('%u', crc32('eng')),
                            sprintf('%u', crc32('fre')),
                        ],
                    ]
                ),
                0,
            ],
        ];
    }

    /**
     * @dataProvider providerForTestValidateError
     * @depends testConstruct
     *
     * @param \eZ\Publish\API\Repository\Values\User\Limitation\SiteAccessLimitation $limitation
     * @param int $errorCount
     * @param \eZ\Publish\Core\Limitation\SiteAccessLimitationType $limitationType
     */
    public function testValidateError(SiteAccessLimitation $limitation, $errorCount, SiteAccessLimitationType $limitationType)
    {
        $validationErrors = $limitationType->validate($limitation);
        self::assertCount($errorCount, $validationErrors);
    }

    /**
     * @depends testConstruct
     *
     * @param \eZ\Publish\Core\Limitation\SiteAccessLimitationType $limitationType
     */
    public function testBuildValue(SiteAccessLimitationType $limitationType)
    {
        $expected = ['test', 'test' => 9];
        $value = $limitationType->buildValue($expected);

        self::assertInstanceOf('\eZ\Publish\API\Repository\Values\User\Limitation\SiteAccessLimitation', $value);
        self::assertIsArray($value->limitationValues);
        self::assertEquals($expected, $value->limitationValues);
    }

    /**
     * @return array
     */
    public function providerForTestEvaluate()
    {
        return [
            // SiteAccess, no access
            [
                'limitation' => new SiteAccessLimitation(),
                'object' => new SiteAccess('behat_site'),
                'expected' => false,
            ],
            // SiteAccess, no access
            [
                'limitation' => new SiteAccessLimitation(['limitationValues' => ['2339567439']]),
                'object' => new SiteAccess('behat_site'),
                'expected' => false,
            ],
            // SiteAccess, with access
            [
                'limitation' => new SiteAccessLimitation(['limitationValues' => ['1817462202']]),
                'object' => new SiteAccess('behat_site'),
                'expected' => true,
            ],
        ];
    }

    /**
     * @depends testConstruct
     * @dataProvider providerForTestEvaluate
     */
    public function testEvaluate(
        SiteAccessLimitation $limitation,
        ValueObject $object,
        $expected,
        SiteAccessLimitationType $limitationType
    ) {
        $userMock = $this->getUserMock();
        $userMock->expects($this->never())->method($this->anything());

        $value = $limitationType->evaluate(
            $limitation,
            $userMock,
            $object
        );

        self::assertIsBool($value);
        self::assertEquals($expected, $value);
    }

    /**
     * @return array
     */
    public function providerForTestEvaluateInvalidArgument()
    {
        return [
            // invalid limitation
            [
                'limitation' => new ObjectStateLimitation(),
                'object' => new SiteAccess('test'),
            ],
            // invalid object
            [
                'limitation' => new SiteAccessLimitation(),
                'object' => new ObjectStateLimitation(),
            ],
        ];
    }

    /**
     * @depends testConstruct
     * @dataProvider providerForTestEvaluateInvalidArgument
     */
    public function testEvaluateInvalidArgument(
        Limitation $limitation,
        ValueObject $object,
        SiteAccessLimitationType $limitationType
    ) {
        $this->expectException(\eZ\Publish\API\Repository\Exceptions\InvalidArgumentException::class);

        $userMock = $this->getUserMock();
        $userMock->expects($this->never())->method($this->anything());

        $limitationType->evaluate(
            $limitation,
            $userMock,
            $object
        );
    }

    /**
     * @depends testConstruct
     *
     * @param \eZ\Publish\Core\Limitation\SiteAccessLimitationType $limitationType
     */
    public function testGetCriterion(SiteAccessLimitationType $limitationType)
    {
        $this->expectException(\eZ\Publish\API\Repository\Exceptions\NotImplementedException::class);

        $limitationType->getCriterion(new SiteAccessLimitation(), $this->getUserMock());
    }

    /**
     * @depends testConstruct
     *
     * @param \eZ\Publish\Core\Limitation\SiteAccessLimitationType $limitationType
     */
    public function testValueSchema(SiteAccessLimitationType $limitationType)
    {
        self::markTestSkipped('Method valueSchema() is not implemented');
    }

    /**
     * @depends testConstruct
     */
    public function testGenerateSiteAccessValue(SiteAccessLimitationType $limitationType): void
    {
        self::assertSame('341347141', $limitationType->generateSiteAccessValue('ger'));
        self::assertSame('2582995467', $limitationType->generateSiteAccessValue('eng'));
        self::assertSame('1817462202', $limitationType->generateSiteAccessValue('behat_site'));
    }
}
