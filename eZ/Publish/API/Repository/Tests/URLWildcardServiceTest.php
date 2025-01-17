<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace eZ\Publish\API\Repository\Tests;

use eZ\Publish\API\Repository\Values\Content\URLWildcard;
use eZ\Publish\API\Repository\Values\Content\URLWildcardTranslationResult;
use eZ\Publish\API\Repository\Values\Content\URLWildcardUpdateStruct;

/**
 * Test case for operations in the URLWildcardService.
 *
 * @see eZ\Publish\API\Repository\URLWildcardService
 * @group url-wildcard
 */
class URLWildcardServiceTest extends BaseTest
{
    /**
     * Test for the create() method.
     *
     * @return \eZ\Publish\API\Repository\Values\Content\URLWildcard
     *
     * @see \eZ\Publish\API\Repository\URLWildcardService::create()
     */
    public function testCreate()
    {
        $repository = $this->getRepository();

        /* BEGIN: Use Case */
        $urlWildcardService = $repository->getURLWildcardService();

        // Create a new url wildcard
        $urlWildcard = $urlWildcardService->create('/articles/*', '/content/{1}');
        /* END: Use Case */

        $this->assertInstanceOf(
            '\\eZ\\Publish\\API\\Repository\\Values\\Content\\URLWildcard',
            $urlWildcard
        );

        return $urlWildcard;
    }

    /**
     * Test for the create() method.
     *
     * @param \eZ\Publish\API\Repository\Values\Content\URLWildcard $urlWildcard
     *
     * @see \eZ\Publish\API\Repository\URLWildcardService::create()
     * @depends eZ\Publish\API\Repository\Tests\URLWildcardServiceTest::testCreate
     */
    public function testCreateSetsIdPropertyOnURLWildcard(URLWildcard $urlWildcard)
    {
        $this->assertNotNull($urlWildcard->id);
    }

    /**
     * Test for the create() method.
     *
     * @param \eZ\Publish\API\Repository\Values\Content\URLWildcard $urlWildcard
     *
     * @see \eZ\Publish\API\Repository\URLWildcardService::create()
     * @depends eZ\Publish\API\Repository\Tests\URLWildcardServiceTest::testCreate
     */
    public function testCreateSetsPropertiesOnURLWildcard(URLWildcard $urlWildcard)
    {
        $this->assertPropertiesCorrect(
            [
                'sourceUrl' => '/articles/*',
                'destinationUrl' => '/content/{1}',
                'forward' => false,
            ],
            $urlWildcard
        );
    }

    /**
     * Test for the create() method.
     *
     * @see \eZ\Publish\API\Repository\URLWildcardService::create()
     * @depends eZ\Publish\API\Repository\Tests\URLWildcardServiceTest::testCreate
     */
    public function testCreateWithOptionalForwardParameter()
    {
        $repository = $this->getRepository();

        /* BEGIN: Use Case */
        $urlWildcardService = $repository->getURLWildcardService();

        // Create a new url wildcard
        $urlWildcard = $urlWildcardService->create('/articles/*', '/content/{1}', true);
        /* END: Use Case */

        $this->assertPropertiesCorrect(
            [
                'sourceUrl' => '/articles/*',
                'destinationUrl' => '/content/{1}',
                'forward' => true,
            ],
            $urlWildcard
        );
    }

    /**
     * Test for the create() method.
     *
     * @see \eZ\Publish\API\Repository\URLWildcardService::create()
     * @depends eZ\Publish\API\Repository\Tests\URLWildcardServiceTest::testCreate
     */
    public function testCreateThrowsInvalidArgumentExceptionOnDuplicateSourceUrl()
    {
        $this->expectException(\eZ\Publish\API\Repository\Exceptions\InvalidArgumentException::class);

        $repository = $this->getRepository();

        /* BEGIN: Use Case */
        $urlWildcardService = $repository->getURLWildcardService();

        // Create a new url wildcard
        $urlWildcardService->create('/articles/*', '/content/{1}', true);

        // This call will fail with an InvalidArgumentException because the
        // sourceUrl '/articles/*' already exists.
        $urlWildcardService->create('/articles/*', '/content/data/{1}');
        /* END: Use Case */
    }

    /**
     * Test for the create() method.
     *
     * @see \eZ\Publish\API\Repository\URLWildcardService::create()
     * @depends eZ\Publish\API\Repository\Tests\URLWildcardServiceTest::testCreate
     */
    public function testCreateThrowsContentValidationExceptionWhenPatternsAndPlaceholdersNotMatch()
    {
        $this->expectException(\eZ\Publish\API\Repository\Exceptions\ContentValidationException::class);

        $repository = $this->getRepository();

        /* BEGIN: Use Case */
        $urlWildcardService = $repository->getURLWildcardService();

        // This call will fail with a ContentValidationException because the
        // number of patterns '*' does not match the number of {\d} placeholders
        $urlWildcardService->create('/articles/*', '/content/{1}/year{2}');
        /* END: Use Case */
    }

    /**
     * Test for the create() method.
     *
     * @see \eZ\Publish\API\Repository\URLWildcardService::create()
     * @depends eZ\Publish\API\Repository\Tests\URLWildcardServiceTest::testCreate
     */
    public function testCreateThrowsContentValidationExceptionWhenPlaceholdersNotValidNumberSequence()
    {
        $this->expectException(\eZ\Publish\API\Repository\Exceptions\ContentValidationException::class);

        $repository = $this->getRepository();

        /* BEGIN: Use Case */
        $urlWildcardService = $repository->getURLWildcardService();

        // This call will fail with a ContentValidationException because the
        // number of patterns '*' does not match the number of {\d} placeholders
        $urlWildcardService->create('/articles/*/*/*', '/content/{1}/year/{2}/{4}');
        /* END: Use Case */
    }

    /**
     * Test for the load() method.
     *
     * @return \eZ\Publish\API\Repository\Values\Content\URLWildcard
     *
     * @see \eZ\Publish\API\Repository\URLWildcardService::load()
     * @depends eZ\Publish\API\Repository\Tests\URLWildcardServiceTest::testCreate
     */
    public function testLoad()
    {
        $repository = $this->getRepository();

        /* BEGIN: Use Case */
        $urlWildcardService = $repository->getURLWildcardService();

        // Create a new url wildcard
        $urlWildcardId = $urlWildcardService->create('/articles/*', '/content/{1}', true)->id;

        // Load newly created url wildcard
        $urlWildcard = $urlWildcardService->load($urlWildcardId);
        /* END: Use Case */

        $this->assertInstanceOf(
            '\\eZ\\Publish\\API\\Repository\\Values\\Content\\URLWildcard',
            $urlWildcard
        );

        return $urlWildcard;
    }

    /**
     * Test for the load() method.
     *
     * @param \eZ\Publish\API\Repository\Values\Content\URLWildcard $urlWildcard
     *
     * @see \eZ\Publish\API\Repository\URLWildcardService::load()
     * @depends eZ\Publish\API\Repository\Tests\URLWildcardServiceTest::testLoad
     */
    public function testLoadSetsPropertiesOnURLWildcard(URLWildcard $urlWildcard)
    {
        $this->assertPropertiesCorrect(
            [
                'sourceUrl' => '/articles/*',
                'destinationUrl' => '/content/{1}',
                'forward' => true,
            ],
            $urlWildcard
        );
    }

    /**
     * Test for the load() method.
     *
     * @param \eZ\Publish\API\Repository\Values\Content\URLWildcard $urlWildcard
     *
     * @see \eZ\Publish\API\Repository\URLWildcardService::load()
     * @depends eZ\Publish\API\Repository\Tests\URLWildcardServiceTest::testLoad
     */
    public function testLoadThrowsNotFoundException(URLWildcard $urlWildcard)
    {
        $this->expectException(\eZ\Publish\API\Repository\Exceptions\NotFoundException::class);

        $repository = $this->getRepository();

        /* BEGIN: Use Case */
        $urlWildcardService = $repository->getURLWildcardService();

        // This call will fail with a NotFoundException
        $urlWildcardService->load(42);
        /* END: Use Case */
    }

    /**
     * @see \eZ\Publish\API\Repository\URLWildcardService::update
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\ForbiddenException
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     */
    public function testUpdate(): void
    {
        $repository = $this->getRepository();

        $urlWildcardService = $repository->getURLWildcardService();

        $urlWildcard = $urlWildcardService->create(
            '/articles/*',
            '/content/{1}',
            true
        );

        $updateStruct = new URLWildcardUpdateStruct();
        $updateStruct->sourceUrl = '/articles/new/*';
        $updateStruct->destinationUrl = '/content/new/*';
        $updateStruct->forward = false;

        $urlWildcardService->update($urlWildcard, $updateStruct);

        $urlWildcardUpdated = $urlWildcardService->load($urlWildcard->id);

        $this->assertEquals(
            [
                $urlWildcard->id,
                $updateStruct->sourceUrl,
                $updateStruct->destinationUrl,
                $updateStruct->forward,
            ],
            [
                $urlWildcardUpdated->id,
                $urlWildcardUpdated->sourceUrl,
                $urlWildcardUpdated->destinationUrl,
                $urlWildcardUpdated->forward,
            ]
        );
    }

    /**
     * Test for the remove() method.
     *
     * @see \eZ\Publish\API\Repository\URLWildcardService::remove()
     * @depends eZ\Publish\API\Repository\Tests\URLWildcardServiceTest::testLoad
     */
    public function testRemove()
    {
        $this->expectException(\eZ\Publish\API\Repository\Exceptions\NotFoundException::class);

        $repository = $this->getRepository();

        /* BEGIN: Use Case */
        $urlWildcardService = $repository->getURLWildcardService();

        // Create a new url wildcard
        $urlWildcard = $urlWildcardService->create('/articles/*', '/content/{1}', true);

        // Store wildcard url for later reuse
        $urlWildcardId = $urlWildcard->id;

        // Remove the newly created url wildcard
        $urlWildcardService->remove($urlWildcard);

        // This call will fail with a NotFoundException
        $urlWildcardService->load($urlWildcardId);
        /* END: Use Case */
    }

    /**
     * Test for the loadAll() method.
     *
     * @see \eZ\Publish\API\Repository\URLWildcardService::loadAll()
     * @depends eZ\Publish\API\Repository\Tests\URLWildcardServiceTest::testCreate
     */
    public function testLoadAll()
    {
        $repository = $this->getRepository();

        /* BEGIN: Use Case */
        $urlWildcardService = $repository->getURLWildcardService();

        // Create new url wildcards
        $urlWildcardOne = $urlWildcardService->create('/articles/*', '/content/{1}', true);
        $urlWildcardTwo = $urlWildcardService->create('/news/*', '/content/{1}', true);

        // Load all available url wildcards
        $allUrlWildcards = $urlWildcardService->loadAll();
        /* END: Use Case */

        $this->assertEquals(
            [
                $urlWildcardOne,
                $urlWildcardTwo,
            ],
            $allUrlWildcards
        );
    }

    /**
     * Test for the loadAll() method.
     *
     * @see \eZ\Publish\API\Repository\URLWildcardService::loadAll()
     * @depends eZ\Publish\API\Repository\Tests\URLWildcardServiceTest::testLoadAll
     */
    public function testLoadAllWithOffsetParameter()
    {
        $repository = $this->getRepository();

        /* BEGIN: Use Case */
        $urlWildcardService = $repository->getURLWildcardService();

        // Create new url wildcards
        $urlWildcardOne = $urlWildcardService->create('/articles/*', '/content/{1}', true);
        $urlWildcardTwo = $urlWildcardService->create('/news/*', '/content/{1}', true);

        // Load all available url wildcards
        $allUrlWildcards = $urlWildcardService->loadAll(1);
        /* END: Use Case */

        $this->assertEquals([$urlWildcardTwo], $allUrlWildcards);
    }

    /**
     * Test for the loadAll() method.
     *
     * @see \eZ\Publish\API\Repository\URLWildcardService::loadAll()
     * @depends eZ\Publish\API\Repository\Tests\URLWildcardServiceTest::testLoadAll
     */
    public function testLoadAllWithOffsetAndLimitParameter()
    {
        $repository = $this->getRepository();

        /* BEGIN: Use Case */
        $urlWildcardService = $repository->getURLWildcardService();

        // Create new url wildcards
        $urlWildcardOne = $urlWildcardService->create('/articles/*', '/content/{1}');
        $urlWildcardTwo = $urlWildcardService->create('/news/*', '/content/{1}');

        // Load all available url wildcards
        $allUrlWildcards = $urlWildcardService->loadAll(0, 1);
        /* END: Use Case */

        $this->assertEquals([$urlWildcardOne], $allUrlWildcards);
    }

    /**
     * Test for the loadAll() method.
     *
     * @see \eZ\Publish\API\Repository\URLWildcardService::loadAll()
     * @depends eZ\Publish\API\Repository\Tests\URLWildcardServiceTest::testLoadAll
     */
    public function testLoadAllReturnsEmptyArrayByDefault()
    {
        $repository = $this->getRepository();

        /* BEGIN: Use Case */
        $urlWildcardService = $repository->getURLWildcardService();

        // Load all available url wildcards
        $allUrlWildcards = $urlWildcardService->loadAll();
        /* END: Use Case */

        $this->assertSame([], $allUrlWildcards);
    }

    /**
     * Test for the translate() method.
     *
     * @return \eZ\Publish\API\Repository\Values\Content\URLWildcardTranslationResult
     *
     * @see \eZ\Publish\API\Repository\URLWildcardService::translate()
     * @depends eZ\Publish\API\Repository\Tests\URLWildcardServiceTest::testCreate
     */
    public function testTranslate()
    {
        $repository = $this->getRepository();

        /* BEGIN: Use Case */
        $urlWildcardService = $repository->getURLWildcardService();

        // Create a new url wildcard
        $urlWildcardService->create('/articles/*', '/content/{1}');

        // Translate a given url
        $result = $urlWildcardService->translate('/articles/2012/05/sindelfingen');
        /* END: Use Case */

        $this->assertInstanceOf(
            '\\eZ\\Publish\\API\\Repository\\Values\\Content\\URLWildcardTranslationResult',
            $result
        );

        return $result;
    }

    /**
     * Test for the translate() method.
     *
     * @param \eZ\Publish\API\Repository\Values\Content\URLWildcardTranslationResult $result
     *
     * @see \eZ\Publish\API\Repository\URLWildcardService::translate()
     * @depends eZ\Publish\API\Repository\Tests\URLWildcardServiceTest::testTranslate
     */
    public function testTranslateSetsPropertiesOnTranslationResult(URLWildcardTranslationResult $result)
    {
        $this->assertPropertiesCorrect(
            [
                'uri' => '/content/2012/05/sindelfingen',
                'forward' => false,
            ],
            $result
        );
    }

    /**
     * Test for the translate() method.
     *
     * @see \eZ\Publish\API\Repository\URLWildcardService::translate()
     * @depends eZ\Publish\API\Repository\Tests\URLWildcardServiceTest::testTranslate
     */
    public function testTranslateWithForwardSetToTrue()
    {
        $repository = $this->getRepository();

        /* BEGIN: Use Case */
        $urlWildcardService = $repository->getURLWildcardService();

        // Create a new url wildcard
        $urlWildcardService->create('/articles/*/05/*', '/content/{2}/year/{1}', true);

        // Translate a given url
        $result = $urlWildcardService->translate('/articles/2012/05/sindelfingen');
        /* END: Use Case */

        $this->assertPropertiesCorrect(
            [
                'uri' => '/content/sindelfingen/year/2012',
                'forward' => true,
            ],
            $result
        );
    }

    /**
     * Test for the translate() method.
     *
     * @see \eZ\Publish\API\Repository\URLWildcardService::translate()
     * @depends eZ\Publish\API\Repository\Tests\URLWildcardServiceTest::testTranslate
     */
    public function testTranslateReturnsLongestMatchingWildcard()
    {
        $repository = $this->getRepository();

        /* BEGIN: Use Case */
        $urlWildcardService = $repository->getURLWildcardService();

        // Create new url wildcards
        $urlWildcardService->create('/articles/*/05/*', '/content/{2}/year/{1}');
        $urlWildcardService->create('/articles/*/05/sindelfingen/*', '/content/{2}/bar/{1}');

        // Translate a given url
        $result = $urlWildcardService->translate('/articles/2012/05/sindelfingen/42');
        /* END: Use Case */

        $this->assertEquals('/content/42/bar/2012', $result->uri);
    }

    /**
     * Test for the translate() method.
     *
     * @return \eZ\Publish\API\Repository\Values\Content\URLAlias
     *
     * @see \eZ\Publish\API\Repository\URLWildcardService::translate()
     * @depends eZ\Publish\API\Repository\Tests\URLWildcardServiceTest::testTranslate
     */
    public function testTranslateThrowsNotFoundExceptionWhenNotAliasOrWildcardMatches()
    {
        $this->expectException(\eZ\Publish\API\Repository\Exceptions\NotFoundException::class);

        $repository = $this->getRepository();

        /* BEGIN: Use Case */
        $urlWildcardService = $repository->getURLWildcardService();

        // This call will fail with a NotFoundException because no wildcard or
        // url alias matches against the given url.
        $urlWildcardService->translate('/sindelfingen');
        /* END: Use Case */
    }

    public function testCountAllReturnsZeroByDefault(): void
    {
        $repository = $this->getRepository();
        $urlWildcardService = $repository->getURLWildcardService();

        $this->assertSame(0, $urlWildcardService->countAll());
    }

    public function testCountAll(): void
    {
        $repository = $this->getRepository();
        $urlWildcardService = $repository->getURLWildcardService();

        $urlWildcardService->create('/articles/*', '/content/{1}');

        $this->assertSame(1, $urlWildcardService->countAll());
    }
}
