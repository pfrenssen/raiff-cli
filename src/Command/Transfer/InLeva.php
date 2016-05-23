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

        $base_url = $this->config->get('base_url');

        // Visit the homepage.
        /** @var \Behat\Mink\Session $session */
        $session = $this->session;
        $session->visit($base_url);

        // Log in.
        $session->getPage()->fillField('userName', $this->config->get('credentials.username'));
        $session->getPage()->fillField('pwd', $this->config->get('credentials.password'));
        $session->getPage()->find('css', '#m_ctrl_Page button.primary')->click();

        // Choose between individual and corporate account.
        $this->waitUntilElementPresent($session, '#main .themebox.ind');
        $selector = $account_type === 'individual' ? '#main .themebox.ind a.btn.secondary' : '#main .themebox.corp a.btn.secondary';
        $session->getPage()->find('css', $selector)->click();
        $this->waitUntilElementPresent($session, '#head a.logo');

        // Start from the homepage.
        $session->getPage()->find('css', '#head a.logo')->click();

        foreach ($transactions as $transaction) {
            // Open the "In leva" payment form.
            $this->waitUntilElementPresent($session, '#NewPaymentTypes');
            $session->getPage()->clickLink('In leva');

            // Choose the account.
            $this->waitUntilElementPresent($session, '#showPayerPicker');
            $session->getPage()->findById('showPayerPicker')->click();
            $this->waitUntilElementPresent($session, '#accounts');
            $session->getPage()->find('xpath', '//*[@id="accounts"]/table//tr/td[text()[contains(., "' . $account . '")]]')->click();

            // Fill in the fields.
            $this->waitUntilElementPresent($session, '#Document_PayeeName');
            $session->getPage()->fillField('Document_PayeeName', $transaction['recipient']['name']);
            $session->getPage()->fillField('Document_PayeeIBAN', $transaction['recipient']['iban']);
            $session->getPage()->fillField('Document_Amount', $transaction['amount']);
            $session->getPage()->fillField('Document_Description1', $transaction['description']);

            // Submit the form.
            $session->getPage()->findById('btnSave')->click();
            $this->waitUntilElementPresent($session, '#SaveOKResultHolder');

            // Provide feedback about the progress.
            $output->writeln("<info>Registered transaction to {$transaction['recipient']['name']} for {$transaction['amount']} BGN: '{$transaction['description']}'</info>");
        }
    }

}
