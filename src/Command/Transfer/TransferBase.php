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
 * Base class for transfers.
 */
abstract class TransferBase extends CommandBase
{
    /**
     * The global configuration object.
     *
     * @var \RaiffCli\Config\Config
     */
    protected $config;

    /**
     * The Mink session manager.
     *
     * @var \Behat\Mink\Mink
     */
    protected $mink;

    /**
     * The Mink session.
     *
     * @var \Behat\Mink\Session
     */
    protected $session;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
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
        $this->askTransactions($input, $output, $this->getRecipientNationality());
        $this->askConfirmation($input, $output, $input->getArgument('transactions'));
    }

    /**
     * Waits for the element with the given selector to appear.
     *
     * @param string $selector
     *   The CSS selector identifying the element.
     * @param string $engine
     *   The selector engine name, either 'css' or 'xpath'. Defaults to 'css'.
     *
     * @throws \Exception
     *   Thrown when the element doesn't appear within 10 seconds.
     */
    protected function waitUntilElementPresent($selector, $engine = 'css')
    {
        $timeout = 20000000;
        if ($engine === 'css') {
            $converter = new CssSelectorConverter();
            $selector = $converter->toXPath($selector);
        }

        do {
            $elements = $this->session->getDriver()->find($selector);
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
     * @param string $nationality
     *   Optionally limit the beneficiary accounts for the transactions by
     *   nationality. Supported values are 'bulgaria' and 'foreign'.
     */
    protected function askTransactions(InputInterface $input, OutputInterface $output, $nationality = '')
    {
        $transactions = [];

        $helper = $this->getHelper('question');
        while (true) {
            // Ask recipient, limited to Bulgarian accounts.
            $recipient = $this->askRecipient($input, $output, true, $nationality);

            // If the recipient is omitted, start the transactions.
            if (empty($recipient)) {
                if (empty($transactions)) {
                    // No transactions yet. Restart.
                    continue;
                }
                break;
            }

            // Ask amount.
            $currency = $nationality === 'bulgaria' ? 'BGN' : 'EUR';
            $question = new Question("Amount in $currency: ");
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
            $output->writeln("<info>Added transaction to {$recipient['name']} for $amount BGN: '$description'</info>");
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
    protected function askConfirmation(InputInterface $input, OutputInterface $output, array $transactions)
    {
        // Show a table of transactions:
        $output->writeln('Transactions:');
        $table = new Table($output);
        $table->setHeaders(['Recipient', 'Amount', 'Description']);
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

    /**
     * Initializes Mink.
     */
    protected function initMink()
    {
        $mink = new Mink();
        $config = $this->getConfigManager()->get('config');

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
        return $mink;
    }

    /**
     * @return Mink
     *   The Mink session manager.
     */
    protected function getMink()
    {
        if (empty($this->mink)) {
            $this->mink = $this->initMink();
        }
        return $this->mink;
    }

    /**
     * @return Session
     *   The Mink session.
     */
    protected function getSession()
    {
        return $this->getMink()->getSession();
    }

    /**
     * Visits the homepage.
     */
    protected function navigateToHomepage() {
        // Navigate to the homepage by clicking on the logo. We cannot visit the
        // URL directly because we would lose session information passed by
        // query arguments.
        $this->session->getPage()->find('css', '#head a.logo')->click();
    }

    /**
     * Logs in.
     */
    protected function logIn()
    {
        $config = $this->getConfigManager()->get('config');
        $base_url = $config->get('base_url');
        $this->session->visit($base_url);
        $this->session->getPage()->fillField('userName', $config->get('credentials.username'));
        $this->session->getPage()->fillField('pwd', $config->get('credentials.password'));
        $this->session->getPage()->find('css', '#m_ctrl_Page button.primary')->click();
        $this->waitUntilElementPresent('#main .themebox.ind');
    }

    /**
     * Selects the account type.
     *
     * @param string $account_type
     *   The account type, either 'individual' or 'corporate'.
     */
    protected function selectAccountType($account_type) {
        $selector = $account_type === 'individual' ? '#main .themebox.ind a.btn.secondary' : '#main .themebox.corp a.btn.secondary';
        $this->session->getPage()->find('css', $selector)->click();
        $this->waitUntilElementPresent('#head a.logo');
    }

    /**
     * Chooses the "Sender" account.
     *
     * @param string $account
     *   The account name, in the format '1234567890 BGN'.
     */
    protected function chooseAccount($account) {
        $this->waitUntilElementPresent('#showPayerPicker');
        $this->session->getPage()->findById('showPayerPicker')->click();
        $this->waitUntilElementPresent('#accounts');
        $this->session->getPage()->find('xpath', '//*[@id="accounts"]/table//tr/td[text()[contains(., "' . $account . '")]]')->click();
    }

    /**
     * Sets the nationality of the recipients for the current transaction type.
     *
     * @return string
     *   Can be either 'bulgaria' or 'foreign'. Leave empty to allow recipients
     *   of all nationalities.
     */
    protected function getRecipientNationality() {
        return '';
    }

}
