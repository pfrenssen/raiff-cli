<?php

declare(strict_types = 1);

namespace RaiffCli\Command\Transfer;

use RaiffCli\Command\CommandBase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

/**
 * Console command to sign pending transactions.
 */
class Sign extends CommandBase
{

    /**
     * {@inheritdoc}
     */
    protected function configure() : void
    {
        parent::configure();

        $this
            ->addAccountTypeArgument()
            ->setName('transfer:sign')
            ->setDescription('Sign pending transfers.');
    }

    /**
     * {@inheritdoc}
     */
    protected function interact(InputInterface $input, OutputInterface $output) : void
    {
        $this->askAccountType($input, $output);
        $account_type = $input->getArgument('account-type');
        $output->writeln("<info>Selected account type: $account_type</info>");
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output) : void
    {
        $this->session = $this->getSession();

        $account_type = $input->getArgument('account-type');

        // Log in.
        $this->logIn();

        // Choose between individual and corporate account.
        $this->selectAccountType($account_type);

        // Navigate to the transfers overview page, waiting a moment for the
        // dynamic page loading to complete.
        $this->clickMainNavigationLink('Transfers');
        $link_text = $account_type === 'corporate' ? 'In leva' : 'Next';
        $this->waitForLinkButtonPresence($link_text);
        $this->clickSecondaryNavigationLink('Pending');
        $this->waitForElementPresence('//a[contains(@data-bind, "filterToggler")]', 'xpath');

        // Check if there are any pending transfers.
        $element = $this->session->getPage()->find('xpath', '//table//tr//span[contains(@data-bind, "Payment.PayerName")]');
        if (empty($element)) {
            $output->writeln("<comment>The $account_type account has no pending transfers.</comment>");
            return;
        }

        // Click the 'Sign' button (for corporate accounts), or the 'Send'
        // button (for individual accounts) for all transfers.
        $this->selectAllTransfers();
        $button_id = $account_type === 'corporate' ? 'Sign' : 'Send';
        $this->clickLinkButton($button_id);

        // Wait for the dialog box to appear.
        $this->waitForElementPresence('input#id_Model_Response');

        // Confirm any declarations of origin of money that are present in the
        // dialog box.
        $transaction_rows = $this->session->getPage()->findAll('css', '#SignSendPreview > table tr');
        /** @var \Behat\Mink\Element\NodeElement $transaction_row */
        foreach ($transaction_rows as $transaction_row) {
            $declaration_link = $transaction_row->find('css', 'a.dirtyMoney');
            if (!empty($declaration_link)) {
                $declaration_link->click();
                $this->waitForElementPresence('//div[@id="DirtyMoneyDeclarationForm"]', 'xpath');
                $this->session->getPage()->pressButton('sendDirtyMontdlnk');
                $this->waitForElementPresence('//div[@id="DirtyMoneyDeclarationForm"]', 'xpath', FALSE);
            }
        }

        // Retrieve the challenge.
        $element = $this->session->getPage()->find('xpath', '//span[contains(@data-bind, "Model.Challenge")]');
        $challenge = trim($element->getText());

        // Ask the response in the console.
        $helper = $this->getHelper('question');
        $question = new Question('Challenge: ' . $challenge . '. Response: ');
        $question->setValidator([$this, 'numericValidator']);
        $response = trim($helper->ask($input, $output, $question));

        // Fill in the response in the form.
        $this->session->getPage()->fillField('id_Model_Response', $response);
        $this->clickLinkButton('OK');
        $this->waitForSuccessMessage();

        // Print results.
        $this->printMessages($output);

        // In corporate accounts the sending of the transactions is performed in
        // a separate step. This is not needed for personal accounts.
        if ($account_type === 'corporate') {
            // Navigate back to the overview.
            $this->closeDialog();
            $this->clickMainNavigationLink('Transfers');
            $this->waitForLinkButtonPresence('In leva');
            $this->clickSecondaryNavigationLink('Pending');
            $this->waitForElementPresence('//a[contains(@data-bind, "filterToggler")]', 'xpath');

            // Check all transfers and click on "Send".
            $this->selectAllTransfers();
            $this->clickLinkButton('Send');

            // Wait for the dialog box to appear.
            $this->waitForLinkButtonPresence('OK');

            // Confirm.
            $this->clickLinkButton('OK');
            $this->waitForSuccessMessage();

            // Print results.
            $this->printMessages($output);
        }
    }

    /**
     * Outputs any success messages present in the web page to the console.
     *
     * @param OutputInterface $output
     *   The output handler.
     */
    protected function printMessages(OutputInterface $output) : void
    {
        $elements = $this->session->getPage()->findAll('xpath', '//span[@data-bind = "text: Message"]');
        foreach ($elements as $element) {
            $result = trim($element->getText());
            $output->writeln("<comment>$result</comment>");
        }
    }

    /**
     * Selects all transfers.
     */
    protected function selectAllTransfers() : void
    {
        // Expand the table to show all transfers.
        $this->clickLinkButton('Show all');
        $this->waitUntilPageLoaded();

        // Click the checkbox to select all transfers.
        $this->clickLinkButton('Select all');
    }

}
