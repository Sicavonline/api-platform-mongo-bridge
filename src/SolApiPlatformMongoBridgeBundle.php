<?php

namespace Sol\ApiPlatform\MongoBridge;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class SolApiPlatformMongoBridgeBundle extends Bundle
{
    public function __construct()
    {
        // die('ok');
    }

    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container)
    {
        parent::build($container);
    }
}