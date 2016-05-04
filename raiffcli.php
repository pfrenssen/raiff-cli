<?php

require_once __DIR__ . '/vendor/autoload.php';

use RaiffCli\Command\InLeva;
use RaiffCli\Helper\ContainerHelper;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Application;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

// Initialize dependency injection container.
$container = new ContainerBuilder();

// Register services.
$loader = new YamlFileLoader($container, new FileLocator(__DIR__));
$loader->load('services.yml');

// Initialize application.
$application = new Application();

// Register helpers.
$helperSet = $application->getHelperSet();
$helperSet->set(new ContainerHelper($container));

// Add commands.
$application->add(new InLeva());

$application->run();
