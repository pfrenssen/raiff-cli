<?php

namespace RaiffCli\Command;

use Behat\Mink\Driver\Selenium2Driver;
use Behat\Mink\Mink;
use Behat\Mink\Session;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\CssSelector\CssSelectorConverter;
use Zumba\Mink\Driver\PhantomJSDriver;

/**
 * Base class for commands.
 */
abstract class CommandBase extends Command
{

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
     * Returns the dependency injection container helper.
     *
     * @return \RaiffCli\Helper\ContainerHelper
     *   The dependency injection container helper.
     */
    protected function getContainer()
    {
        return $this->getHelper('container');
    }

    /**
     * Returns the configuration manager.
     *
     * @return \RaiffCli\Config\ConfigManager
     *   The configuration manager.
     */
    protected function getConfigManager()
    {
        return $this->getContainer()->get('config.manager');
    }

    /**
     * Returns the Mink session manager.
     *
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
     * Returns the Mink session.
     *
     * @return Session
     *   The Mink session.
     */
    protected function getSession()
    {
        return $this->getMink()->getSession();
    }

    /**
     * Initializes Mink.
     *
     * @return Mink
     *   The initialized Mink session manager.
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
     * Adds an argument to the command for the account type.
     *
     * @return $this
     */
    protected function addAccountTypeArgument()
    {
        $this->addArgument('account-type', InputArgument::REQUIRED, 'The account type to use: either "individual" or "corporate"');

        return $this;
    }

    /**
     * Asks the user for the account type.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     *   The input interface.
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *   The output interface.
     */
    protected function askAccountType(InputInterface $input, OutputInterface $output)
    {
        $account = $input->getArgument('account-type');
        if (empty($account) || !in_array($account, ['individual', 'corporate'])) {
            $helper = $this->getHelper('question');
            $question = new ChoiceQuestion('Please select the account type (default: individual):', array('individual', 'corporate'), 0);
            $question->setErrorMessage('Account type %s is invalid.');
            $input->setArgument('account-type', $helper->ask($input, $output, $question));
        }
    }

    /**
     * Adds an argument to the command for the account.
     *
     * @return $this
     */
    protected function addAccountArgument()
    {
        $this->addArgument('account', InputArgument::REQUIRED, 'The account to use');

        return $this;
    }

    /**
     * Asks the user for the account.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     *   The input interface.
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *   The output interface.
     * @param string $type
     *   The account type, either 'individual' or 'corporate'.
     *
     * @throws \Exception
     *   Thrown when there are multiple accounts for one account type. This is
     *   not implemented yet.
     * @throws \LogicException
     *   Thrown when no accounts have been configured for the given account
     *   type.
     */
    protected function askAccount(InputInterface $input, OutputInterface $output, $type)
    {
        $config = $this->getConfigManager()->get('accounts');
        $accounts = $config->get($type, []);

        if (empty($accounts)) {
            throw new \LogicException("There are no $type accounts. Please add one using the 'account:add' command.");
        }

        // If there is only one account, set it without asking questions.
        if (count($accounts) == 1) {
            $account = reset($accounts);
            $input->setArgument('account', $account);
        } else {
            // @todo Ask which account to use if there are multiple accounts.
            throw new \Exception('Support for multiple accounts is not implemented yet.');
        }
    }

    /**
     * Asks for a recipient with an autocomplete field.
     *
     * @param \Symfony\Component\Console\Input\InputInterface $input
     *   The input interface.
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *   The output interface.
     * @param bool $allow_empty
     *   Whether or not an empty result is acceptable.
     * @param string $type
     *   The recipient type to return, either 'bulgaria' or 'foreign'. Leave
     *   empty to choose between all recipients.
     *
     * @return array
     *   The recipient array.
     */
    protected function askRecipient(InputInterface $input, OutputInterface $output, $allow_empty = false, $type = '')
    {
        // Retrieve the recipients.
        $config = $this->getConfigManager()->get('recipients');
        $recipients = $config->get('recipients', []);

        // Filter recipients by type.
        switch ($type) {
            case 'bulgaria':
                $recipients = array_filter($recipients, function ($recipient) {
                    return substr($recipient['iban'], 0, 2) === 'BG';
                });
                break;
            case 'foreign':
                $recipients = array_filter($recipients, function ($recipient) {
                    return substr($recipient['iban'], 0, 2) !== 'BG';
                });
                break;
            default:
                // Do not filter.
                break;
        }

        // Add an empty option.
        $default = null;
        if ($allow_empty) {
            $default = '- skip -';
            $recipients[]['alias'] = $default;
        }

        $helper = $this->getHelper('question');
        $question = new ChoiceQuestion('Recipient:', array_column($recipients, 'alias'), $default);
        $question->setErrorMessage('Recipient %s does not exist.');

        $recipient = $helper->ask($input, $output, $question);

        if ($recipient === $default) {
            return [];
        }

        $recipients = array_combine(array_column($recipients, 'alias'), $recipients);
        return $recipients[$recipient];
    }

    /**
     * Validator for required arguments.
     *
     * @param string $input
     *   The input from the user.
     *
     * @return string
     *   The input from the user.
     *
     * @throws \InvalidArgumentException
     *   Thrown when the input is empty.
     */
    public static function requiredValidator($input)
    {
        if (empty(trim($input))) {
            throw new \InvalidArgumentException('Please enter a value.');
        }
        return $input;
    }

    /**
     * Validator for numeric arguments.
     *
     * @param string $input
     *   The input from the user.
     *
     * @return string
     *   The input from the user.
     *
     * @throws \InvalidArgumentException
     *   Thrown when the input is not numeric.
     */
    public static function numericValidator($input)
    {
        if (empty(trim($input)) || !is_numeric(trim($input))) {
            throw new \InvalidArgumentException('Please enter a numeric value.');
        }
        return $input;
    }

    /**
     * Waits for the given element to appear or disappear from the DOM.
     *
     * @param string $selector
     *   The CSS selector identifying the element.
     * @param string $engine
     *   The selector engine name, either 'css' or 'xpath'. Defaults to 'css'.
     * @param bool $present
     *   TRUE to wait for the element to appear, FALSE for it to disappear.
     *   Defaults to TRUE.
     *
     * @throws \Exception
     *   Thrown when the element doesn't appear or disappear within 20 seconds.
     */
    protected function waitForElementPresence($selector, $engine = 'css', $present = TRUE)
    {
        $timeout = 20000000;
        if ($engine === 'css') {
            $converter = new CssSelectorConverter();
            $selector = $converter->toXPath($selector);
        }

        do {
            $element = $this->session->getDriver()->find($selector);
            if (!empty($element) === $present) return;
            usleep(500000);
            $timeout -= 500000;
        } while ($timeout > 0);

        throw new \Exception("The element with selector '$selector' is " . ($present ? 'not ' : '') . 'present on the page.');
    }

    /**
     * Waits for the given element to become (in)visible.
     *
     * @param string $selector
     *   The CSS selector identifying the element.
     * @param string $engine
     *   The selector engine name, either 'css' or 'xpath'. Defaults to 'css'.
     * @param bool $visible
     *   TRUE to wait for the element to become visible, FALSE for it to become
     *   invisible. Defaults to TRUE.
     *
     * @throws \Exception
     *   Thrown when the element doesn't become (in)visible within 20 seconds.
     */
    protected function waitForElementVisibility($selector, $engine = 'css', $visible = TRUE)
    {
        $timeout = 20000000;
        if ($engine === 'css') {
            $converter = new CssSelectorConverter();
            $selector = $converter->toXPath($selector);
        }

        do {
            $element = $this->mink->assertSession()->elementExists($engine, $selector);
            if ($element->isVisible() === $visible) return;
            usleep(500000);
            $timeout -= 500000;
        } while ($timeout > 0);

        throw new \Exception("The element with selector '$selector' is " . ($visible ? 'not ' : '') . 'visible on the page.');
    }

    /**
     * Visits the homepage.
     */
    protected function navigateToHomepage()
    {
        // Navigate to the homepage by clicking on the logo. We cannot visit the
        // URL directly because we would lose session information passed by
        // query arguments.
        $this->session->getPage()->find('css', '#head a.logo')->click();
    }

    /**
     * Clicks the link with the given link text in the main navigation menu.
     *
     * @param string $account_type
     *   The account type, either 'individual' or 'corporate'.
     * @param string $link_text
     *   The link text to click.
     */
    protected function clickMainNavigationLink($account_type, $link_text)
    {
        $this->session->getPage()->find('xpath', '//table[@class="' . $account_type . '"]//a[text()="' . $link_text . '"]')->click();
    }

    /**
     * Logs in.
     */
    protected function logIn()
    {
        $config = $this->getConfigManager()->get('config');
        $base_url = $config->get('base_url');
        $this->session->visit($base_url);
        $this->closeSecurityWarning();
        $this->session->getPage()->fillField('userName', $config->get('credentials.username'));
        $this->session->getPage()->fillField('pwd', $config->get('credentials.password'));
        $this->session->getPage()->find('css', '#m_ctrl_Page button.primary')->click();
        $this->waitForElementPresence('#main .themebox.ind');
    }

    /**
     * Selects the account type.
     *
     * @param string $account_type
     *   The account type, either 'individual' or 'corporate'.
     */
    protected function selectAccountType($account_type)
    {
        $selector = $account_type === 'individual' ? '#main .themebox.ind a.btn.secondary' : '#main .themebox.corp a.btn.secondary';
        $this->session->getPage()->find('css', $selector)->click();
        $this->waitForElementPresence('#head a.logo');
        // Close the marketing banner if it is present.
        $this->closeCampaignContent();
    }

    /**
     * Closes the marketing campaign dialog if it is present.
     */
    protected function closeCampaignContent()
    {
        $this->closeDialog('CampaignContent');
    }

    /**
     * Closes the security warning if it is present.
     */
    protected function closeSecurityWarning()
    {
        $this->closeDialog('ui-dialog-title-lbSecutiryWarnings');
    }

    /**
     * Closes the dialog with the given CSS ID if it is present.
     *
     * @param string $id
     *   The CSS ID of the dialog.
     */
    protected function closeDialog($id)
    {
        $page = $this->session->getPage();
        if ($page->find('css', '#' . $id)) {
            $page->find('css', 'a.ui-dialog-titlebar-close')->click();
        }
    }

}
