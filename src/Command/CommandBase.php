<?php

namespace RaiffCli\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

/**
 * Base class for commands.
 */
abstract class CommandBase extends Command
{

    /**
     * Adds an argument to the command for the account type.
     */
    protected function addAccountTypeArgument()
    {
        $this->addArgument('account-type', InputArgument::REQUIRED, 'The account type to use: either "individual" or "corporate"');
    }

    /**
     * Asks the user for the account type.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     *   The input interface.
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *   The output interface.
     */
    protected function askAccountType(InputInterface $input, OutputInterface $output)
    {
        $account = $input->getArgument('account-type');
        if (empty($account) || !in_array($account, ['individual', 'corporate'])) {
            $helper = $this->getHelper('question');
            $question = new ChoiceQuestion('Please select the account type to use', array('individual', 'corporate'), 0);
            $question->setErrorMessage('Account type %s is invalid.');
            $input->setArgument('account-type', $helper->ask($input, $output, $question));
        }
    }

    /**
     * Validator for required arguments.
     *
     * @param string $input
     *   The input from the user.
     *
     * @return string
     *   The input from the user.
     *
     * @throws \InvalidArgumentException
     *   Thrown when the input is empty.
     */
    public static function requiredValidator($input)
    {
        if (empty(trim($input))) {
            throw new \InvalidArgumentException('Please enter a value.');
        }
        return $input;
    }

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
