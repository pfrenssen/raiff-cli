<?php

namespace RaiffCli\Command\Transfer;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use WebDriver\Exception\UnknownError;

/**
 * Console command to execute a transaction in foreign currency.
 */
class InForeignCurrency extends TransferBase
{

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('transfer:foreign')
            ->setDescription('Do a bank transfer in foreign currency.');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
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
            try {
                $this->clickSecondaryNavigationLink($link);
            } catch (UnknownError $e) {
                // @todo At this point the following error might occur:
                //   'Element "#CreatePayment/Index" is not clickable at point
                //   (536, 204). Other element would receive the click: <div
                //   class="overlay-loading">...</div>'
                //   This overlay appears only rarely. I haven't seen it in the
                //   browser yet so I don't know its markup. Let's try closing
                //   any possible dialog that might be on the page.
                $this->closeDialog();
                $this->clickSecondaryNavigationLink($link);
            }

            // Open the "In foreign currency" payment form.
            $this->clickLinkButton('In foreign currency');
            $this->waitForElementPresence('.pmt-form');

            // Choose the account.
            $this->chooseAccount($account);

            // Fill in the fields.
            $this->session->getPage()->fillField('id_Model_GenericPayment_Document_Model_PayeeName', $transaction['recipient']['name']);
            $this->session->getPage()->fillField('id_Model_GenericPayment_Document_Model_PayeeAccountNumber', $transaction['recipient']['iban']);
            $this->session->getPage()->fillField('id_Model_GenericPayment_Document_Model_PayeeAddress', $transaction['recipient']['address']);
            $this->session->getPage()->fillField('id_Model_GenericPayment_Document_Model_PayeeBankSWIFT', $transaction['recipient']['bic']);
            $this->session->getPage()->fillField('id_Model_GenericPayment_Document_Model_Amount', $transaction['amount']);
            $this->session->getPage()->fillField('id_Model_GenericPayment_Document_Model_Description', $transaction['description']);

            // Select the currency.
            // @todo Support currencies other than EUR.
            $this->selectOptionByElementText('id_Model_GenericPayment_Document_SelectedCCY', 'EUR');

            // Select the country.
            // @todo Support countries other than Belgium.
            $this->session->getPage()->selectFieldOption('id_Model_GenericPayment_Document_Model_PayeeBankCountryCode', '056');

            // Select the operation type.
            // @todo Support other operation types.
            $this->selectOptionByElementText('id_Model_OpCodeBNBInfo_SelectedOpMode', 'Transfers');
            // @todo Support other operations than "Other private transfers".
            $this->selectOptionByElementText('id_Model_OpCodeBNBInfo_SelectedOpCode', 'Other private transfers');

            // Submit the form.
            $this->clickLinkButton('Save');
            $this->waitForSuccessMessage();

            // The transaction succeeded. Remove it from the disk cache.
            $this->deleteStoredTransaction($transaction);

            // Provide feedback about the progress.
            $output->writeln("<info>Registered transaction to {$transaction['recipient']['name']} for {$transaction['amount']} EUR: '{$transaction['description']}'</info>");
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function getRecipientNationality(): string
    {
        return 'foreign';
    }

}
