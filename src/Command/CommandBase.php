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
     *
     * @return $this
     */
    protected function addAccountTypeArgument()
    {
        $this->addArgument('account-type', InputArgument::REQUIRED, 'The account type to use: either "individual" or "corporate"');

        return $this;
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
            $question = new ChoiceQuestion('Please select the account type (default: individual):', array('individual', 'corporate'), 0);
            $question->setErrorMessage('Account type %s is invalid.');
            $input->setArgument('account-type', $helper->ask($input, $output, $question));
        }
    }

    /**
     * Adds an argument to the command for the account.
     *
     * @return $this
     */
    protected function addAccountArgument()
    {
        $this->addArgument('account', InputArgument::REQUIRED, 'The account to use');

        return $this;
    }

    /**
     * Asks the user for the account.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     *   The input interface.
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *   The output interface.
     * @param string $type
     *   The account type, either 'individual' or 'corporate'.
     *
     * @throws \Exception
     *   Thrown when there are multiple accounts for one account type. This is
     *   not implemented yet.
     * @throws \LogicException
     *   Thrown when no accounts have been configured for the given account
     *   type.
     */
    protected function askAccount(InputInterface $input, OutputInterface $output, $type)
    {
        $config = $this->getConfigManager()->get('accounts');
        $accounts = $config->get($type, []);

        if (empty($accounts)) {
            throw new \LogicException("There are no $type accounts. Please add one using the 'account:add' command.");
        }

        // If there is only one account, set it without asking questions.
        if (count($accounts) == 1) {
            $account = reset($accounts);
            $input->setArgument('account', $account);
        } else {
            // @todo Ask which account to use if there are multiple accounts.
            throw new \Exception('Support for multiple accounts is not implemented yet.');
        }
    }

    /**
     * Asks for a recipient with an autocomplete field.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     *   The input interface.
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *   The output interface.
     * @param bool $allow_empty
     *   Whether or not an empty result is acceptable.
     * @param string $type
     *   The recipient type to return, either 'bulgaria' or 'foreign'. Leave
     *   empty to choose between all recipients.
     *
     * @return array
     *   The recipient array.
     */
    protected function askRecipient(InputInterface $input, OutputInterface $output, $allow_empty = false, $type = '')
    {
        // Retrieve the recipients.
        $config = $this->getConfigManager()->get('recipients');
        $recipients = $config->get('recipients', []);

        // Filter recipients by type.
        switch ($type) {
            case 'bulgaria':
                $recipients = array_filter($recipients, function ($recipient) {
                    return substr($recipient['iban'], 0, 2) === 'BG';
                });
                break;
            case 'foreign':
                $recipients = array_filter($recipients, function ($recipient) {
                    return substr($recipient['iban'], 0, 2) !== 'BG';
                });
                break;
            default:
                // Do not filter.
                break;
        }

        // Add an empty option.
        $default = null;
        if ($allow_empty) {
            $default = '- skip -';
            $recipients[]['alias'] = $default;
        }

        $helper = $this->getHelper('question');
        $question = new ChoiceQuestion('Recipient:', array_column($recipients, 'alias'), $default);
        $question->setErrorMessage('Recipient %s does not exist.');

        $recipient = $helper->ask($input, $output, $question);

        if ($recipient === $default) {
            return [];
        }

        $recipients = array_combine(array_column($recipients, 'alias'), $recipients);
        return $recipients[$recipient];
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
