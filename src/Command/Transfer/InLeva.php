<?php

namespace RaiffCli\Command\Transfer;

use Behat\Mink\Driver\Selenium2Driver;
use Behat\Mink\Mink;
use Behat\Mink\Session;
use RaiffCli\Command\CommandBase;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\CssSelector\CssSelectorConverter;
use Zumba\Mink\Driver\PhantomJSDriver;

/**
 * Console command to execute a transaction in leva.
 */
class InLeva extends CommandBase
{

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('transfer:in-leva')
            ->setDescription('Do a bank transfer in leva.')
            ->addAccountTypeArgument()
            ->addAccountArgument()
            ->addArgument('transactions', InputArgument::REQUIRED, 'The transactions');
    }

    /**
     * {@inheritdoc}
     */
    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $this->askAccountType($input, $output);
        $account_type = $input->getArgument('account-type');
        $output->writeln("<info>Selected account type: $account_type</info>");

        $this->askAccount($input, $output, $account_type);
        $this->askTransactions($input, $output);
        $this->askConfirmation($input, $output, $input->getArgument('transactions'));
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $account_type = $input->getArgument('account-type');
        $account = $input->getArgument('account');
        $transactions = $input->getArgument('transactions');

        $config = $this->getConfigManager()->get('config');
        $base_url = $config->get('base_url');

        $mink = new Mink();

        // Register PhantomJS driver.
        $host = $config->get('mink.sessions.phantomjs.host');
        $template_cache = $config->get('mink.sessions.phantomjs.template_cache');
        if (!file_exists($template_cache)) mkdir($template_cache);
        $mink->registerSession('phantomjs', new Session(new PhantomJSDriver($host, $template_cache)));

        // Register Selenium driver.
        $browser = $config->get('mink.sessions.selenium2.browser');
        $host = $config->get('mink.sessions.selenium2.host');
        $mink->registerSession('selenium2', new Session(new Selenium2Driver($browser, NULL, $host)));

        $mink->setDefaultSessionName($config->get('mink.default_session'));
        $session = $mink->getSession();

        // Visit the homepage.
        $session->visit($base_url);

        // Log in.
        $session->getPage()->fillField('userName', $config->get('credentials.username'));
        $session->getPage()->fillField('pwd', $config->get('credentials.password'));
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
        }
    }

    protected function waitUntilElementPresent(Session $session, $selector) {
        $timeout = 10000000;
        $converter = new CssSelectorConverter();

        do {
            $elements = $session->getDriver()->find($converter->toXPath($selector));
            if (!empty($elements)) return;
            usleep(500000);
            $timeout -= 500000;
        } while ($timeout > 0);

        throw new \Exception("The element with selector '$selector' is not present on the page.");
    }

    /**
     * Asks the user for transactions.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     *   The input interface.
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *   The output interface.
     */
    protected function askTransactions(InputInterface $input, OutputInterface $output) {
        $transactions = [];

        $helper = $this->getHelper('question');
        while (true) {
            // Ask recipient, limited to Bulgarian accounts.
            $recipient = $this->askRecipient($input, $output, true, 'bulgaria');

            // If the recipient is omitted, start the transactions.
            if (empty($recipient)) {
                if (empty($transactions)) {
                    // No transactions yet. Restart.
                    continue;
                }
                break;
            }

            // Ask amount in BGN.
            $question = new Question('Amount in BGN: ');
            $question->setValidator(function ($input) {
                if (!empty($input) && preg_match("/^[0-9]+\.[0-9]{2}$/", $input) !== 1) {
                    throw new \InvalidArgumentException('The amount must be in the format "123.45".');
                }
                return $input;
            });
            $amount = $helper->ask($input, $output, $question);

            // Allow the user to start over by entering an empty amount.
            if (empty($amount) || $amount == 0) {
                $output->writeln('<comment>Skipping transaction with empty amount.</comment>');
                continue;
            }

            // Ask for the description.
            $question = new Question('Description: ');
            $description = $helper->ask($input, $output, $question);

            // Allow the user to start over by entering an empty description.
            if (empty($description)) {
                $output->writeln('<comment>Skipping transaction with empty description.</comment>');
                continue;
            }

            $transactions[] = [
                'recipient' => $recipient,
                'amount' => $amount,
                'description' => $description,
            ];
            $output->writeln("<info>Added transaction to ${recipient['name']} for $amount BGN: '$description'</info>");
        }

        $input->setArgument('transactions', $transactions);
    }

    /**
     * Asks the user for confirmation before executing the transactions.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     *   The input interface.
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *   The output interface.
     * @param array $transactions
     *   The transactions ready to be executed.
     *
     * @throws \Exception
     *   Thrown when confirmation is not given.
     */
    protected function askConfirmation(InputInterface $input, OutputInterface $output, array $transactions) {
        // Show a table of transactions:
        $output->writeln('Transactions:');
        $table = new Table($output);
        $table->setHeaders(['Name', 'Amount', 'Description']);
        $table->setStyle('compact');

        foreach ($transactions as $transaction) {
            $table->addRow([
                $transaction['recipient']['name'],
                $transaction['amount'],
                $transaction['description'],
            ]);
        }
        $table->render();

        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion('Are you sure you want to execute these transactions (Y/n)? ', true);
        $confirmation = $helper->ask($input, $output, $question);

        if (!$confirmation) {
            throw new \Exception('Transfer aborted.');
        }
    }
}
