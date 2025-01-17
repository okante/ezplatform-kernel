<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace eZ\Bundle\EzPublishCoreBundle\Tests\DependencyInjection\Compiler;

use eZ\Bundle\EzPublishCoreBundle\DependencyInjection\Compiler\NotificationRendererPass;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractCompilerPassTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class NotificationRendererPassTest extends AbstractCompilerPassTestCase
{
    public const NOTIFICATION_RENDERER_ID = 'notification.renderer.id';
    public const NOTIFICATION_ALIAS = 'example';

    protected function setUp(): void
    {
        parent::setUp();

        $this->setDefinition(NotificationRendererPass::REGISTRY_DEFINITION_ID, new Definition());
    }

    protected function registerCompilerPass(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new NotificationRendererPass());
    }

    public function testAddRenderer()
    {
        $definition = new Definition();
        $definition->addTag(NotificationRendererPass::TAG_NAME, [
            'alias' => self::NOTIFICATION_ALIAS,
        ]);

        $this->setDefinition(self::NOTIFICATION_RENDERER_ID, $definition);
        $this->compile();

        $this->assertContainerBuilderHasServiceDefinitionWithMethodCall(
            NotificationRendererPass::REGISTRY_DEFINITION_ID,
            'addRenderer',
            [self::NOTIFICATION_ALIAS, new Reference(self::NOTIFICATION_RENDERER_ID)]
        );
    }

    public function testAddRendererWithoutAliasThrowsLogicException()
    {
        $this->expectException(\LogicException::class);

        $definition = new Definition();
        $definition->addTag(NotificationRendererPass::TAG_NAME);

        $this->setDefinition(self::NOTIFICATION_RENDERER_ID, $definition);
        $this->compile();
    }
}
