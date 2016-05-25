<?php

namespace RaiffCli\Command\Transfer;

use Behat\Mink\Driver\Selenium2Driver;
use Behat\Mink\Session;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Behat\Mink\Mink;
use Zumba\Mink\Driver\PhantomJSDriver;

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

        // Start from the homepage.
        $this->navigateToHomepage();

        foreach ($transactions as $transaction) {
            // Open the "In foreign currency" payment form.
            $this->waitUntilElementPresent('#NewPaymentTypes');
            $this->session->getPage()->clickLink('In foreign currency');

            // Choose the account.
            $this->chooseAccount($account);

            // Fill in the fields.
            $this->waitUntilElementPresent('#PayeeName');
            $this->session->getPage()->fillField('Document.PayeeName', $transaction['recipient']['name']);
            $this->session->getPage()->fillField('Document.PayeeAccountNumber', $transaction['recipient']['iban']);
            $this->session->getPage()->fillField('Document.PayeeAddress', $transaction['recipient']['address']);
            $this->session->getPage()->fillField('Document.PayeeBankSWIFT', $transaction['recipient']['bic']);
            $this->session->getPage()->fillField('Document.Amount', $transaction['amount']);
            $this->session->getPage()->fillField('Document.Description', $transaction['description']);

            // Select the currency.
            // @todo Support currencies other than EUR.
            $this->session->getPage()->findById('CCYPicker-button')->click();
            $this->session->getPage()->clickLink('EUR');

            // Select the country.
            // @todo Support countries other than Belgium.
            $this->session->getPage()->selectFieldOption('Document.PayeeBankCountryPicker', '056');

            // Select the operation type.
            // @todo Support other operation types.
            $this->session->getPage()->findById('FCCYOpCodeSelector')->click();
            // The operation type dialog box doesn't have an identifier. Nice.
            $operation_type_select_xpath = '//div[@aria-labelledby="ui-dialog-title-1"]/div/fieldset[@class="col1"]/div[@class="column"][1]/select';
            $this->waitUntilElementPresent($operation_type_select_xpath, 'xpath');
            // @todo Support other operations than "Other private transfers".
            $this->session->getPage()->find('xpath', $operation_type_select_xpath)->selectOption('4');
            $this->waitUntilElementPresent('#OpCodePick');
            $this->session->getPage()->selectFieldOption('OpCodePick', '629');
            $this->session->getPage()->find('xpath', '//div[@aria-labelledby="ui-dialog-title-1"]/div/div/button')->click();

            // Submit the form.
            sleep(10);
            $this->session->getPage()->findById('btnSave')->click();
            $this->waitUntilElementPresent('#SaveOKResultHolder');

            // Provide feedback about the progress.
            $output->writeln("<info>Registered transaction to {$transaction['recipient']['name']} for {$transaction['amount']} BGN: '{$transaction['description']}'</info>");
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function getRecipientNationality() {
        return 'foreign';
    }

}
