<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace eZ\Publish\API\Repository\Tests;

use DateTime;
use eZ\Publish\API\Repository\Exceptions\NotFoundException;
use eZ\Publish\API\Repository\Repository;
use eZ\Publish\API\Repository\URLAliasService;
use eZ\Publish\API\Repository\Values\Content\Content;
use eZ\Publish\API\Repository\Values\Content\ContentInfo;
use eZ\Publish\API\Repository\Values\Content\Location as APILocation;
use eZ\Publish\API\Repository\Values\Content\LocationCreateStruct;
use eZ\Publish\API\Repository\Values\Content\Query;
use eZ\Publish\API\Repository\Values\Content\Query\Criterion;
use eZ\Publish\API\Repository\Values\Content\Query\SortClause;
use eZ\Publish\API\Repository\Values\Content\Trash\SearchResult;
use eZ\Publish\API\Repository\Values\Content\TrashItem as APITrashItem;
use eZ\Publish\API\Repository\Values\User\Limitation\SubtreeLimitation;
use eZ\Publish\Core\Repository\Values\Content\Location;
use eZ\Publish\Core\Repository\Values\Content\TrashItem;

/**
 * Test case for operations in the TrashService using in memory storage.
 *
 * @see eZ\Publish\API\Repository\TrashService
 * @group integration
 * @group trash
 */
class TrashServiceTest extends BaseTrashServiceTest
{
    /**
     * Test for the trash() method.
     *
     * @see \eZ\Publish\API\Repository\TrashService::trash()
     * @depends eZ\Publish\API\Repository\Tests\LocationServiceTest::testLoadLocationByRemoteId
     */
    public function testTrash()
    {
        /* BEGIN: Use Case */
        $trashItem = $this->createTrashItem();
        /* END: Use Case */

        $this->assertInstanceOf(
            '\\eZ\\Publish\\API\\Repository\\Values\\Content\\TrashItem',
            $trashItem
        );
    }

    /**
     * Test for the trash() method.
     *
     * @see \eZ\Publish\API\Repository\TrashService::trash()
     * @depends eZ\Publish\API\Repository\Tests\TrashServiceTest::testTrash
     */
    public function testTrashSetsExpectedTrashItemProperties()
    {
        $repository = $this->getRepository();

        $mediaRemoteId = '75c715a51699d2d309a924eca6a95145';

        // Load the location that will be trashed
        $location = $repository->getLocationService()
            ->loadLocationByRemoteId($mediaRemoteId);

        $expected = [
            'id' => $location->id,
            'depth' => $location->depth,
            'hidden' => $location->hidden,
            'invisible' => $location->invisible,
            'parentLocationId' => $location->parentLocationId,
            'pathString' => $location->pathString,
            'priority' => $location->priority,
            'remoteId' => $location->remoteId,
            'sortField' => $location->sortField,
            'sortOrder' => $location->sortOrder,
        ];

        $trashItem = $this->createTrashItem();

        $this->assertPropertiesCorrect($expected, $trashItem);
    }

    /**
     * Test for the trash() method.
     *
     * @see \eZ\Publish\API\Repository\TrashService::trash()
     * @depends eZ\Publish\API\Repository\Tests\TrashServiceTest::testTrash
     */
    public function testTrashRemovesLocationFromMainStorage()
    {
        $this->expectException(\eZ\Publish\API\Repository\Exceptions\NotFoundException::class);

        $repository = $this->getRepository();

        $mediaRemoteId = '75c715a51699d2d309a924eca6a95145';

        /* BEGIN: Use Case */
        $this->createTrashItem();

        // Load the location service
        $locationService = $repository->getLocationService();

        // This call will fail with a "NotFoundException", because the media
        // location was marked as trashed in the main storage
        $locationService->loadLocationByRemoteId($mediaRemoteId);
        /* END: Use Case */
    }

    /**
     * Test for the trash() method.
     *
     * @see \eZ\Publish\API\Repository\TrashService::trash()
     * @depends eZ\Publish\API\Repository\Tests\TrashServiceTest::testTrash
     */
    public function testTrashRemovesChildLocationsFromMainStorage()
    {
        $repository = $this->getRepository();

        /* BEGIN: Use Case */
        $remoteIds = $this->createRemoteIdList();

        $this->createTrashItem();

        // All invocations to loadLocationByRemoteId() to one of the above
        // collected remoteIds will return in an "NotFoundException"
        /* END: Use Case */

        $locationService = $repository->getLocationService();
        foreach ($remoteIds as $remoteId) {
            try {
                $locationService->loadLocationByRemoteId($remoteId);
                $this->fail("Location '{$remoteId}' should exist.'");
            } catch (NotFoundException $e) {
                // echo $e->getFile(), ' +', $e->getLine(), PHP_EOL;
            }
        }

        $this->assertGreaterThan(
            0,
            count($remoteIds),
            "There should be at least one 'Community' child location."
        );
    }

    /**
     * Test for the trash() method.
     *
     * @see \eZ\Publish\API\Repository\TrashService::trash()
     * @depends eZ\Publish\API\Repository\Tests\TrashServiceTest::testTrash
     */
    public function testTrashDecrementsChildCountOnParentLocation()
    {
        $repository = $this->getRepository();
        $locationService = $repository->getLocationService();

        $baseLocationId = $this->generateId('location', 1);

        $location = $locationService->loadLocation($baseLocationId);

        $childCount = $locationService->getLocationChildCount($location);

        $this->createTrashItem();

        $this->refreshSearch($repository);

        $this->assertEquals(
            $childCount - 1,
            $locationService->getLocationChildCount($location)
        );
    }

    /**
     * Test sending a location to trash updates Content mainLocation.
     *
     * @covers \eZ\Publish\API\Repository\TrashService::trash
     */
    public function testTrashUpdatesMainLocation()
    {
        $repository = $this->getRepository();
        $contentService = $repository->getContentService();
        $locationService = $repository->getLocationService();
        $trashService = $repository->getTrashService();

        $contentInfo = $contentService->loadContentInfo(42);

        // Create additional location that will become new main location
        $location = $locationService->createLocation(
            $contentInfo,
            new LocationCreateStruct(['parentLocationId' => 2])
        );

        $trashService->trash(
            $locationService->loadLocation($contentInfo->mainLocationId)
        );

        self::assertEquals(
            $location->id,
            $contentService->loadContentInfo(42)->mainLocationId
        );
    }

    /**
     * Test sending a location to trash.
     *
     * @covers \eZ\Publish\API\Repository\TrashService::trash
     */
    public function testTrashReturnsNull()
    {
        $repository = $this->getRepository();
        $contentService = $repository->getContentService();
        $locationService = $repository->getLocationService();
        $trashService = $repository->getTrashService();

        // Create additional location to trash
        $location = $locationService->createLocation(
            $contentService->loadContentInfo(42),
            new LocationCreateStruct(['parentLocationId' => 2])
        );

        $trashItem = $trashService->trash($location);

        self::assertNull($trashItem);
    }

    /**
     * Test for the loadTrashItem() method.
     *
     * @covers \eZ\Publish\API\Repository\TrashService::loadTrashItem
     * @depends eZ\Publish\API\Repository\Tests\TrashServiceTest::testTrash
     */
    public function testLoadTrashItem()
    {
        $repository = $this->getRepository();
        $trashService = $repository->getTrashService();

        /* BEGIN: Use Case */
        $trashItem = $this->createTrashItem();

        // Reload the trash item
        $trashItemReloaded = $trashService->loadTrashItem($trashItem->id);
        /* END: Use Case */

        $this->assertInstanceOf(
            APITrashItem::class,
            $trashItemReloaded
        );

        $this->assertEquals(
            $trashItem->pathString,
            $trashItemReloaded->pathString
        );

        $this->assertEquals(
            $trashItem,
            $trashItemReloaded
        );

        $this->assertInstanceOf(
            DateTime::class,
            $trashItemReloaded->trashed
        );

        $this->assertEquals(
            $trashItem->trashed->getTimestamp(),
            $trashItemReloaded->trashed->getTimestamp()
        );

        $this->assertGreaterThan(
            0,
            $trashItemReloaded->trashed->getTimestamp()
        );

        $this->assertInstanceOf(
            Content::class,
            $content = $trashItemReloaded->getContent()
        );
        $this->assertEquals($trashItem->contentId, $content->contentInfo->id);
    }

    /**
     * Test for the loadTrashItem() method.
     *
     * @see \eZ\Publish\API\Repository\TrashService::loadTrashItem()
     * @depends eZ\Publish\API\Repository\Tests\TrashServiceTest::testLoadTrashItem
     */
    public function testLoadTrashItemThrowsNotFoundException()
    {
        $this->expectException(\eZ\Publish\API\Repository\Exceptions\NotFoundException::class);

        $repository = $this->getRepository();

        $nonExistingTrashId = $this->generateId('trash', 2342);
        /* BEGIN: Use Case */
        $trashService = $repository->getTrashService();

        // This call will fail with a "NotFoundException", because no trash item
        // with the ID 1342 should exist in an eZ Publish demo installation
        $trashService->loadTrashItem($nonExistingTrashId);
        /* END: Use Case */
    }

    /**
     * Test for the recover() method.
     *
     * @covers \eZ\Publish\API\Repository\TrashService::recover
     * @depends eZ\Publish\API\Repository\Tests\TrashServiceTest::testTrash
     */
    public function testRecover()
    {
        $repository = $this->getRepository();
        $trashService = $repository->getTrashService();
        $locationService = $repository->getLocationService();

        $mediaRemoteId = '75c715a51699d2d309a924eca6a95145';

        /* BEGIN: Use Case */
        $trashItem = $this->createTrashItem();

        // Recover the trashed item
        $location = $trashService->recover($trashItem);

        // Load the recovered location
        $locationReloaded = $locationService->loadLocationByRemoteId(
            $mediaRemoteId
        );
        /* END: Use Case */

        $this->assertInstanceOf(
            APILocation::class,
            $location
        );

        $this->assertEquals(
            $location,
            $locationReloaded
        );

        try {
            $trashService->loadTrashItem($trashItem->id);
            $this->fail('Trash item was not removed after being recovered.');
        } catch (NotFoundException $e) {
            // All well
        }
    }

    /**
     * Test recovering a non existing trash item results in a NotFoundException.
     *
     * @covers \eZ\Publish\API\Repository\TrashService::recover
     */
    public function testRecoverThrowsNotFoundExceptionForNonExistingTrashItem()
    {
        $this->expectException(\eZ\Publish\API\Repository\Exceptions\NotFoundException::class);

        $repository = $this->getRepository();
        $trashService = $repository->getTrashService();

        $trashService->recover(
            $this->getTrashItemDouble(
                12364,
                12345,
                12363
            )
        );
    }

    /**
     * Test for the trash() method.
     *
     * @see \eZ\Publish\API\Repository\TrashService::recover()
     * @depends eZ\Publish\API\Repository\Tests\TrashServiceTest::testTrash
     */
    public function testNotFoundAliasAfterRemoveIt()
    {
        $this->expectException(\eZ\Publish\API\Repository\Exceptions\NotFoundException::class);

        $mediaRemoteId = '75c715a51699d2d309a924eca6a95145';

        $repository = $this->getRepository();
        $trashService = $repository->getTrashService();
        $urlAliasService = $repository->getURLAliasService();
        $locationService = $repository->getLocationService();

        // Double ->lookup() call because there where issue that one call was not enough to spot bug
        $urlAliasService->lookup('/Media');
        $urlAliasService->lookup('/Media');

        $mediaLocation = $locationService->loadLocationByRemoteId($mediaRemoteId);
        $trashService->trash($mediaLocation);

        $urlAliasService->lookup('/Media');
    }

    /**
     * Test for the recover() method.
     *
     * @see \eZ\Publish\API\Repository\TrashService::recover()
     * @depends eZ\Publish\API\Repository\Tests\TrashServiceTest::testTrash
     */
    public function testAliasesForRemovedItems()
    {
        $mediaRemoteId = '75c715a51699d2d309a924eca6a95145';

        $repository = $this->getRepository();
        $trashService = $repository->getTrashService();
        $urlAliasService = $repository->getURLAliasService();
        $locationService = $repository->getLocationService();

        // Double ->lookup() call because there where issue that one call was not enough to spot bug
        $urlAliasService->lookup('/Media');
        $trashedLocationAlias = $urlAliasService->lookup('/Media');

        $mediaLocation = $locationService->loadLocationByRemoteId($mediaRemoteId);
        $trashItem = $trashService->trash($mediaLocation);
        $this->assertAliasNotExists($urlAliasService, '/Media');

        $this->createNewContentInPlaceTrashedOne($repository, $mediaLocation->parentLocationId);

        $createdLocationAlias = $urlAliasService->lookup('/Media');

        $this->assertNotEquals(
            $trashedLocationAlias->destination,
            $createdLocationAlias->destination,
            'Destination for /media url should changed'
        );

        $recoveredLocation = $trashService->recover($trashItem);
        $recoveredLocationAlias = $urlAliasService->lookup('/Media2');
        $recoveredLocationAliasReverse = $urlAliasService->reverseLookup($recoveredLocation);

        $this->assertEquals($recoveredLocationAlias->destination, $recoveredLocationAliasReverse->destination);

        $this->assertNotEquals($recoveredLocationAliasReverse->destination, $trashedLocationAlias->destination);
        $this->assertNotEquals($recoveredLocationAliasReverse->destination, $createdLocationAlias->destination);
    }

    /**
     * Test for the recover() method.
     *
     * @see \eZ\Publish\API\Repository\TrashService::recover()
     * @depends eZ\Publish\API\Repository\Tests\TrashServiceTest::testRecover
     */
    public function testRecoverDoesNotRestoreChildLocations()
    {
        $repository = $this->getRepository();
        $trashService = $repository->getTrashService();
        $locationService = $repository->getLocationService();

        $remoteIds = $this->createRemoteIdList();

        // Unset remote ID of actually restored location
        unset($remoteIds[array_search('3f6d92f8044aed134f32153517850f5a', $remoteIds)]);

        $trashItem = $this->createTrashItem();

        $trashService->recover($trashItem);

        $this->assertGreaterThan(
            0,
            count($remoteIds),
            "There should be at least one 'Community' child location."
        );

        // None of the child locations will be available again
        foreach ($remoteIds as $remoteId) {
            try {
                $locationService->loadLocationByRemoteId($remoteId);
                $this->fail(
                    sprintf(
                        'Location with remote ID "%s" unexpectedly restored.',
                        $remoteId
                    )
                );
            } catch (NotFoundException $e) {
                // All well
            }
        }

        try {
            $trashService->loadTrashItem($trashItem->id);
            $this->fail('Trash item was not removed after being recovered.');
        } catch (NotFoundException $e) {
            // All well
        }
    }

    /**
     * Test for the recover() method.
     *
     * @see \eZ\Publish\API\Repository\TrashService::recover($trashItem, $newParentLocation)
     * @depends eZ\Publish\API\Repository\Tests\TrashServiceTest::testRecover
     *
     * @todo Fix naming
     */
    public function testRecoverWithLocationCreateStructParameter()
    {
        $repository = $this->getRepository();
        $trashService = $repository->getTrashService();
        $locationService = $repository->getLocationService();

        $homeLocationId = $this->generateId('location', 2);
        /* BEGIN: Use Case */
        // $homeLocationId is the ID of the "Home" location in an eZ Publish
        // demo installation

        $trashItem = $this->createTrashItem();

        // Get the new parent location
        $newParentLocation = $locationService->loadLocation($homeLocationId);

        // Recover location with new location
        $location = $trashService->recover($trashItem, $newParentLocation);
        /* END: Use Case */

        $this->assertPropertiesCorrect(
            [
                'remoteId' => $trashItem->remoteId,
                'parentLocationId' => $homeLocationId,
                // Not the full sub tree is restored
                'depth' => $newParentLocation->depth + 1,
                'hidden' => false,
                'invisible' => $trashItem->invisible,
                'pathString' => $newParentLocation->pathString . $this->parseId('location', $location->id) . '/',
                'priority' => 0,
                'sortField' => APILocation::SORT_FIELD_NAME,
                'sortOrder' => APILocation::SORT_ORDER_ASC,
            ],
            $location
        );

        try {
            $trashService->loadTrashItem($trashItem->id);
            $this->fail('Trash item was not removed after being recovered.');
        } catch (NotFoundException $e) {
            // All well
        }
    }

    /**
     * Test for the recover() method.
     *
     * @see \eZ\Publish\API\Repository\TrashService::recover($trashItem)
     * @depends eZ\Publish\API\Repository\Tests\TrashServiceTest::testRecover
     */
    public function testRecoverIncrementsChildCountOnOriginalParent()
    {
        $repository = $this->getRepository();
        $trashService = $repository->getTrashService();
        $locationService = $repository->getLocationService();

        $location = $locationService->loadLocation($this->generateId('location', 1));

        $trashItem = $this->createTrashItem();

        $this->refreshSearch($repository);

        /* BEGIN: Use Case */
        $childCount = $locationService->getLocationChildCount($location);

        // Recover location with new location
        $trashService->recover($trashItem);
        /* END: Use Case */

        $this->refreshSearch($repository);

        $this->assertEquals(
            $childCount + 1,
            $locationService->getLocationChildCount($location)
        );

        try {
            $trashService->loadTrashItem($trashItem->id);
            $this->fail('Trash item was not removed after being recovered.');
        } catch (NotFoundException $e) {
            // All well
        }
    }

    /**
     * Test for the recover() method.
     *
     * @see \eZ\Publish\API\Repository\TrashService::recover($trashItem, $newParentLocation)
     * @depends eZ\Publish\API\Repository\Tests\TrashServiceTest::testRecoverWithLocationCreateStructParameter
     */
    public function testRecoverWithLocationCreateStructParameterIncrementsChildCountOnNewParent()
    {
        $repository = $this->getRepository();
        $trashService = $repository->getTrashService();
        $locationService = $repository->getLocationService();

        $homeLocationId = $this->generateId('location', 2);

        $location = $locationService->loadLocation($homeLocationId);

        $childCount = $locationService->getLocationChildCount($location);

        /* BEGIN: Use Case */
        // $homeLocationId is the ID of the "Home" location in an eZ Publish
        // demo installation

        $trashItem = $this->createTrashItem();

        // Get the new parent location
        $newParentLocation = $locationService->loadLocation($homeLocationId);

        // Recover location with new location
        $trashService->recover($trashItem, $newParentLocation);
        /* END: Use Case */

        $this->refreshSearch($repository);

        $this->assertEquals(
            $childCount + 1,
            $locationService->getLocationChildCount($location)
        );

        try {
            $trashService->loadTrashItem($trashItem->id);
            $this->fail('Trash item was not removed after being recovered.');
        } catch (NotFoundException $e) {
            // All well
        }
    }

    /**
     * Test recovering a location from trash to non existing location.
     *
     * @covers \eZ\Publish\API\Repository\TrashService::recover
     */
    public function testRecoverToNonExistingLocation()
    {
        $this->expectException(\eZ\Publish\API\Repository\Exceptions\NotFoundException::class);

        $repository = $this->getRepository();
        $trashService = $repository->getTrashService();
        $locationService = $repository->getLocationService();

        $location = $locationService->loadLocation(44);
        $trashItem = $trashService->trash($location);

        $newParentLocation = new Location(
            [
                'id' => 123456,
                'parentLocationId' => 123455,
            ]
        );
        $trashService->recover($trashItem, $newParentLocation);
    }

    /**
     * @covers \eZ\Publish\API\Repository\TrashService::findTrashItems
     * @dataProvider trashFiltersProvider
     */
    public function testFindTrashItems(
        array $filters,
        int $expectedCount
    ): void {
        $repository = $this->getRepository();
        $trashService = $repository->getTrashService();

        $this->trashDifferentContentItems();

        $query = new Query();
        $filtersCount = count($filters);

        if ($filtersCount === 1) {
            $query->filter = $filters[0];
        } elseif ($filtersCount > 1) {
            $query->filter = new Criterion\LogicalAnd(
                $filters
            );
        }

        $searchResult = $trashService->findTrashItems($query);

        $this->assertEquals($expectedCount, $searchResult->totalCount);
    }

    /**
     * @covers  \eZ\Publish\API\Repository\TrashService::findTrashItems
     *
     * @throws \ErrorException
     * @throws \eZ\Publish\API\Repository\Exceptions\ForbiddenException
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     */
    public function testFindTrashItemsSortedByDateTrashed(): void
    {
        $repository = $this->getRepository();
        $trashService = $repository->getTrashService();
        $locationService = $repository->getLocationService();

        $folder1 = $this->createFolder(['eng-GB' => 'Folder1'], 2);
        $folder2 = $this->createFolder(['eng-GB' => 'Folder2'], 2);

        $firstTrashedItem = $trashService->trash(
            $locationService->loadLocation($folder1->contentInfo->mainLocationId)
        );
        $this->updateTrashedDate($firstTrashedItem->id, \time() - 100);
        $latestTrashItem = $trashService->trash(
            $locationService->loadLocation($folder2->contentInfo->mainLocationId)
        );

        $query = new Query();

        // Load all trashed locations, sorted by trashed date ASC
        $query->sortClauses = [new SortClause\Trash\DateTrashed(Query::SORT_ASC)];
        $searchResult = $trashService->findTrashItems($query);
        self::assertEquals(2, $searchResult->totalCount);
        self::assertEquals($firstTrashedItem->remoteId, $searchResult->items[0]->remoteId);
        self::assertEquals($latestTrashItem->remoteId, $searchResult->items[1]->remoteId);

        // Load all trashed locations, sorted by trashed date DESC
        $query->sortClauses = [new SortClause\Trash\DateTrashed(Query::SORT_DESC)];
        $searchResult = $trashService->findTrashItems($query);
        self::assertEquals(2, $searchResult->totalCount);
        self::assertEquals($latestTrashItem->remoteId, $searchResult->items[0]->remoteId);
        self::assertEquals($firstTrashedItem->remoteId, $searchResult->items[1]->remoteId);
    }

    /**
     * @covers \eZ\Publish\API\Repository\TrashService::findTrashItems
     * @dataProvider trashSortClausesProvider
     */
    public function testFindTrashItemsSort(array $sortClausesClasses): void
    {
        $repository = $this->getRepository();
        $trashService = $repository->getTrashService();

        $expectedCount = 2;
        $ascQuery = new Query();
        $ascQuery->limit = $expectedCount;
        $descQuery = clone $ascQuery;

        $this->trashDifferentContentItems();

        foreach ($sortClausesClasses as $sortClauseClass) {
            $ascQuery->sortClauses[] = new $sortClauseClass(Query::SORT_ASC);
        }

        $ascResultsIds = [];
        foreach ($trashService->findTrashItems($ascQuery) as $result) {
            $ascResultsIds[] = $result->contentInfo->id;
        }

        $this->assertGreaterThanOrEqual($expectedCount, count($ascResultsIds));

        foreach ($sortClausesClasses as $sortClauseClass) {
            $descQuery->sortClauses[] = new $sortClauseClass(Query::SORT_DESC);
        }

        $descResultsIds = [];
        foreach ($trashService->findTrashItems($descQuery) as $result) {
            $descResultsIds[] = $result->contentInfo->id;
        }

        $this->assertNotSame($descResultsIds, $ascResultsIds);

        krsort($descResultsIds);
        $descResultsIds = array_values($descResultsIds);

        $this->assertSame($descResultsIds, $ascResultsIds);
    }

    /**
     * Test for the findTrashItems() method for it's result structure.
     *
     * @see \eZ\Publish\API\Repository\TrashService::findTrashItems()
     * @depends eZ\Publish\API\Repository\Tests\TrashServiceTest::testTrash
     */
    public function testFindTrashItemsLimits()
    {
        $repository = $this->getRepository();
        $trashService = $repository->getTrashService();

        $this->createTrashItem();

        // Create a search query for all trashed items
        $query = new Query();
        $query->limit = 2;

        // Load all trashed locations
        $searchResult = $trashService->findTrashItems($query);

        $this->assertInstanceOf(
            SearchResult::class,
            $searchResult
        );

        // 4 trashed locations from the sub tree, but only 2 in results
        $this->assertCount(2, $searchResult->items);
        $this->assertEquals(4, $searchResult->count);
        $this->assertEquals(4, $searchResult->totalCount);
    }

    /**
     * Test for the findTrashItems() method.
     *
     * @see \eZ\Publish\API\Repository\TrashService::findTrashItems()
     * @depends \eZ\Publish\API\Repository\Tests\TrashServiceTest::testFindTrashItems
     */
    public function testFindTrashItemsLimitedAccess()
    {
        $repository = $this->getRepository();
        $trashService = $repository->getTrashService();

        /* BEGIN: Use Case */
        $this->createTrashItem();

        // Create a search query for all trashed items
        $query = new Query();
        $query->filter = new Criterion\LogicalAnd(
            [
                new Criterion\Field('title', Criterion\Operator::LIKE, '*'),
            ]
        );

        // Create a user in the Editor user group.
        $user = $this->createUserVersion1();

        // Set the Editor user as current user, these users have no access to Trash by default.
        $repository->getPermissionResolver()->setCurrentUserReference($user);

        // Load all trashed locations
        $searchResult = $trashService->findTrashItems($query);
        /* END: Use Case */

        $this->assertInstanceOf(
            '\\eZ\\Publish\\API\\Repository\\Values\\Content\\SearchResult',
            $searchResult
        );

        // 0 trashed locations found, though 4 exist
        $this->assertEquals(0, $searchResult->count);
    }

    /**
     * Test Section Role Assignment Limitation against user/login.
     */
    public function testFindTrashItemsSubtreeLimitation()
    {
        $repository = $this->getRepository();
        $contentService = $repository->getContentService();
        $locationService = $repository->getLocationService();
        $contentTypeService = $repository->getContentTypeService();
        $trashService = $repository->getTrashService();

        $folder1 = $this->createFolder(['eng-GB' => 'Folder1'], 2);
        $folderLocationId = $folder1->contentInfo->mainLocationId;
        $contentType = $contentTypeService->loadContentTypeByIdentifier('forum');
        $newContent = $contentService->newContentCreateStruct($contentType, 'eng-US');
        $newContent->setField('name', 'Media');
        $draftContent = $contentService->createContent($newContent, [new LocationCreateStruct(['parentLocationId' => $folderLocationId])]);
        $published = $contentService->publishVersion($draftContent->versionInfo);
        $location = $locationService->loadLocation($published->contentInfo->mainLocationId);
        $trashService->trash($location);

        $this->createRoleWithPolicies('roleTrashCleaner', [
            [
                'module' => 'content',
                'function' => 'cleantrash',
            ],
            [
                'module' => 'content',
                'function' => 'read',
                'limitations' => [
                    new SubtreeLimitation(['limitationValues' => [sprintf('/1/2/%d/', $folderLocationId)]]),
                ],
            ],
        ]);
        $user = $this->createCustomUserWithLogin(
            'user',
            'user@example.com',
            'roleTrashCleaners',
            'roleTrashCleaner'
        );
        $repository->getPermissionResolver()->setCurrentUserReference($user);

        $query = new Query();

        // Load all trashed locations
        $searchResult = $trashService->findTrashItems($query);
        /* END: Use Case */
        $this->assertInstanceOf(
            SearchResult::class,
            $searchResult
        );

        $this->assertEquals(1, count($searchResult->items));
    }

    /**
     * Test for the emptyTrash() method.
     *
     * @see \eZ\Publish\API\Repository\TrashService::emptyTrash()
     * @depends eZ\Publish\API\Repository\Tests\TrashServiceTest::testFindTrashItems
     */
    public function testEmptyTrash()
    {
        $repository = $this->getRepository();
        $trashService = $repository->getTrashService();
        $contentService = $repository->getContentService();

        /* BEGIN: Use Case */
        $trashItem = $this->createTrashItem();

        // Empty the trash
        $trashService->emptyTrash();

        // Create a search query for all trashed items
        $query = new Query();

        // Load all trashed locations, search result should be empty
        $searchResult = $trashService->findTrashItems($query);
        /* END: Use Case */

        $this->assertEquals(0, $searchResult->count);

        // Try to load content
        $this->expectException(NotFoundException::class);
        $contentService->loadContent($trashItem->contentId);
    }

    /**
     * Test for the emptyTrash() method with user which has subtree limitations.
     *
     * @see \eZ\Publish\API\Repository\TrashService::emptyTrash()
     * @depends eZ\Publish\API\Repository\Tests\TrashServiceTest::testFindTrashItems
     */
    public function testEmptyTrashForUserWithSubtreeLimitation()
    {
        $repository = $this->getRepository();
        $trashService = $repository->getTrashService();
        $contentService = $repository->getContentService();

        /* BEGIN: Use Case */
        $trashItem = $this->createTrashItem();

        $this->createRoleWithPolicies('roleTrashCleaner', [
            ['module' => 'content', 'function' => 'cleantrash'],
            ['module' => 'content', 'function' => 'read'],
        ]);
        $user = $this->createCustomUserWithLogin(
            'user',
            'user@example.com',
            'roleTrashCleaners',
            'roleTrashCleaner',
            new SubtreeLimitation(['limitationValues' => ['/1/2/']])
        );
        $repository->getPermissionResolver()->setCurrentUserReference($user);

        // Empty the trash
        $trashService->emptyTrash();

        // Create a search query for all trashed items
        $query = new Query();

        // Load all trashed locations, search result should be empty
        $searchResult = $trashService->findTrashItems($query);
        /* END: Use Case */

        $this->assertEquals(0, $searchResult->totalCount);

        // Try to load content
        $this->expectException(NotFoundException::class);
        $contentService->loadContent($trashItem->contentId);
    }

    /**
     * Test for the deleteTrashItem() method.
     *
     * @see \eZ\Publish\API\Repository\TrashService::deleteTrashItem()
     * @depends eZ\Publish\API\Repository\Tests\TrashServiceTest::testFindTrashItems
     */
    public function testDeleteTrashItem()
    {
        $repository = $this->getRepository();
        $trashService = $repository->getTrashService();
        $locationService = $repository->getLocationService();
        $contentService = $repository->getContentService();

        $demoDesignLocationId = $this->generateId('location', 56);
        /* BEGIN: Use Case */
        // $demoDesignLocationId is the ID of the "Demo Design" location in an eZ
        // Publish demo installation

        $trashItem = $this->createTrashItem();

        // Trash one more location
        $trashService->trash(
            $locationService->loadLocation($demoDesignLocationId)
        );

        // Empty the trash
        $trashService->deleteTrashItem($trashItem);

        // Create a search query for all trashed items
        $query = new Query();

        // Load all trashed locations, should only contain the Demo Design location
        $searchResult = $trashService->findTrashItems($query);
        /* END: Use Case */

        $foundIds = array_map(
            static function ($trashItem) {
                return $trashItem->id;
            },
            $searchResult->items
        );

        $this->assertEquals(4, $searchResult->count);
        $this->assertTrue(
            in_array($demoDesignLocationId, $foundIds)
        );

        // Try to load Content
        $this->expectException(NotFoundException::class);
        $contentService->loadContent($trashItem->contentId);
    }

    /**
     * Test deleting a non existing trash item.
     *
     * @covers \eZ\Publish\API\Repository\TrashService::deleteTrashItem
     */
    public function testDeleteThrowsNotFoundExceptionForNonExistingTrashItem()
    {
        $this->expectException(\eZ\Publish\API\Repository\Exceptions\NotFoundException::class);

        $repository = $this->getRepository();
        $trashService = $repository->getTrashService();

        $trashService->deleteTrashItem($this->getTrashItemDouble(
            12364,
            12345,
            12363
        ));
    }

    /**
     * @return array
     */
    public function trashFiltersProvider(): array
    {
        return [
            [
                [],
                2,
            ],
            [
                [
                    new Criterion\ContentTypeId(4),
                ],
                1,
            ],
            [
                [
                    new Criterion\ContentTypeId(999),
                ],
                0,
            ],
            [
                [
                    new Criterion\SectionId(2),
                ],
                1,
            ],
            [
                [
                    new Criterion\SectionId(999),
                ],
                0,
            ],
            [
                [
                    new Criterion\UserMetadata(
                        Criterion\UserMetadata::OWNER,
                        Criterion\Operator::EQ,
                        14
                    ),
                ],
                1,
            ],
            [
                [
                    new Criterion\UserMetadata(
                        Criterion\UserMetadata::OWNER,
                        Criterion\Operator::EQ,
                        999
                    ),
                ],
                0,
            ],
            [
                [
                    new Criterion\DateMetadata(
                        Criterion\DateMetadata::TRASHED,
                        Criterion\Operator::BETWEEN,
                        [time(), time() + 86400]
                    ),
                ],
                2,
            ],
            [
                [
                    new Criterion\DateMetadata(
                        Criterion\DateMetadata::TRASHED,
                        Criterion\Operator::BETWEEN,
                        [time() - 90, time()]
                    ),
                ],
                0,
            ],
            [
                [
                    new Criterion\ContentTypeId(1),
                    new Criterion\SectionId(1),
                    new Criterion\UserMetadata(
                        Criterion\UserMetadata::OWNER,
                        Criterion\Operator::EQ,
                        14
                    ),
                    new Criterion\DateMetadata(
                        Criterion\DateMetadata::TRASHED,
                        Criterion\Operator::BETWEEN,
                        [time(), time() + 86400]
                    ),
                ],
                1,
            ],
            [
                [
                    new Criterion\ContentTypeId(999),
                    new Criterion\SectionId(1),
                    new Criterion\UserMetadata(
                        Criterion\UserMetadata::OWNER,
                        Criterion\Operator::EQ,
                        10
                    ),
                    new Criterion\DateMetadata(
                        Criterion\DateMetadata::TRASHED,
                        Criterion\Operator::BETWEEN,
                        [time() - 90, time() + 90]
                    ),
                ],
                0,
            ],
            [
                [
                    new Criterion\MatchNone(),
                ],
                0,
            ],
            [
                [
                    new Criterion\MatchAll(),
                ],
                2,
            ],
            [
                [
                    new Criterion\LogicalNot(new Criterion\SectionId(2)),
                ],
                1,
            ],
            [
                [
                    new Criterion\LogicalOr([
                        new Criterion\SectionId(1),
                        new Criterion\ContentTypeId(4),
                    ]),
                ],
                2,
            ],
            [
                [
                    new Criterion\LogicalAnd([
                        new Criterion\SectionId(2),
                        new Criterion\ContentTypeId(4),
                    ]),
                ],
                1,
            ],
        ];
    }

    public function trashSortClausesProvider(): array
    {
        return [
            [
                [
                    SortClause\SectionName::class,
                ],
            ],
            [
                [
                    SortClause\ContentName::class,
                ],
            ],
            [
                [
                    SortClause\Trash\ContentTypeName::class,
                ],
            ],
            [
                [
                    SortClause\Trash\UserLogin::class,
                ],
            ],
            [
                [
                    SortClause\SectionName::class,
                    SortClause\ContentName::class,
                    SortClause\Trash\ContentTypeName::class,
                    SortClause\Trash\UserLogin::class,
                ],
            ],
        ];
    }

    /**
     * Returns an array with the remoteIds of all child locations of the
     * <b>Community</b> location. It is stored in a local variable named
     * <b>$remoteIds</b>.
     *
     * @return string[]
     */
    private function createRemoteIdList()
    {
        $repository = $this->getRepository();

        /* BEGIN: Inline */
        // remoteId of the "Community" location in an eZ Publish demo installation
        $mediaRemoteId = '75c715a51699d2d309a924eca6a95145';

        // Load the location service
        $locationService = $repository->getLocationService();

        $remoteIds = [];
        $children = $locationService->loadLocationChildren($locationService->loadLocationByRemoteId($mediaRemoteId));
        foreach ($children->locations as $child) {
            $remoteIds[] = $child->remoteId;
            foreach ($locationService->loadLocationChildren($child)->locations as $grandChild) {
                $remoteIds[] = $grandChild->remoteId;
            }
        }
        /* END: Inline */

        return $remoteIds;
    }

    /**
     * @param \eZ\Publish\API\Repository\Repository $repository
     * @param int $parentLocationId
     *
     * @return \eZ\Publish\API\Repository\Values\Content\Content
     */
    protected function createNewContentInPlaceTrashedOne(Repository $repository, $parentLocationId)
    {
        $contentService = $repository->getContentService();
        $locationService = $repository->getLocationService();
        $contentTypeService = $repository->getContentTypeService();

        $contentType = $contentTypeService->loadContentTypeByIdentifier('forum');
        $newContent = $contentService->newContentCreateStruct($contentType, 'eng-US');
        $newContent->setField('name', 'Media');

        $location = $locationService->newLocationCreateStruct($parentLocationId);

        $draftContent = $contentService->createContent($newContent, [$location]);

        return $contentService->publishVersion($draftContent->versionInfo);
    }

    /**
     * @param \eZ\Publish\API\Repository\URLAliasService $urlAliasService
     * @param string $urlPath Url alias path
     *
     * @return \eZ\Publish\API\Repository\Values\Content\URLAlias
     */
    private function assertAliasExists(URLAliasService $urlAliasService, $urlPath)
    {
        $urlAlias = $urlAliasService->lookup($urlPath);

        $this->assertInstanceOf('\eZ\Publish\API\Repository\Values\Content\URLAlias', $urlAlias);

        return $urlAlias;
    }

    /**
     * @param \eZ\Publish\API\Repository\URLAliasService $urlAliasService
     * @param string $urlPath Url alias path
     */
    private function assertAliasNotExists(URLAliasService $urlAliasService, $urlPath)
    {
        try {
            $this->getRepository()->getURLAliasService()->lookup($urlPath);
            $this->fail(sprintf('Alias [%s] should not exist', $urlPath));
        } catch (\eZ\Publish\API\Repository\Exceptions\NotFoundException $e) {
            $this->assertTrue(true);
        }
    }

    /**
     * Get Test Double for TrashItem for exception testing and similar.
     *
     * @param int $trashId
     * @param int $contentId
     * @param int $parentLocationId
     *
     * @return \eZ\Publish\API\Repository\Values\Content\TrashItem
     */
    private function getTrashItemDouble(int $trashId, int $contentId = 44, int $parentLocationId = 2): APITrashItem
    {
        return new TrashItem([
            'id' => $trashId,
            'parentLocationId' => $parentLocationId,
            'contentInfo' => new ContentInfo(['id' => $contentId]),
        ]);
    }

    private function trashDifferentContentItems(): void
    {
        $repository = $this->getRepository(false);
        $permissionResolver = $repository->getPermissionResolver();
        $trashService = $repository->getTrashService();
        $locationService = $repository->getLocationService();
        $currentUser = $permissionResolver->getCurrentUserReference();

        $folderContent = $this->createFolder(['eng-GB' => 'Folder'], 2);

        $newCreator = $this->createUserWithPolicies(
            'test_user',
            [
                ['module' => 'content', 'function' => 'create'],
                ['module' => 'content', 'function' => 'read'],
                ['module' => 'content', 'function' => 'publish'],
            ]
        );

        $permissionResolver->setCurrentUserReference($newCreator);

        $userContent = $this->createUser('test_user2', 'Some2', 'User2');

        $permissionResolver->setCurrentUserReference($currentUser);

        $locationIds = [
            $userContent->contentInfo->mainLocationId,
            $folderContent->contentInfo->mainLocationId,
        ];

        foreach ($locationIds as $locationId) {
            $trashService->trash(
                $locationService->loadLocation($locationId)
            );
        }
    }
}
