<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
namespace eZ\Publish\Core\Persistence\Legacy\Content\Location\Trash;

use eZ\Publish\API\Repository\Values\Content\Query\Criterion;
use eZ\Publish\API\Repository\Values\Content\Trash\TrashItemDeleteResult;
use eZ\Publish\API\Repository\Values\Content\Trash\TrashItemDeleteResultList;
use eZ\Publish\Core\Persistence\Legacy\Content\Handler as ContentHandler;
use eZ\Publish\Core\Persistence\Legacy\Content\Location\Gateway as LocationGateway;
use eZ\Publish\Core\Persistence\Legacy\Content\Location\Handler as LocationHandler;
use eZ\Publish\Core\Persistence\Legacy\Content\Location\Mapper as LocationMapper;
use eZ\Publish\SPI\Persistence\Content\Location\Trash\Handler as BaseTrashHandler;
use eZ\Publish\SPI\Persistence\Content\Location\Trash\TrashResult;
use eZ\Publish\SPI\Persistence\Content\Location\Trashed;

/**
 * The Location Handler interface defines operations on Location elements in the storage engine.
 */
class Handler implements BaseTrashHandler
{
    private const EMPTY_TRASH_BULK_SIZE = 100;

    /**
     * Location handler.
     *
     * @var \eZ\Publish\Core\Persistence\Legacy\Content\Location\Handler
     */
    protected $locationHandler;

    /**
     * Gateway for handling location data.
     *
     * @var \eZ\Publish\Core\Persistence\Legacy\Content\Location\Gateway
     */
    protected $locationGateway;

    /**
     * Mapper for handling location data.
     *
     * @var \eZ\Publish\Core\Persistence\Legacy\Content\Location\Mapper
     */
    protected $locationMapper;

    /**
     * Content handler.
     *
     * @var \eZ\Publish\Core\Persistence\Legacy\Content\Handler
     */
    protected $contentHandler;

    /**
     * Construct from userGateway.
     *
     * @param \eZ\Publish\Core\Persistence\Legacy\Content\Location\Handler $locationHandler
     * @param \eZ\Publish\Core\Persistence\Legacy\Content\Location\Gateway $locationGateway
     * @param \eZ\Publish\Core\Persistence\Legacy\Content\Location\Mapper $locationMapper
     * @param \eZ\Publish\Core\Persistence\Legacy\Content\Handler $contentHandler
     *
     * @return \eZ\Publish\Core\Persistence\Legacy\Content\Location\Trash\Handler
     */
    public function __construct(
        LocationHandler $locationHandler,
        LocationGateway $locationGateway,
        LocationMapper $locationMapper,
        ContentHandler $contentHandler
    ) {
        $this->locationHandler = $locationHandler;
        $this->locationGateway = $locationGateway;
        $this->locationMapper = $locationMapper;
        $this->contentHandler = $contentHandler;
    }

    /**
     * Loads the data for the trashed location identified by $id.
     * $id is the same as original location (which has been previously trashed).
     *
     * @param int $id
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException
     *
     * @return \eZ\Publish\SPI\Persistence\Content\Location\Trashed
     */
    public function loadTrashItem($id)
    {
        $data = $this->locationGateway->loadTrashByLocation($id);

        return $this->locationMapper->createLocationFromRow($data, null, new Trashed());
    }

    /**
     * Sends a subtree starting to $locationId to the trash
     * and returns a Trashed object corresponding to $locationId.
     *
     * Moves all locations in the subtree to the Trash. The associated content
     * objects are left untouched.
     *
     * @param mixed $locationId
     *
     * @todo Handle field types actions
     *
     * @return \eZ\Publish\SPI\Persistence\Content\Location\Trashed|null null if location was deleted, otherwise Trashed object
     */
    public function trashSubtree($locationId)
    {
        $locationRows = $this->locationGateway->getSubtreeContent($locationId);
        $isLocationRemoved = false;
        $parentLocationId = null;

        foreach ($locationRows as $locationRow) {
            if ($locationRow['node_id'] == $locationId) {
                $parentLocationId = $locationRow['parent_node_id'];
            }

            if ($this->locationGateway->countLocationsByContentId($locationRow['contentobject_id']) == 1) {
                $this->locationGateway->trashLocation($locationRow['node_id']);
            } else {
                if ($locationRow['node_id'] == $locationId) {
                    $isLocationRemoved = true;
                }
                $this->locationGateway->removeLocation($locationRow['node_id']);

                if ($locationRow['node_id'] == $locationRow['main_node_id']) {
                    $newMainLocationRow = $this->locationGateway->getFallbackMainNodeData(
                        $locationRow['contentobject_id'],
                        $locationRow['node_id']
                    );

                    $this->locationHandler->changeMainLocation(
                        $locationRow['contentobject_id'],
                        $newMainLocationRow['node_id'],
                        $newMainLocationRow['contentobject_version'],
                        $newMainLocationRow['parent_node_id']
                    );
                }
            }
        }

        if (isset($parentLocationId)) {
            $this->locationHandler->markSubtreeModified($parentLocationId, time());
        }

        return $isLocationRemoved ? null : $this->loadTrashItem($locationId);
    }

    /**
     * Returns a trashed location to normal state.
     *
     * Recreates the originally trashed location in the new position.
     * If this is not possible (because the old location does not exist any more),
     * a ParentNotFound exception is thrown.
     *
     * Returns newly restored location Id.
     *
     * @param mixed $trashedId
     * @param mixed $newParentId
     *
     * @return int Newly restored location id
     *
     * @throws \eZ\Publish\Core\Base\Exceptions\NotFoundException If $newParentId is invalid
     *
     * @todo Handle field types actions
     */
    public function recover($trashedId, $newParentId)
    {
        return $this->locationGateway->untrashLocation($trashedId, $newParentId)->id;
    }

    /**
     * {@inheritdoc}.
     */
    public function findTrashItems(Criterion $criterion = null, $offset = 0, $limit = null, array $sort = null)
    {
        $totalCount = $this->locationGateway->countTrashed($criterion);
        if ($totalCount === 0) {
            return new TrashResult();
        }

        $rows = $this->locationGateway->listTrashed($offset, $limit, $sort, $criterion);
        $items = [];

        foreach ($rows as $row) {
            $items[] = $this->locationMapper->createLocationFromRow($row, null, new Trashed());
        }

        return new TrashResult([
            'items' => $items,
            'totalCount' => $totalCount,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function emptyTrash()
    {
        $resultList = new TrashItemDeleteResultList();
        do {
            $trashedItems = $this->findTrashItems(null, 0, self::EMPTY_TRASH_BULK_SIZE);
            foreach ($trashedItems as $item) {
                $resultList->items[] = $this->delete($item);
            }
        } while ($trashedItems->totalCount > self::EMPTY_TRASH_BULK_SIZE);

        $this->locationGateway->cleanupTrash();

        return $resultList;
    }

    /**
     * Removes a trashed location identified by $trashedLocationId from trash
     * Associated content has to be deleted.
     *
     * @param int $trashedId
     *
     * @return \eZ\Publish\API\Repository\Values\Content\Trash\TrashItemDeleteResult
     */
    public function deleteTrashItem($trashedId)
    {
        return $this->delete($this->loadTrashItem($trashedId));
    }

    /**
     * Triggers delete operations for $trashItem.
     * If there is no more locations for corresponding content, then it will be deleted as well.
     *
     * @param \eZ\Publish\SPI\Persistence\Content\Location\Trashed $trashItem
     *
     * @return \eZ\Publish\API\Repository\Values\Content\Trash\TrashItemDeleteResult
     */
    protected function delete(Trashed $trashItem)
    {
        $result = new TrashItemDeleteResult();
        $result->trashItemId = $trashItem->id;
        $result->contentId = $trashItem->contentId;

        $this->locationGateway->removeElementFromTrash($trashItem->id);

        if ($this->locationGateway->countLocationsByContentId($trashItem->contentId) < 1) {
            $this->contentHandler->deleteContent($trashItem->contentId);
            $result->contentRemoved = true;
        }

        return $result;
    }
}
