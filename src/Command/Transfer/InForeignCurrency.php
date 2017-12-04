<?php

namespace RaiffCli\Command\Transfer;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

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
        $this->clickMainNavigationLink('transfers');

        foreach ($transactions as $transaction) {
            // Click "New transfer" for corporate accounts, or "Transfer types"
            // for individual accounts.
            $link = $account_type === 'corporate' ? 'New transfer' : 'Transfer Types';
            $this->clickSecondaryNavigationLink($link);
            // Open the "In foreign currency" payment form.
            $this->clickLinkButton('In foreign currency');

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
            $element = $this->session->getPage()->findById('id_Model_GenericPayment_Document_SelectedCCY');
            // @todo This doesn't work, probably because there are no values
            //   associated with the options.
            $element->selectOption('EUR');

            // Select the country.
            // @todo Support countries other than Belgium.
            $this->session->getPage()->selectFieldOption('id_Model_GenericPayment_Document_Model_PayeeBankCountryCode', '056');

            // Select the operation type.
            // @todo Support other operation types.
            throw new \Exception(__METHOD__ . ' needs to be completely ported.');
            $this->session->getPage()->findById('FCCYOpCodeSelector')->click();
            // The operation type dialog box doesn't have an identifier. Nice.
            $operation_type_select_xpath = '//div[@aria-labelledby="ui-dialog-title-1"]/div/fieldset[@class="col1"]/div[@class="column"][1]/select';
            $this->waitForElementPresence($operation_type_select_xpath, 'xpath');
            // @todo Support other operations than "Other private transfers".
            $this->session->getPage()->find('xpath', $operation_type_select_xpath)->selectOption('4');
            $this->waitForElementPresence('#OpCodePick');
            $this->session->getPage()->selectFieldOption('OpCodePick', '629');
            $this->session->getPage()->find('xpath', '//div[@aria-labelledby="ui-dialog-title-1"]/div/div/button')->click();

            // Submit the form.
            sleep(1);
            $this->session->getPage()->findById('btnSave')->click();
            $this->waitForElementPresence('#SaveOKResultHolder');

            // The transaction succeeded. Remove it from the disk cache.
            $this->deleteStoredTransaction($transaction);

            // Provide feedback about the progress.
            $output->writeln("<info>Registered transaction to {$transaction['recipient']['name']} for {$transaction['amount']} EUR: '{$transaction['description']}'</info>");
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function getRecipientNationality() {
        return 'foreign';
    }

}
