<?php

namespace RaiffCli\Command\Transfer;

use RaiffCli\Command\CommandBase;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

/**
 * Base class for transfers.
 */
abstract class TransferBase extends CommandBase
{

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
        // Retrieve any transactions that were entered during a previous session
        // but were not successfully executed.
        $transactions = $this->getStoredTransactions($input->getArgument('account-type'));

        // If there are any transactions remaining from a previous session,
        // ask if the user wants to include them.
        if (!empty($transactions)) {
            $output->writeln("<info>Transactions from the previous session are present:</info>");
            $this->outputTransactionTable($output, $transactions);

            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion('Do you want to import these transactions (Y/n)? ', true);
            $confirmation = $helper->ask($input, $output, $question);

            if (!$confirmation) {
                $transactions = [];
            }
        }

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
            $output->writeln("<info>Added transaction to {$recipient['name']} for $amount $currency: '$description'</info>");
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
        // Show a table of transactions.
        $this->outputTransactionTable($output, $transactions);

        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion('Are you sure you want to register these transactions (Y/n)? ', true);
        $confirmation = $helper->ask($input, $output, $question);

        if (!$confirmation) {
            throw new \Exception('Transfer aborted.');
        }

        // Store the transactions to disk so they can be reused if they cannot
        // be executed.
        $this->storeTransactions($transactions, $input->getArgument('account-type'));
    }

    /**
     * Outputs a table containing the given transactions to the console.
     *
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *   The console output handler.
     * @param array $transactions
     *   The array of transactions to output.
     */
    protected function outputTransactionTable(OutputInterface $output, array $transactions)
    {
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
    }

    /**
     * Returns transactions that were stored during a previous session.
     *
     * @param string $account_type
     *   The account type for which to return the transactions.
     *
     * @return array
     *   The transactions.
     */
    protected function getStoredTransactions($account_type)
    {
        $config = $this->getConfigManager()->get('transactions');
        $transactions = $config->get($this->getName(), []);
        return !empty($transactions[$account_type]) ? $transactions[$account_type] : [];
    }

    /**
     * Stores the given transactions to disk.
     *
     * @param array $transactions
     *   The transactions to store.
     * @param string $account_type
     *   The account type for which this transaction has been entered.
     */
    protected function storeTransactions(array $transactions, $account_type)
    {
        $config = $this->getConfigManager()->get('transactions');
        $stored_transactions = $config->get($this->getName());
        $stored_transactions[$account_type] = $transactions;
        $config->set($this->getName(), $stored_transactions)->save();
    }

    /**
     * Deletes the given transaction from the disk cache.
     *
     * @param array $transaction
     *   The transaction to delete.
     */
    protected function deleteStoredTransaction(array $transaction)
    {
        $config = $this->getConfigManager()->get('transactions');
        $data = $config->get($this->getName(), []);
        foreach ($data as $account_type => $transactions) {
            foreach ($transactions as $key => $stored_transaction) {
                if ($stored_transaction === $transaction) {
                    unset($data[$account_type][$key]);
                    break;
                }
            }
        }
        $config->set($this->getName(), $data)->save();
    }

    /**
     * Chooses the "Sender" account.
     *
     * @param string $account
     *   The account name, in the format '1234567890 BGN'.
     */
    protected function chooseAccount($account)
    {
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
    protected function getRecipientNationality()
    {
        return '';
    }

}
