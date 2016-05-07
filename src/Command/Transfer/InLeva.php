<?php

namespace RaiffCli\Command\Transfer;

use Behat\Mink\Driver\Selenium2Driver;
use Behat\Mink\Mink;
use Behat\Mink\Session;
use RaiffCli\Command\CommandBase;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\CssSelector\CssSelectorConverter;
use Zumba\Mink\Driver\PhantomJSDriver;

class InLeva extends CommandBase
{

    protected function configure()
    {
        $this
            ->setName('transfer:in-leva')
            ->setDescription('Do a bank transfer in leva.');
        $this->addAccountTypeArgument();
        $this->addAccountArgument();
        $this->addArgument('transactions', InputArgument::REQUIRED, 'The transactions');
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $this->askAccountType($input, $output);
        $this->askAccount($input, $output, $input->getArgument('account-type'));
        $this->askTransactions($input, $output);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $config = $this->getConfigManager()->get('config');
        $account = $input->getArgument('account');
        $output->writeln('Chosen account: ' . $account);
        $output->writeln('Your username: ' . $config->get('credentials.username'));

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
        $selector = $account === 'individual' ? '#main .themebox.ind a.btn.secondary' : '#main .themebox.corp a.btn.secondary';
        $session->getPage()->find('css', $selector)->click();
        sleep(5);
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
    protected function askTransactions (InputInterface $input, OutputInterface $output) {
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
        }

        $input->setArgument('transactions', $transactions);
    }

}
