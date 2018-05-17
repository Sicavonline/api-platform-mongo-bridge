<?php

namespace Sol\ApiPlatform\MongoBridge;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Class SolApiPlatformMongoBridgeBundle
 * @package Sol\ApiPlatform\MongoBridge
 */
class SolApiPlatformMongoBridgeBundle extends Bundle
{
    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container)
    {
        parent::build($container);
    }
}