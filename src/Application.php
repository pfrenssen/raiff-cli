<?php

namespace RaiffCli;

use Symfony\Component\Console\Application as ConsoleApplication;

class Application extends ConsoleApplication
{

    /**
     * @return \RaiffCli\Helper\ContainerHelper
     */
    public function getContainer()
    {
        return $this->getHelperSet()->get('container');
    }

    /**
     * @return \RaiffCli\Config
     */
    public function getConfig()
    {
        return $this->getContainer()->get('config');
    }

}
