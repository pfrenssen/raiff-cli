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
     * Returns the configuration manager.
     *
     * @return \RaiffCli\Config\ConfigManager
     *   The configuration manager.
     */
    protected function getConfigManager()
    {
       return $this->getContainer()->get('config.manager');
    }

}
