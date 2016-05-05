<?php

namespace RaiffCli\Command;

use Symfony\Component\Console\Command\Command;

/**
 * Base class for commands.
 */
abstract class CommandBase extends Command
{

    /**
     * Returns the dependency injection container helper.
     *
     * @return \RaiffCli\Helper\ContainerHelper
     *   The dependency injection container helper.
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
