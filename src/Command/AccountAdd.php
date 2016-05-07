<?php

namespace RaiffCli\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

/**
 * Command that adds an account to the account configuration.
 */
class AccountAdd extends CommandBase
{

    protected function configure()
    {
        $this
            ->setName('account:add')
            ->setDescription('Add a bank account.');
        $this->addAccountTypeArgument();
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        // Ask for the account if this argument is omitted or invalid.
        $this->askAccountType($input, $output);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $helper = $this->getHelper('question');
        $question = new Question('Please enter the name of the account to add: ');
        $question->setValidator([$this, 'requiredValidator']);
        $account = $helper->ask($input, $output, $question);
        $account_type = $input->getArgument('account-type');

        /** @var \RaiffCli\Config\Config $config */
        $config = $this->getConfigManager()->get('accounts');
        $accounts = $config->get($account_type, []);
        $accounts[] = $account;
        $config->set($account_type, $accounts);
        $config->save();

        $output->writeln("Added account '$account'.");
    }

}
