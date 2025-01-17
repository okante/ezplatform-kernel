<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace eZ\Publish\Core\Helper\Tests;

use eZ\Publish\API\Repository\ContentTypeService;
use eZ\Publish\API\Repository\FieldTypeService;
use eZ\Publish\API\Repository\Values\Content\Content as APIContent;
use eZ\Publish\API\Repository\Values\Content\ContentInfo;
use eZ\Publish\API\Repository\Values\Content\Field;
use eZ\Publish\API\Repository\Values\ContentType\ContentType;
use eZ\Publish\API\Repository\Values\ContentType\FieldDefinition;
use eZ\Publish\Core\FieldType\TextLine\Type as TextLineType;
use eZ\Publish\Core\FieldType\TextLine\Value;
use eZ\Publish\Core\Helper\FieldHelper;
use eZ\Publish\Core\Helper\TranslationHelper;
use eZ\Publish\Core\Repository\Values\ContentType\FieldType;
use PHPUnit\Framework\TestCase;

class FieldHelperTest extends TestCase
{
    /** @var \eZ\Publish\Core\Helper\FieldHelper */
    private $fieldHelper;

    /** @var \eZ\Publish\API\Repository\FieldTypeService|\PHPUnit\Framework\MockObject\MockObject */
    private $fieldTypeServiceMock;

    /** @var \eZ\Publish\API\Repository\ContentTypeService|\PHPUnit\Framework\MockObject\MockObject */
    private $contentTypeServiceMock;

    /** @var \eZ\Publish\Core\Helper\TranslationHelper|\PHPUnit\Framework\MockObject\MockObject */
    private $translationHelper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fieldTypeServiceMock = $this->createMock(FieldTypeService::class);
        $this->contentTypeServiceMock = $this->createMock(ContentTypeService::class);
        $this->translationHelper = $this->createMock(TranslationHelper::class);
        $this->fieldHelper = new FieldHelper($this->translationHelper, $this->contentTypeServiceMock, $this->fieldTypeServiceMock);
    }

    public function testIsFieldEmpty()
    {
        $contentTypeId = 123;
        $contentInfo = new ContentInfo(['contentTypeId' => $contentTypeId]);
        $content = $this->createMock(APIContent::class);
        $content
            ->expects($this->any())
            ->method('__get')
            ->with('contentInfo')
            ->will($this->returnValue($contentInfo));

        $fieldDefIdentifier = 'my_field_definition';
        $textLineFT = new TextLineType();
        $emptyValue = $textLineFT->getEmptyValue();
        $emptyField = new Field(['fieldDefIdentifier' => $fieldDefIdentifier, 'value' => $emptyValue]);

        $contentType = $this->createMock(ContentType::class);
        $fieldDefinition = $this->getMockBuilder(FieldDefinition::class)
            ->setConstructorArgs([['fieldTypeIdentifier' => 'ezstring']])
            ->getMockForAbstractClass();
        $contentType
            ->expects($this->once())
            ->method('getFieldDefinition')
            ->with($fieldDefIdentifier)
            ->will($this->returnValue($fieldDefinition));

        $content
            ->expects($this->any())
            ->method('getContentType')
            ->willReturn($contentType);

        $this->translationHelper
            ->expects($this->once())
            ->method('getTranslatedField')
            ->with($content, $fieldDefIdentifier)
            ->will($this->returnValue($emptyField));

        $this->fieldTypeServiceMock
            ->expects($this->any())
            ->method('getFieldType')
            ->with('ezstring')
            ->will($this->returnValue(new FieldType($textLineFT)));

        $this->assertTrue($this->fieldHelper->isFieldEmpty($content, $fieldDefIdentifier));
    }

    public function testIsFieldNotEmpty()
    {
        $contentTypeId = 123;
        $contentInfo = new ContentInfo(['contentTypeId' => $contentTypeId]);
        $content = $this->createMock(APIContent::class);
        $content
            ->expects($this->any())
            ->method('__get')
            ->with('contentInfo')
            ->will($this->returnValue($contentInfo));

        $fieldDefIdentifier = 'my_field_definition';
        $textLineFT = new TextLineType();
        $nonEmptyValue = new Value('Vive le sucre !!!');
        $emptyField = new Field(['fieldDefIdentifier' => 'ezstring', 'value' => $nonEmptyValue]);

        $contentType = $this->createMock(ContentType::class);
        $fieldDefinition = $this->getMockBuilder(FieldDefinition::class)
            ->setConstructorArgs([['fieldTypeIdentifier' => 'ezstring']])
            ->getMockForAbstractClass();
        $contentType
            ->expects($this->once())
            ->method('getFieldDefinition')
            ->with($fieldDefIdentifier)
            ->will($this->returnValue($fieldDefinition));

        $content
            ->expects($this->any())
            ->method('getContentType')
            ->willReturn($contentType);

        $this->translationHelper
            ->expects($this->once())
            ->method('getTranslatedField')
            ->with($content, $fieldDefIdentifier)
            ->will($this->returnValue($emptyField));

        $this->fieldTypeServiceMock
            ->expects($this->any())
            ->method('getFieldType')
            ->with('ezstring')
            ->will($this->returnValue(new FieldType($textLineFT)));

        $this->assertFalse($this->fieldHelper->isFieldEmpty($content, $fieldDefIdentifier));
    }
}
