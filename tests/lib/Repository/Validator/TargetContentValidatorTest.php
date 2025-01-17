<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Core\Repository\Validator;

use eZ\Publish\API\Repository\ContentService;
use eZ\Publish\API\Repository\ContentTypeService;
use eZ\Publish\API\Repository\Exceptions\NotFoundException;
use eZ\Publish\API\Repository\Values\Content\ContentInfo;
use eZ\Publish\Core\FieldType\ValidationError;
use eZ\Publish\Core\Repository\Values\ContentType\ContentType;
use Ibexa\Core\Repository\Validator\TargetContentValidator;
use PHPUnit\Framework\TestCase;

final class TargetContentValidatorTest extends TestCase
{
    /** @var \eZ\Publish\API\Repository\ContentService|\PHPUnit_Framework_MockObject_MockObject */
    private $contentService;

    /** @var \eZ\Publish\API\Repository\ContentTypeService|\PHPUnit_Framework_MockObject_MockObject */
    private $contentTypeService;

    /** @var \Ibexa\Core\Repository\Validator\TargetContentValidator */
    private $targetContentValidator;

    public function setUp(): void
    {
        $this->contentService = $this->createMock(ContentService::class);
        $this->contentTypeService = $this->createMock(ContentTypeService::class);

        $this->targetContentValidator = new TargetContentValidator(
            $this->contentService,
            $this->contentTypeService
        );
    }

    public function testValidateWithValidContent(): void
    {
        $contentId = 2;
        $allowedContentTypes = ['article'];

        $this->setupContentTypeValidation($contentId);

        $validationError = $this->targetContentValidator->validate($contentId, $allowedContentTypes);

        self::assertNull($validationError);
    }

    public function testValidateWithInvalidContentType(): void
    {
        $contentId = 2;
        $allowedContentTypes = ['folder'];

        $this->setupContentTypeValidation($contentId);

        $validationError = $this->targetContentValidator->validate($contentId, $allowedContentTypes);

        self::assertInstanceOf(ValidationError::class, $validationError);
    }

    private function setupContentTypeValidation(int $contentId): void
    {
        $contentTypeId = 55;
        $contentInfo = new ContentInfo(['id' => $contentId, 'contentTypeId' => $contentTypeId]);
        $contentType = new ContentType(['identifier' => 'article']);

        $this->contentService
            ->expects(self::once())
            ->method('loadContentInfo')
            ->with($contentId)
            ->willReturn($contentInfo);

        $this->contentTypeService
            ->expects(self::once())
            ->method('loadContentType')
            ->with($contentInfo->contentTypeId)
            ->willReturn($contentType);
    }

    public function testValidateWithInvalidContentId(): void
    {
        $id = 0;

        $this->contentService
            ->expects(self::once())
            ->method('loadContentInfo')
            ->with($id)
            ->willThrowException($this->createMock(NotFoundException::class));

        $validationError = $this->targetContentValidator->validate($id);

        self::assertInstanceOf(ValidationError::class, $validationError);
    }
}
