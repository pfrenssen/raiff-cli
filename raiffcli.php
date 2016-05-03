<?php

require_once __DIR__ . '/vendor/autoload.php';

use RaiffCli\Command\InLeva;
use Symfony\Component\Console\Application;

$application = new Application();
$application->add(new InLeva());
$application->run();

