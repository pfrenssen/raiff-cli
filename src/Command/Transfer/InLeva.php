<?php

declare(strict_types = 1);

namespace RaiffCli\Command\Transfer;

use RaiffCli\Exception\ElementPresenceException;
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
    protected function configure() : void
    {
        parent::configure();

        $this
            ->setName('transfer:in-leva')
            ->setDescription('Do a bank transfer in leva.');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output) : void
    {
        $this->session = $this->getSession();

        $account_type = $input->getArgument('account-type');
        $account = $input->getArgument('account');
        $transactions = $input->getArgument('transactions');

        // Log in.
        $this->logIn();

        // Choose between individual and corporate account.
        $this->selectAccountType($account_type);

        // Start from the Transfers page.
        $this->clickMainNavigationLink('Transfers');

        foreach ($transactions as $transaction) {
            // Click "New transfer" for corporate accounts, or "Transfer types"
            // for individual accounts.
            $link = $account_type === 'corporate' ? 'New transfer' : 'Transfer Types';

            // The navigation links do not immediately work after they appear. Retry a few times if the page doesn't
            // change after clicking it.
            $attempts = 0;
            do {
                try {
                    $this->clickSecondaryNavigationLink($link);
                    $this->waitForLinkButtonPresence('In leva', TRUE, 1000000);
                    break;
                }
                catch (ElementPresenceException $e) {}
            }
            while (++$attempts < 3);

            // Open the "In leva" payment form.
            $this->clickLinkButton('In leva');
            $this->waitForElementPresence('.pmt-form');

            // Choose the account.
            $this->chooseAccount($account);

            // Fill in the fields.
            $this->session->getPage()->fillField('Name', $transaction['recipient']['name']);
            $this->session->getPage()->fillField('IBAN', $transaction['recipient']['iban']);
            $this->session->getPage()->fillField('Amount', $transaction['amount']);
            $this->session->getPage()->fillField('Details', $transaction['description']);

            // If the amount is over 30000 leva the origin of the funds needs to
            // be declared.
            if ($transaction['amount'] > 30000.00 && !empty($transaction['origin'])) {
                $select_element = $this->session->getPage()->find('xpath', '//select[contains(@id, "Model_DirtyMoney_Model_DirtyMoney")]');
                $select_element->setValue(array_search($transaction['origin'], static::getFundOrigins()));
            }

            // Submit the form.
            $this->clickLinkButton('Save');
            $this->waitForSuccessMessage();

            // The transaction succeeded. Remove it from the disk cache.
            $this->deleteStoredTransaction($transaction);

            // Provide feedback about the progress.
            $output->writeln("<info>Registered transaction to {$transaction['recipient']['name']} for {$transaction['amount']} BGN: '{$transaction['description']}'</info>");
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function getRecipientNationality() : string
    {
        return 'bulgaria';
    }

}
