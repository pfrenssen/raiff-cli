<?php

namespace RaiffCli\Command;

use Symfony\Component\Console\Command\Command;

abstract class CommandBase extends Command
{

    /**
     * @return \RaiffCli\Helper\ContainerHelper
     */
    protected function getContainer()
    {
        return $this->getHelper('container');
    }

    /**
     * @return \RaiffCli\Config
     */
    protected function getConfig()
    {
       return $this->getContainer()->get('config');
    }

}
