<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace eZ\Publish\API\Repository\Tests\Values\User\Limitation;

use eZ\Publish\API\Repository\ContentService;
use eZ\Publish\API\Repository\Exceptions\UnauthorizedException;
use eZ\Publish\API\Repository\Tests\BaseTest;
use eZ\Publish\API\Repository\Values\Content\Content;
use eZ\Publish\API\Repository\Values\Content\LocationCreateStruct;
use eZ\Publish\API\Repository\Values\User\Limitation\LanguageLimitation;
use eZ\Publish\API\Repository\Values\User\User;
use eZ\Publish\SPI\Limitation\Target\Builder\VersionBuilder;

/**
 * Test cases for ContentService APIs calls made by user with LanguageLimitation on chosen policies.
 *
 * @uses \eZ\Publish\API\Repository\Values\User\Limitation\LanguageLimitation
 *
 * @group integration
 * @group authorization
 * @group language-limited-content-mgm
 */
class LanguageLimitationTest extends BaseTest
{
    /** @var string */
    private const ENG_US = 'eng-US';

    /** @var string */
    private const ENG_GB = 'eng-GB';

    /** @var string */
    private const GER_DE = 'ger-DE';

    /**
     * Create editor who is allowed to modify only specific translations of a Content item.
     *
     * @param array $allowedTranslationsList list of translations (language codes) which editor can modify.
     * @param string $login
     *
     * @return \eZ\Publish\API\Repository\Values\User\User
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\ForbiddenException
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     */
    private function createEditorUserWithLanguageLimitation(
        array $allowedTranslationsList,
        string $login = 'editor'
    ): User {
        $limitations = [
            // limitation for specific translations
            new LanguageLimitation(['limitationValues' => $allowedTranslationsList]),
        ];

        return $this->createUserWithPolicies(
            $login,
            [
                ['module' => 'content', 'function' => 'read'],
                ['module' => 'content', 'function' => 'versionread'],
                ['module' => 'content', 'function' => 'view_embed'],
                ['module' => 'content', 'function' => 'create', 'limitations' => $limitations],
                ['module' => 'content', 'function' => 'edit', 'limitations' => $limitations],
                ['module' => 'content', 'function' => 'publish', 'limitations' => $limitations],
                ['module' => 'content', 'function' => 'hide', 'limitations' => $limitations],
                ['module' => 'content', 'function' => 'manage_locations'],
            ]
        );
    }

    /**
     * @return array
     *
     * @see testCreateAndPublishContent
     */
    public function providerForCreateAndPublishContent(): array
    {
        // $names (as admin), $allowedTranslationsList (editor limitations)
        return [
            [
                ['ger-DE' => 'German Folder'],
                ['ger-DE'],
            ],
            [
                ['ger-DE' => 'German Folder', 'eng-GB' => 'British Folder'],
                ['ger-DE', 'eng-GB'],
            ],
        ];
    }

    /**
     * Test creating and publishing a fresh Content item in a language restricted by LanguageLimitation.
     *
     * @param array $names
     * @param array $allowedTranslationsList
     *
     * @dataProvider providerForCreateAndPublishContent
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\ForbiddenException
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     */
    public function testCreateAndPublishContent(array $names, array $allowedTranslationsList): void
    {
        $repository = $this->getRepository();
        $repository->getPermissionResolver()->setCurrentUserReference(
            $this->createEditorUserWithLanguageLimitation($allowedTranslationsList)
        );

        $folder = $this->createFolder($names, 2);

        foreach ($names as $languageCode => $translatedName) {
            self::assertEquals(
                $translatedName,
                $folder->getField('name', $languageCode)->value->text
            );
        }
    }

    /**
     * @covers \eZ\Publish\API\Repository\PermissionResolver::canUser
     *
     * @dataProvider providerForCanUserWithLimitationTargets
     *
     * @param array $folderNames names of a folder to create as test content
     * @param array $allowedTranslationsList a list of language codes of translations a user is allowed to edit
     * @param \eZ\Publish\SPI\Limitation\Target[] $targets
     * @param bool $expectedCanUserResult
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\ForbiddenException
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     */
    public function testCanUserWithLimitationTargets(
        string $policyModule,
        string $policyFunction,
        array $folderNames,
        array $allowedTranslationsList,
        array $targets,
        bool $expectedCanUserResult
    ): void {
        $repository = $this->getRepository();

        // prepare test data as an admin
        $content = $this->createFolder($folderNames, 2);

        $permissionResolver = $repository->getPermissionResolver();
        $permissionResolver->setCurrentUserReference(
            $this->createEditorUserWithLanguageLimitation($allowedTranslationsList)
        );

        $actualCanUserResult = $permissionResolver->canUser(
            $policyModule,
            $policyFunction,
            $content->contentInfo,
            $targets
        );

        self::assertSame(
            $expectedCanUserResult,
            $actualCanUserResult,
            "canUser('{$policyModule}', '{$policyFunction}') returned unexpected result"
        );
    }

    /**
     * Data provider for testEditContentWithLimitationTargets.
     *
     * @return array
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\InvalidArgumentException
     */
    public function providerForCanUserWithLimitationTargets(): array
    {
        return [
            'Editing a content before translating it' => [
                'content',
                'edit',
                ['eng-GB' => 'BrE Folder'],
                ['ger-DE'],
                [
                    (new VersionBuilder())
                        ->translateToAnyLanguageOf(['ger-DE'])
                        ->build(),
                ],
                true,
            ],
            'Publishing the specific translation of a content item' => [
                'content',
                'publish',
                ['eng-GB' => 'BrE Folder', 'ger-DE' => 'DE Folder'],
                ['ger-DE'],
                [
                    (new VersionBuilder())
                        ->publishTranslations(['ger-DE'])
                        ->build(),
                ],
                true,
            ],
            'Not being able to edit a content before translating it' => [
                'content',
                'edit',
                ['eng-GB' => 'BrE Folder'],
                ['ger-DE'],
                [
                    (new VersionBuilder())
                        ->translateToAnyLanguageOf(['eng-GB'])
                        ->build(),
                ],
                false,
            ],
            'Not being able to publish the specific translation of a content item' => [
                'content',
                'publish',
                ['eng-GB' => 'BrE Folder', 'ger-DE' => 'DE Folder'],
                ['ger-DE'],
                [
                    (new VersionBuilder())
                        ->publishTranslations(['eng-GB'])
                        ->build(),
                ],
                false,
            ],
        ];
    }

    /**
     * Data provider for testPublishVersionWithLanguageLimitation.
     *
     * @return array
     *
     * @see testPublishVersionIsNotAllowedIfModifiedOtherTranslations
     * @see testPublishVersion
     */
    public function providerForPublishVersionWithLanguageLimitation(): array
    {
        // $names (as admin), $namesToUpdate (as editor), $allowedTranslationsList (editor limitations)
        return [
            [
                ['eng-US' => 'American Folder'],
                ['ger-DE' => 'Updated German Folder'],
                ['ger-DE'],
            ],
            [
                ['eng-US' => 'American Folder', 'ger-DE' => 'German Folder'],
                ['ger-DE' => 'Updated German Folder'],
                ['ger-DE'],
            ],
            [
                [
                    'eng-US' => 'American Folder',
                    'eng-GB' => 'British Folder',
                    'ger-DE' => 'German Folder',
                ],
                ['ger-DE' => 'Updated German Folder', 'eng-GB' => 'British Folder'],
                ['ger-DE', 'eng-GB'],
            ],
            [
                ['eng-US' => 'American Folder', 'ger-DE' => 'German Folder'],
                ['ger-DE' => 'Updated German Folder', 'eng-GB' => 'British Folder'],
                ['ger-DE', 'eng-GB'],
            ],
        ];
    }

    /**
     * Test publishing Version with translations restricted by LanguageLimitation.
     *
     * @param array $names
     * @param array $namesToUpdate
     * @param array $allowedTranslationsList
     *
     * @dataProvider providerForPublishVersionWithLanguageLimitation
     *
     * @covers \eZ\Publish\API\Repository\ContentService::createContentDraft
     * @covers \eZ\Publish\API\Repository\ContentService::updateContent
     * @covers \eZ\Publish\API\Repository\ContentService::publishVersion
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\ForbiddenException
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     * @throws \Exception
     */
    public function testPublishVersion(
        array $names,
        array $namesToUpdate,
        array $allowedTranslationsList
    ): void {
        $repository = $this->getRepository();
        $contentService = $repository->getContentService();

        $folder = $this->createFolder($names, 2);

        $repository->getPermissionResolver()->setCurrentUserReference(
            $this->createEditorUserWithLanguageLimitation($allowedTranslationsList)
        );

        $folderDraft = $contentService->createContentDraft($folder->contentInfo);
        $folderUpdateStruct = $contentService->newContentUpdateStruct();
        // set modified translation of Version to the first modified as multiple are not supported yet
        $folderUpdateStruct->initialLanguageCode = array_keys($namesToUpdate)[0];
        foreach ($namesToUpdate as $languageCode => $translatedName) {
            $folderUpdateStruct->setField('name', $translatedName, $languageCode);
        }
        $folderDraft = $contentService->updateContent(
            $folderDraft->getVersionInfo(),
            $folderUpdateStruct
        );
        $contentService->publishVersion($folderDraft->getVersionInfo());

        $folder = $contentService->loadContent($folder->id);
        $updatedNames = array_merge($names, $namesToUpdate);
        foreach ($updatedNames as $languageCode => $expectedValue) {
            self::assertEquals(
                $expectedValue,
                $folder->getField('name', $languageCode)->value->text,
                "Unexpected Field value for {$languageCode}"
            );
        }
    }

    /**
     * Test that publishing version with changes to translations outside limitation values throws unauthorized exception.
     *
     * @param array $names
     *
     * @dataProvider providerForPublishVersionWithLanguageLimitation
     *
     * @covers \eZ\Publish\API\Repository\ContentService::createContentDraft
     * @covers \eZ\Publish\API\Repository\ContentService::updateContent
     * @covers \eZ\Publish\API\Repository\ContentService::publishVersion
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\ForbiddenException
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     */
    public function testPublishVersionIsNotAllowedIfModifiedOtherTranslations(array $names): void
    {
        $repository = $this->getRepository();
        $contentService = $repository->getContentService();

        $folder = $this->createFolder($names, 2);
        $folderDraft = $contentService->createContentDraft($folder->contentInfo);
        $folderUpdateStruct = $contentService->newContentUpdateStruct();
        $folderUpdateStruct->setField('name', 'Updated American Folder', 'eng-US');
        $folderDraft = $contentService->updateContent(
            $folderDraft->getVersionInfo(),
            $folderUpdateStruct
        );

        // switch context to the user not allowed to publish eng-US
        $repository->getPermissionResolver()->setCurrentUserReference(
            $this->createEditorUserWithLanguageLimitation(['ger-DE'])
        );

        $this->expectException(UnauthorizedException::class);
        $contentService->publishVersion($folderDraft->getVersionInfo());
    }

    /**
     * @throws \eZ\Publish\API\Repository\Exceptions\ForbiddenException
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     */
    public function testPublishVersionTranslation(): void
    {
        $repository = $this->getRepository();
        $contentService = $repository->getContentService();
        $permissionResolver = $repository->getPermissionResolver();

        $draft = $this->createMultilingualFolderDraft($contentService);

        $contentUpdateStruct = $contentService->newContentUpdateStruct();

        $contentUpdateStruct->setField('name', 'Draft 1 DE', self::GER_DE);

        $contentService->updateContent($draft->versionInfo, $contentUpdateStruct);

        $admin = $permissionResolver->getCurrentUserReference();
        $permissionResolver->setCurrentUserReference($this->createEditorUserWithLanguageLimitation([self::GER_DE]));

        $contentService->publishVersion($draft->versionInfo, [self::GER_DE]);

        $permissionResolver->setCurrentUserReference($admin);
        $content = $contentService->loadContent($draft->contentInfo->id);
        $this->assertEquals(
            [
                self::ENG_US => 'Published US',
                self::GER_DE => 'Draft 1 DE',
            ],
            $content->fields['name']
        );
    }

    /**
     * @throws \eZ\Publish\API\Repository\Exceptions\ForbiddenException
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     */
    public function testPublishVersionTranslationIsNotAllowed(): void
    {
        $repository = $this->getRepository();
        $contentService = $repository->getContentService();
        $permissionResolver = $repository->getPermissionResolver();

        $draft = $this->createMultilingualFolderDraft($contentService);

        $contentUpdateStruct = $contentService->newContentUpdateStruct();

        $contentUpdateStruct->setField('name', 'Draft 1 EN', self::ENG_US);

        $contentService->updateContent($draft->versionInfo, $contentUpdateStruct);

        $permissionResolver->setCurrentUserReference($this->createEditorUserWithLanguageLimitation([self::GER_DE]));

        $this->expectException(UnauthorizedException::class);
        $contentService->publishVersion($draft->versionInfo, [self::ENG_US]);
    }

    /**
     * @throws \eZ\Publish\API\Repository\Exceptions\ForbiddenException
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     */
    public function testPublishVersionTranslationIsNotAllowedWithTwoEditors(): void
    {
        $repository = $this->getRepository();
        $contentService = $repository->getContentService();
        $permissionResolver = $repository->getPermissionResolver();

        $editorDE = $this->createEditorUserWithLanguageLimitation([self::GER_DE], 'editor-de');
        $editorUS = $this->createEditorUserWithLanguageLimitation([self::ENG_US], 'editor-us');

        // German editor publishes content in German language
        $permissionResolver->setCurrentUserReference($editorDE);

        $folder = $this->createFolder([self::GER_DE => 'German Folder'], 2);

        // American editor creates and saves English draft
        $permissionResolver->setCurrentUserReference($editorUS);

        $folder = $contentService->loadContent($folder->id);
        $folderDraft = $contentService->createContentDraft($folder->contentInfo);
        $folderUpdateStruct = $contentService->newContentUpdateStruct();
        $folderUpdateStruct->setField('name', 'English Folder', self::ENG_US);
        $folderDraft = $contentService->updateContent(
            $folderDraft->versionInfo,
            $folderUpdateStruct
        );

        // German editor tries to publish English translation
        $permissionResolver->setCurrentUserReference($editorDE);
        $folderDraftVersionInfo = $contentService->loadVersionInfo(
            $folderDraft->contentInfo,
            $folderDraft->versionInfo->versionNo
        );
        self::assertTrue($folderDraftVersionInfo->isDraft());
        $this->expectException(UnauthorizedException::class);
        $this->expectExceptionMessage("The User does not have the 'publish' 'content' permission");
        $contentService->publishVersion($folderDraftVersionInfo, [self::ENG_US]);
    }

    /**
     * @throws \eZ\Publish\API\Repository\Exceptions\ForbiddenException
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     */
    public function testPublishVersionTranslationWhenUserHasNoAccessToAllLanguages(): void
    {
        $repository = $this->getRepository();
        $contentService = $repository->getContentService();
        $permissionResolver = $repository->getPermissionResolver();

        $draft = $this->createMultilingualFolderDraft($contentService);

        $contentUpdateStruct = $contentService->newContentUpdateStruct();

        $contentUpdateStruct->setField('name', 'Draft 1 DE', self::GER_DE);
        $contentUpdateStruct->setField('name', 'Draft 1 GB', self::ENG_GB);

        $contentService->updateContent($draft->versionInfo, $contentUpdateStruct);

        $permissionResolver->setCurrentUserReference(
            $this->createEditorUserWithLanguageLimitation([self::GER_DE])
        );
        $this->expectException(UnauthorizedException::class);
        $this->expectExceptionMessage("The User does not have the 'publish' 'content' permission");
        $contentService->publishVersion($draft->versionInfo, [self::GER_DE, self::ENG_GB]);
    }

    /**
     * @dataProvider providerForPrepareDataForTestsWithLanguageLimitationAndDifferentContentTranslations
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     * @throws \eZ\Publish\API\Repository\Exceptions\BadStateException
     * @throws \eZ\Publish\API\Repository\Exceptions\ForbiddenException
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException
     */
    public function testCopyContentWithLanguageLimitationAndDifferentContentTranslations(
        array $limitationValues,
        bool $containsAllTranslations
    ): void {
        $repository = $this->getRepository();
        $contentService = $repository->getContentService();

        $content = $this->testPrepareDataForTestsWithLanguageLimitationAndDifferentContentTranslations(
            $limitationValues,
            $containsAllTranslations
        );

        $locationCreateStruct = new LocationCreateStruct(['parentLocationId' => 2]);

        if ($containsAllTranslations) {
            $clonedContent = $contentService->copyContent($content->contentInfo, $locationCreateStruct);

            self::assertSame($content->getVersionInfo()->languageCodes, $clonedContent->getVersionInfo()->languageCodes);
        } else {
            $this->expectException(UnauthorizedException::class);

            $contentService->copyContent($content->contentInfo, $locationCreateStruct);
        }
    }

    /**
     * @dataProvider providerForPrepareDataForTestsWithLanguageLimitationAndDifferentContentTranslations
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     * @throws \eZ\Publish\API\Repository\Exceptions\BadStateException
     * @throws \eZ\Publish\API\Repository\Exceptions\ForbiddenException
     * @throws \eZ\Publish\API\Repository\Exceptions\InvalidArgumentException
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException
     */
    public function testCopySubtreeWithLanguageLimitationAndDifferentContentTranslations(
        array $limitationValues,
        bool $containsAllTranslations
    ): void {
        $repository = $this->getRepository();
        $locationService = $repository->getLocationService();

        $content = $this->testPrepareDataForTestsWithLanguageLimitationAndDifferentContentTranslations(
            $limitationValues,
            $containsAllTranslations
        );

        $contentLocation = $locationService->loadLocation($content->contentInfo->mainLocationId);
        $targetLocation = $locationService->loadLocation(2);

        if ($containsAllTranslations) {
            $location = $locationService->copySubtree($contentLocation, $targetLocation);

            self::assertSame(
                $content->getVersionInfo()->languageCodes,
                $location->getContent()->getVersionInfo()->languageCodes
            );
        } else {
            $this->expectException(UnauthorizedException::class);

            $locationService->copySubtree($contentLocation, $targetLocation);
        }
    }

    /**
     * @dataProvider providerForPrepareDataForTestsWithLanguageLimitationAndDifferentContentTranslations
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     * @throws \eZ\Publish\API\Repository\Exceptions\BadStateException
     * @throws \eZ\Publish\API\Repository\Exceptions\ForbiddenException
     * @throws \eZ\Publish\API\Repository\Exceptions\InvalidArgumentException
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException
     */
    public function testMoveSubtreeWithLanguageLimitationAndDifferentContentTranslations(
        array $limitationValues,
        bool $containsAllTranslations
    ): void {
        $repository = $this->getRepository();
        $locationService = $repository->getLocationService();
        $contentService = $repository->getContentService();

        $targetContent = $this->createMultilingualFolderDraft($contentService);
        $content = $this->testPrepareDataForTestsWithLanguageLimitationAndDifferentContentTranslations(
            $limitationValues,
            $containsAllTranslations
        );

        $contentLocation = $locationService->loadLocation($content->contentInfo->mainLocationId);
        $targetLocation = $locationService->loadLocation($targetContent->contentInfo->mainLocationId);

        if ($containsAllTranslations) {
            $locationService->moveSubtree($contentLocation, $targetLocation);
            $targetLocation = $locationService->loadLocation($targetLocation->id);

            self::assertSame(
                $content->getVersionInfo()->languageCodes,
                $targetLocation->getContent()->getVersionInfo()->languageCodes
            );
        } else {
            $this->expectException(UnauthorizedException::class);

            $locationService->moveSubtree($contentLocation, $targetLocation);
        }
    }

    /**
     * @dataProvider providerForPrepareDataForTestsWithLanguageLimitationAndDifferentContentTranslations
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     * @throws \eZ\Publish\API\Repository\Exceptions\BadStateException
     * @throws \eZ\Publish\API\Repository\Exceptions\ForbiddenException
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException
     */
    public function testSwapLocationWithLanguageLimitationAndDifferentContentTranslations(
        array $limitationValues,
        bool $containsAllTranslations
    ): void {
        $repository = $this->getRepository();
        $locationService = $repository->getLocationService();

        $content = $this->testPrepareDataForTestsWithLanguageLimitationAndDifferentContentTranslations(
            $limitationValues,
            $containsAllTranslations
        );

        $location = $locationService->loadLocation($content->contentInfo->mainLocationId);
        $location2 = $locationService->loadLocation(2);

        if ($containsAllTranslations) {
            $locationService->swapLocation($location2, $location);

            $location = $locationService->loadLocation($location2->id);

            self::assertSame(
                $content->getVersionInfo()->languageCodes,
                $location->getContent()->getVersionInfo()->languageCodes
            );
        } else {
            $this->expectException(UnauthorizedException::class);

            $locationService->swapLocation($location, $location2);
        }
    }

    /**
     * @dataProvider providerForPrepareDataForTestsWithLanguageLimitationAndDifferentContentTranslations
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     * @throws \eZ\Publish\API\Repository\Exceptions\BadStateException
     * @throws \eZ\Publish\API\Repository\Exceptions\ForbiddenException
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException
     */
    public function testHideLocationWithLanguageLimitationAndDifferentContentTranslations(
        array $limitationValues,
        bool $containsAllTranslations
    ): void {
        $repository = $this->getRepository();
        $locationService = $repository->getLocationService();

        $content = $this->testPrepareDataForTestsWithLanguageLimitationAndDifferentContentTranslations(
            $limitationValues,
            $containsAllTranslations
        );

        $location = $locationService->loadLocation($content->contentInfo->mainLocationId);

        if ($containsAllTranslations) {
            $locationService->hideLocation($location);
            $hiddenLocation = $locationService->loadLocation($location->id);

            self::assertSame(
                $content->getVersionInfo()->languageCodes,
                $hiddenLocation->getContent()->getVersionInfo()->languageCodes
            );
        } else {
            $this->expectException(UnauthorizedException::class);

            $locationService->hideLocation($location);
        }
    }

    /**
     * @dataProvider providerForPrepareDataForTestsWithLanguageLimitationAndDifferentContentTranslations
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     * @throws \eZ\Publish\API\Repository\Exceptions\BadStateException
     * @throws \eZ\Publish\API\Repository\Exceptions\ForbiddenException
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException
     */
    public function testUnhideLocationWithLanguageLimitationAndDifferentContentTranslations(
        array $limitationValues,
        bool $containsAllTranslations
    ): void {
        $repository = $this->getRepository();
        $locationService = $repository->getLocationService();

        $content = $this->testPrepareDataForTestsWithLanguageLimitationAndDifferentContentTranslations(
            $limitationValues,
            $containsAllTranslations
        );

        $location = $locationService->loadLocation($content->contentInfo->mainLocationId);

        if ($containsAllTranslations) {
            $locationService->unhideLocation($location);
            $revealedLocation = $locationService->loadLocation($location->id);

            self::assertSame(
                $content->getVersionInfo()->languageCodes,
                $revealedLocation->getContent()->getVersionInfo()->languageCodes
            );
        } else {
            $this->expectException(UnauthorizedException::class);

            $locationService->unhideLocation($location);
        }
    }

    /**
     * @dataProvider providerForPrepareDataForTestsWithLanguageLimitationAndDifferentContentTranslations
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     * @throws \eZ\Publish\API\Repository\Exceptions\BadStateException
     * @throws \eZ\Publish\API\Repository\Exceptions\ForbiddenException
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException
     */
    public function testHideContentWithLanguageLimitationAndDifferentContentTranslations(
        array $limitationValues,
        bool $containsAllTranslations
    ): void {
        $repository = $this->getRepository();
        $contentService = $repository->getContentService();

        $content = $this->testPrepareDataForTestsWithLanguageLimitationAndDifferentContentTranslations(
            $limitationValues,
            $containsAllTranslations
        );

        if ($containsAllTranslations) {
            $contentService->hideContent($content->contentInfo);

            $content = $contentService->loadContent($content->id);

            self::assertTrue($content->contentInfo->isHidden);
        } else {
            $this->expectException(UnauthorizedException::class);

            $contentService->hideContent($content->contentInfo);
        }
    }

    /**
     * @dataProvider providerForPrepareDataForTestsWithLanguageLimitationAndDifferentContentTranslations
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     * @throws \eZ\Publish\API\Repository\Exceptions\BadStateException
     * @throws \eZ\Publish\API\Repository\Exceptions\ForbiddenException
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException
     */
    public function testRevealContentWithLanguageLimitationAndDifferentContentTranslations(
        array $limitationValues,
        bool $containsAllTranslations
    ): void {
        $repository = $this->getRepository();
        $contentService = $repository->getContentService();

        $content = $this->testPrepareDataForTestsWithLanguageLimitationAndDifferentContentTranslations(
            $limitationValues,
            $containsAllTranslations
        );

        if ($containsAllTranslations) {
            $contentService->revealContent($content->contentInfo);

            $content = $contentService->loadContent($content->id);

            self::assertFalse($content->contentInfo->isHidden);
        } else {
            $this->expectException(UnauthorizedException::class);

            $contentService->revealContent($content->contentInfo);
        }
    }

    /**
     * @dataProvider providerForPrepareDataForTestsWithLanguageLimitationAndDifferentContentTranslations
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException
     * @throws \eZ\Publish\API\Repository\Exceptions\BadStateException
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     * @throws \eZ\Publish\API\Repository\Exceptions\ForbiddenException
     */
    public function testPrepareDataForTestsWithLanguageLimitationAndDifferentContentTranslations(
        array $limitationValues,
        bool $containsAllTranslations
    ): Content {
        $repository = $this->getRepository();
        $contentService = $repository->getContentService();
        $permissionResolver = $repository->getPermissionResolver();

        $draft = $this->createMultilingualFolderDraft($contentService);
        $content = $contentService->publishVersion($draft->getVersionInfo());

        $containsAllTranslations
            ? self::assertEmpty(array_diff($content->getVersionInfo()->languageCodes, $limitationValues))
            : self::assertNotEmpty(array_diff($content->getVersionInfo()->languageCodes, $limitationValues));

        $permissionResolver->setCurrentUserReference(
            $this->createEditorUserWithLanguageLimitation($limitationValues)
        );

        return $content;
    }

    /**
     * @return iterable<array{array<string>, bool}>
     */
    public function providerForPrepareDataForTestsWithLanguageLimitationAndDifferentContentTranslations(): array
    {
        return [
            [[self::GER_DE], false],
            [[self::GER_DE, self::ENG_US, self::ENG_GB], true],
        ];
    }

    /**
     * @param \eZ\Publish\API\Repository\ContentService $contentService
     *
     * @return \eZ\Publish\API\Repository\Values\Content\Content
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\ForbiddenException
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException
     * @throws \eZ\Publish\API\Repository\Exceptions\UnauthorizedException
     */
    private function createMultilingualFolderDraft(ContentService $contentService): Content
    {
        $publishedContent = $this->createFolder(
            [
                self::ENG_US => 'Published US',
                self::GER_DE => 'Published DE',
            ],
            $this->generateId('location', 2)
        );

        return $contentService->createContentDraft($publishedContent->contentInfo);
    }
}
