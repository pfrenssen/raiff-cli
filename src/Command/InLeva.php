<?php

namespace RaiffCli\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

class InLeva extends Command
{
    protected function configure()
    {
        $this
            ->setName('transfer:in-leva')
            ->setDescription('Do a bank transfer in leva.')
            ->addArgument(
                'account',
                InputArgument::REQUIRED,
                'The account to use: either "individual" or "corporate"'
            );
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        // Ask for the account if this argument is omitted or invalid.
        $account = $input->getArgument('account');
        if (empty($account) || !in_array($account, ['individual', 'corporate'])) {
            $helper = $this->getHelper('question');
            $question = new ChoiceQuestion(
                'Please select the account to use',
                array('individual', 'corporate'),
                0
            );
            $question->setErrorMessage('Account %s is invalid.');
            $input->setArgument('account', $helper->ask($input, $output, $question));
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $account = $input->getArgument('account');
        $output->writeln('Chosen account: ' . $account);
    }
}
