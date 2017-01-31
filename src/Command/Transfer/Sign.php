<?php

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
    protected function configure()
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
    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $this->askAccountType($input, $output);
        $account_type = $input->getArgument('account-type');
        $output->writeln("<info>Selected account type: $account_type</info>");
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->session = $this->getSession();

        $account_type = $input->getArgument('account-type');

        // Log in.
        $this->logIn();

        // Choose between individual and corporate account.
        $this->selectAccountType($account_type);

        // Start from the homepage.
        $this->navigateToHomepage();

        // Navigate to the transfers overview page.
        $this->clickMainNavigationLink($account_type, 'Transfers');
        $this->waitForElementPresence('#paymentResult');

        // Check if there are any pending transfers.
        $element = $this->session->getPage()->find('css', 'table#pendingpaymenttable');
        if (empty($element)) {
            $output->writeln("<comment>The $account_type account has no pending transfers.</comment>");
            return;
        }

        // Expand the table to show all transfers.
        $this->session->getPage()->find('css', 'a#Paging_ResultsForPage-button')->click();
        $this->waitForElementPresence('//ul[@id="Paging_ResultsForPage-menu"]/li/a[text()="All"]', 'xpath');
        $this->session->getPage()->find('xpath', '//ul[@id="Paging_ResultsForPage-menu"]/li/a[text()="All"]')->click();
        $this->waitForElementVisibility('//div[@id="paymentsResultPaging"]//div[contains(@class, "pagination")]/span[@class="current"]', 'xpath', FALSE);

        // Click the checkbox to select all transfers.
        $this->session->getPage()->checkField('pending_master_checkbox');

        // Click the 'Sign' button (for corporate accounts), or the 'Send'
        // button (for individual accounts).
        $button_id = $account_type === 'corporate' ? 'payments_sign' : 'payments_sign_send';
        $this->session->getPage()->clickLink($button_id);

        // Wait for the dialog box to appear.
        $this->waitForElementPresence('div#response_form');

        // Retrieve the challenge. Its containing elements are not identifiable
        // so we have to count elements.
        $element = $this->session->getPage()->find('xpath', '//div[@id="response_form"]/div[1]/div[2]');
        $challenge = trim($element->getText());

        // Ask the response in the console.
        $helper = $this->getHelper('question');
        $question = new Question('Challenge: ' . $challenge . '. Response: ');
        $question->setValidator([$this, 'numericValidator']);
        $response = trim($helper->ask($input, $output, $question));

        // Fill in the response in the form.
        $this->session->getPage()->fillField('Response', $response);
        $this->session->getPage()->pressButton('authorize_ok');
        $this->waitForElementPresence('div.infoSuccess');

        // Print results.
        $this->printMessages($output);

        // In corporate accounts the sending of the transactions is performed in
        // a separate step. This is not needed for personal accounts.
        if ($account_type === 'corporate') {
            // Navigate back to the overview.
            $this->navigateToHomepage();
            $this->clickMainNavigationLink($account_type, 'Transfers');
            $this->waitForElementPresence('#paymentResult');

            // Check all transfers and click on "Send".
            $this->session->getPage()->checkField('pending_master_checkbox');
            $this->session->getPage()->clickLink('payments_send');

            // Wait for the dialog box to appear.
            $this->waitForElementPresence('div#SignSendPreview');

            // Confirm.
            $this->session->getPage()->pressButton('ok');
            $this->waitForElementPresence('div.infoSuccess');

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
    protected function printMessages(OutputInterface $output) {
        $elements = $this->session->getPage()->findAll('css', 'div.infoSuccess p');
        foreach ($elements as $element) {
            $result = trim($element->getText());
            $output->writeln("<comment>$result</comment>");
        }
    }

}
