<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace eZ\Publish\Core\Base\Exceptions;

use eZ\Publish\API\Repository\Exceptions\ContentFieldValidationException as APIContentFieldValidationException;
use eZ\Publish\Core\Base\Translatable;
use eZ\Publish\Core\Base\TranslatableBase;

/**
 * This Exception is thrown on create or update content one or more given fields are not valid.
 */
class ContentFieldValidationException extends APIContentFieldValidationException implements Translatable
{
    use TranslatableBase;

    /**
     * Contains an array of field ValidationError objects indexed with FieldDefinition id and language code.
     *
     * Example:
     * <code>
     *  $fieldErrors = $exception->getFieldErrors();
     *  $fieldErrors["43"]["eng-GB"]->getTranslatableMessage();
     * </code>
     *
     * @var array<array-key, array<string, \eZ\Publish\Core\FieldType\ValidationError>>
     */
    protected $errors;

    /**
     * Generates: Content fields did not validate.
     *
     * Also sets the given $fieldErrors to the internal property, retrievable by getFieldErrors()
     *
     * @param array<array-key, array<string, \eZ\Publish\Core\FieldType\ValidationError>> $errors
     */
    public function __construct(array $errors)
    {
        $this->errors = $errors;
        $this->setMessageTemplate('Content Fields did not validate');
        parent::__construct($this->getBaseTranslation());
    }

    /**
     * Returns an array of field validation error messages.
     *
     * @return array<array-key, array<string, \eZ\Publish\Core\FieldType\ValidationError>>
     */
    public function getFieldErrors()
    {
        return $this->errors;
    }
}
