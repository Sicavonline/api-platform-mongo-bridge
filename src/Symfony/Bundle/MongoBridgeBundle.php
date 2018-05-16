<?php
namespace Sol\ApiPlatform\Symfony\Bundle;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Class MongoBridgeBundle
 */
final class MongoBridgeBundle extends Bundle
{
    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container)
    {
        die('ok');
        parent::build($container);
    }
}
