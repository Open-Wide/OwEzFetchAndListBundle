<?php

namespace Ow\Bundle\OwEzFetchAndListBundle\Wrapper;

use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ContainerWrapper extends ContainerAware
{
    /**
     * @param ContainerInterface $container
     */
    public function setContainer(ContainerInterface $container = null)
    {
        parent::setContainer($container);
        $this->setServices();
    }

    protected function setServices()
    {
        d('iciservices 1');
    }
}