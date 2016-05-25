<?php

namespace RaiffCli\Command\Transfer;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Console command to execute a transaction in leva.
 */
class InLeva extends TransferBase
{

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('transfer:in-leva')
            ->setDescription('Do a bank transfer in leva.');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $account_type = $input->getArgument('account-type');
        $account = $input->getArgument('account');
        $transactions = $input->getArgument('transactions');
        $session = $this->session;

        // Log in.
        $this->logIn();

        // Choose between individual and corporate account.
        $this->selectAccountType($account_type);

        // Start from the homepage.
        $this->navigateToHomepage();

        foreach ($transactions as $transaction) {
            // Open the "In leva" payment form.
            $this->waitUntilElementPresent('#NewPaymentTypes');
            $session->getPage()->clickLink('In leva');

            // Choose the account.
            $this->chooseAccount($account);

            // Fill in the fields.
            $this->waitUntilElementPresent('#Document_PayeeName');
            $session->getPage()->fillField('Document_PayeeName', $transaction['recipient']['name']);
            $session->getPage()->fillField('Document_PayeeIBAN', $transaction['recipient']['iban']);
            $session->getPage()->fillField('Document_Amount', $transaction['amount']);
            $session->getPage()->fillField('Document_Description1', $transaction['description']);

            // Submit the form.
            $session->getPage()->findById('btnSave')->click();
            $this->waitUntilElementPresent('#SaveOKResultHolder');

            // Provide feedback about the progress.
            $output->writeln("<info>Registered transaction to {$transaction['recipient']['name']} for {$transaction['amount']} BGN: '{$transaction['description']}'</info>");
        }
    }

}
