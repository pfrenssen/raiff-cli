<?php

declare(strict_types = 1);

namespace RaiffCli\Command;

use Behat\Mink\Driver\Selenium2Driver;
use Behat\Mink\Element\NodeElement;
use Behat\Mink\Exception\DriverException;
use Behat\Mink\Mink;
use Behat\Mink\Session;
use RaiffCli\Config\ConfigManager;
use RaiffCli\Exception\ElementPresenceException;
use RaiffCli\Exception\ElementViewModelException;
use RaiffCli\Exception\ElementVisibilityException;
use RaiffCli\Helper\ContainerHelper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\CssSelector\CssSelectorConverter;
use WebDriver\Exception\ElementNotVisible;
use WebDriver\Exception\NoSuchElement;
use WebDriver\Exception\StaleElementReference;
use WebDriver\Exception\UnknownError;
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
    protected function getContainer() : ContainerHelper
    {
        return $this->getHelper('container');
    }

    /**
     * Returns the configuration manager.
     *
     * @return \RaiffCli\Config\ConfigManager
     *   The configuration manager.
     */
    protected function getConfigManager() : ConfigManager
    {
        return $this->getContainer()->get('config.manager');
    }

    /**
     * Returns the Mink session manager.
     *
     * @return \Behat\Mink\Mink
     *   The Mink session manager.
     */
    protected function getMink() : Mink
    {
        if (empty($this->mink)) {
            $this->mink = $this->initMink();
        }
        return $this->mink;
    }

    /**
     * Returns the Mink session.
     *
     * @return \Behat\Mink\Session
     *   The Mink session.
     */
    protected function getSession() : Session
    {
        return $this->getMink()->getSession();
    }

    /**
     * Initializes Mink.
     *
     * @return \Behat\Mink\Mink
     *   The initialized Mink session manager.
     */
    protected function initMink() : Mink
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
    protected function addAccountTypeArgument() : CommandBase
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
    protected function askAccountType(InputInterface $input, OutputInterface $output) : void
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
    protected function addAccountArgument() : CommandBase
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
    protected function askAccount(InputInterface $input, OutputInterface $output, $type) : void
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
    protected function askRecipient(InputInterface $input, OutputInterface $output, bool $allow_empty = false, string $type = '') : array
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
    public static function requiredValidator(string $input = NULL) : string
    {
        if (empty($input) || empty(trim($input))) {
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
    public static function numericValidator(string $input) : string
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
     * @param int $timeout
     *   The time to wait for the element to appear or disappear, in microseconds. Defaults to 20 seconds.
     *
     * @throws ElementPresenceException
     *   Thrown when the element doesn't appear or disappear within the given timeout.
     */
    protected function waitForElementPresence(string $selector, string $engine = 'css', bool $present = TRUE, int $timeout = 20000000) : void
    {
        if ($engine === 'css') {
            $converter = new CssSelectorConverter();
            $selector = $converter->toXPath($selector);
        }

        do {
            try {
                $element = $this->session->getDriver()->find($selector);
            }
            catch (DriverException $e) {
                throw new \RuntimeException("An error occurred while attempting to retrieve the element with selector $selector", 0, $e);
            }
            if (!empty($element) === $present) return;
            usleep(500000);
            $timeout -= 500000;
        } while ($timeout > 0);

        throw new ElementPresenceException("The element with selector '$selector' is " . ($present ? 'not ' : '') . 'present on the page.');
    }

    /**
     * Waits for the given element to become (in)visible.
     *
     * @param string $selector
     *   The selector identifying the element.
     * @param string $engine
     *   The selector engine name, either 'css' or 'xpath'. Defaults to 'css'.
     * @param bool $visible
     *   TRUE to wait for the element to become visible, FALSE for it to become
     *   invisible. Defaults to TRUE.
     * @param int $timeout
     *   The time to wait for the element to become (in)visible, in microseconds. Defaults to 20 seconds.
     *
     * @throws ElementVisibilityException
     *   Thrown when the element doesn't become (in)visible within the given timeout.
     */
    protected function waitForElementVisibility(string $selector, string $engine = 'css', bool $visible = TRUE, int $timeout = 20000000) : void
    {
        do {
            $elements = $this->session->getPage()->findAll($engine, $selector);
            // If the element does not exist and we are waiting for it to
            // disappear we are done.
            if (empty($elements)) {
                if (!$visible) return;
            }
            // It might happen that duplicates of the element exist, for example
            // in mobile versions, or sticky footers. Loop over all elements
            // that are found and report if any of them are visible.
            elseif (array_reduce($elements, function (bool $carry, NodeElement $element) : bool {
                try {
                    $visible = $element->isVisible();
                } catch (StaleElementReference $e) {
                    // The element might have disappeared from the DOM.
                    $visible = FALSE;
                } catch (NoSuchElement $e) {
                    // The element might have disappeared from the DOM.
                    $visible = FALSE;
                }
                return $carry || $visible;
            }, FALSE) === $visible) return;
            usleep(500000);
            $timeout -= 500000;
        } while ($timeout > 0);

        throw new ElementVisibilityException("The element with selector '$selector' is " . ($visible ? 'not ' : '') . 'visible on the page.');
    }

    /**
     * Waits for the link button with the given text to appear or disappear.
     *
     * @param string $link_text
     *   The link text.
     * @param bool $present
     *   TRUE to wait for the element to appear, FALSE for it to disappear.
     *   Defaults to TRUE.
     * @param int $timeout
     *   The time to wait for the element to become present, in microseconds. Defaults to 20 seconds.
     *
     * @throws ElementPresenceException
     *   Thrown when the link button doesn't appear or disappear within the given timeout.
     */
    protected function waitForLinkButtonPresence(string $link_text, bool $present = TRUE, int $timeout = 20000000) : void
    {
        $this->waitForElementPresence('//button[contains(concat(" ", normalize-space(@class), " "), " btn-primary ") and .//span[normalize-space(text()) = "' . $link_text . '"]]', 'xpath', $present, $timeout);
    }

    /**
     * Waits for the link button with the given text to become (in)visible.
     *
     * @param string $link_text
     *   The link text.
     * @param bool $visible
     *   TRUE to wait for the element to become visible, FALSE for it to become
     *   invisible. Defaults to TRUE.
     *
     * @throws \Exception
     *   Thrown when the link button doesn't become (in)visible within 20
     *   seconds.
     */
    protected function waitForLinkButtonVisibility(string $link_text, bool $visible = TRUE) : void
    {
        $this->waitForElementVisibility('//button[contains(concat(" ", normalize-space(@class), " "), " btn-primary ") and .//span[normalize-space(text()) = "' . $link_text . '"]]', 'xpath', $visible);
    }

    /**
     * Waits until the "overlay-loading" element disappears.
     */
    protected function waitUntilPageLoaded() : void
    {
        $this->waitForElementVisibility('.overlay-loading', 'css', FALSE);
    }

    /**
     * Waits until the container for success messages appears.
     */
    protected function waitForSuccessMessage() : void
    {
        $this->waitForElementPresence('.status-container .text-success');
    }

    /**
     * Waits for the given element to receive the given Knockout view model.
     *
     * @param string $selector
     *   The selector identifying the element.
     * @param string $engine
     *   The selector engine name, either 'css' or 'xpath'. Defaults to 'css'.
     * @param string $view_model
     *   The view model that is expected to be bound to the element.
     * @param int $timeout
     *   The time to wait for the element to appear or disappear, in microseconds. Defaults to 20 seconds.
     *
     * @throws ElementViewModelException
     *   Thrown when the element doesn't receive the expected view model.
     */
    protected function waitForElementViewModel(string $selector, string $engine, string $view_model, int $timeout = 20000000): void
    {
        $xpath = $this->getXPathFromSelector($selector, $engine);

        $condition = <<<JS
            (function() {
                function getElementByXPath(xpath) {
                    return document.evaluate(xpath, document, null, XPathResult.FIRST_ORDERED_NODE_TYPE, null).singleNodeValue;
                }

                // Bail out if Knockout isn't loaded yet by RequireJS.
                if (!require.s.contexts._.defined.knockout) {
                    return false;
                }

                // Bail out if the element doesn't exist or has no view model bound to it.
                element = getElementByXPath('$xpath');
                if (!element || !(data = require('knockout').dataFor(element))) {
                    return false;
                }

                return data.constructor.name === '$view_model';
            }());
JS;

        $result = $this->session->wait($timeout, $condition);

        if (!$result) {
            throw new ElementViewModelException("The element with selector '$selector' does not have the '$view_model' view model.");
        }
    }

    /**
     * Returns the XPath selector for the element that matches the given selector and engine.
     *
     * This is intended to get an XPath selector, regardless whether the XPath or CSS engines are used.
     * Note that this depends on the element that is being selected already being present in the page.
     *
     * @param string $selector
     *   The selector identifying the element.
     * @param string $engine
     *   The selector engine name, either 'css' or 'xpath'.
     *
     * @return string
     *   The XPath selector that identifies the element.
     */
    protected function getXPathFromSelector(string $selector, string $engine): string
    {
        if ($engine === 'xpath') {
            return $selector;
        }
        $element = $this->session->getPage()->find($engine, $selector);
        return $element->getXpath();
    }

    /**
     * Visits the homepage.
     */
    protected function navigateToHomepage() : void
    {
        // Navigate to the homepage by clicking on the icon in the main menu. We
        // cannot navigate to the URL directly because we would lose session
        // information passed in the URL.
        $this->clickMainNavigationLink('home');
    }

    /**
     * Clicks the link with the given link text in the main navigation menu.
     *
     * @param string $link_text
     *   The link text to click.
     *
     * @throws \Exception
     *   Thrown when an unknown link text is being passed.
     */
    protected function clickMainNavigationLink(string $link_text) : void
    {
        $link_text = $this->capitalizeMainNavigationLinkText($link_text);
        // A marketing banner might appear at any time. Retry a couple of times if this happens.
        $attempts = 0;
        do {
            try {
                $this->session->getPage()->find('xpath', '//nav[contains(@class, "nav-main") and not(contains(@class, "nav-mobile"))]//a[span[@title = "' . $link_text . '"]]')->click();
                return;
            }
            catch (UnknownError $e) {
                // This exception is thrown when an element cannot be clicked because it is obscured by another element
                // (such as a dialog).
                $this->closeDialog();
            }
        }
        while (++$attempts < 3);
        throw new \RuntimeException('Unable to click main navigation link after 3 attempts.', 0, $e);
    }

    /**
     * Clicks the link with given link text in the secondary navigation menu.
     *
     * @param string $link_text
     *   The link text to click.
     *
     * @throws ElementPresenceException
     *   Thrown when the link is not present on the page.
     */
    protected function clickSecondaryNavigationLink(string $link_text) : void
    {
        $locator = '//ul[contains(concat(" ", normalize-space(@class), " "), " nav-tabs ")]//a[span[@title = "' . $link_text . '"]]';
        // A marketing banner might appear at any time. Retry a couple of times if this happens.
        $attempts = 0;
        do {
            try {
                $element = $this->session->getPage()->find('xpath', $locator);

                if (empty($element)) {
                    throw new ElementPresenceException("The secondary navigation link with text '$link_text' is not present on the page.");
                }

                $element->click();
                return;
            }
            catch (UnknownError $e) {
                // This exception is thrown when an element cannot be clicked because it is obscured by another element
                // (such as a dialog).
                $this->closeDialog();
            }
        }
        while (++$attempts < 3);

        throw new \RuntimeException("Could not click the secondary navigation link with text '$link_text' after three attempts.");
    }

    /**
     * Clicks a "button link" that contains the given link text.
     *
     * These buttons can be identified by having the 'btn-primary' class, and
     * the link text is contained in a set of spans.
     *
     * @param string $link_text
     *   The link text.
     *
     * @throws \Exception
     *   Thrown when a link button with the given text is not present or not
     *   visible.
     */
    protected function clickLinkButton(string $link_text) : void
    {
        $this->waitForLinkButtonVisibility($link_text);

        // The contents of the page might change at any time, causing elements
        // to become stale. If this happens, restart the process with freshly
        // fetched elements.
        $attempts = 0;
        do {
            try {
                // It might happen that duplicates of the button exist, for
                // example in mobile versions, or sticky footers. Loop over all
                // elements that are found and click on the first one that is
                // visible.
                $elements = $this->session->getPage()->findAll('xpath', '//button[contains(concat(" ", normalize-space(@class), " "), " btn-primary ") and .//span[normalize-space(text()) = "' . $link_text . '"]]');
                foreach ($elements as $element) {
                    /** @var \Behat\Mink\Element\NodeElement $element */
                    if ($element->isVisible()) {
                        $element->click();
                        return;
                    }
                }
            } catch (NoSuchElement $e) {
                // The element we're interacting with has become stale. This
                // means the page has changed in the meantime. Restart the
                // process with fresh elements.
            }
        }
        while (++$attempts < 3);

        throw new \Exception('Link button with text "' . $link_text . '" not found, or not visible.');
    }

    /**
     * Selects the option with the given element text in the given select box.
     *
     * Some of the select boxes in the Raiffeisen website have option elements
     * with empty 'value' properties.
     * According to the HTML5 specification the element text should be used as
     * the value if the 'value' property is not present. The website violates
     * the standard by having a 'value' property present and not setting the
     * value.
     *
     * @see https://w3c.github.io/html/sec-forms.html#the-option-element
     *
     * This method works around this violation by finding the option to be
     * selected using the element text and forcing a click on it.
     *
     * @param string $select_id
     *   The ID of the select box.
     * @param string $element_text
     *   The element text of the option that should be selected.
     * @param bool $strict_id
     *   Whether or not to do a strict check for the element ID. Set to FALSE
     *   to allow a partial ID match.
     */
    protected function selectOptionByElementText(string $select_id, string $element_text, bool $strict_id = TRUE): void
    {
        if ($strict_id) {
            $select_element = $this->session->getPage()->findById($select_id);
        }
        else {
            $select_element = $this->session->getPage()->find('xpath', '//*[contains(@id, "' . $select_id . '")]');
        }
        $option_element = $select_element->find('named', array('option', $element_text));
        if (!$option_element->isSelected()) {
            $option_element->click();
        }
    }

    /**
     * Return the main navigation link in the correct capitalization.
     *
     * The navigation link text is inconsistently capitalized.
     *
     * @param string $link_text
     *   The case-insensitive link text.
     *
     * @return string
     *   The link text using the correct capitalization.
     *
     * @throws \Exception
     *   Thrown when an unknown link text is being passed.
     */
    protected function capitalizeMainNavigationLinkText(string $link_text) : string
    {
        $link_texts = [
            'home' => 'home',
            'transfers' => 'Transfers',
            'accounts' => 'accounts',
            'cards' => 'cards',
            'loans' => 'loans',
            'deposits' => 'deposits',
            'investments' => 'investments',
            'offers' => 'offers',
            'forms' => 'forms',
            'financing' => 'financing',
        ];
        $key = strtolower($link_text);
        if (!array_key_exists($key, $link_texts)) {
            throw new \Exception("Unknown link text '$link_text'.");
        }
        return $link_texts[$key];
    }

    /**
     * Logs in.
     */
    protected function logIn() : void
    {
        $config = $this->getConfigManager()->get('config');
        $base_url = $config->get('base_url');
        $this->session->visit($base_url);
        // A dialog containing information or a security warning might suddenly pop up during the login procedure,
        // obscuring the form fields. If this happens, try to log in again.
        $attempts = 0;
        do {
            try {
                $this->waitForElementPresence('//input[contains(@data-bind, "Model.UserName") and contains(@id, "Model_UserName")]', 'xpath');
                $this->session->getPage()->fillField('User name', $config->get('credentials.username'));
                $this->session->getPage()->fillField('Password', $config->get('credentials.password'));
                $this->session->getPage()->find('css', '.btn-login')->click();
                $this->waitForElementPresence('.profile-selection');
                return;
            }
            catch (UnknownError $e) {
                // This exception is thrown when an element cannot be clicked because it is obscured by a dialog.
                $this->closeDialog();
            }
        }
        while (++$attempts < 3);
        throw new \RuntimeException('Unable to log in after 3 attempts.');
    }

    /**
     * Selects the account type.
     *
     * @param string $account_type
     *   The account type, either 'individual' or 'corporate'.
     *
     * @throws \Exception
     */
    protected function selectAccountType(string $account_type) : void
    {
        $selector = '//button[contains(@data-bind, "' . $account_type . '")]';

        // It is possible that the buttons are already present but their click handlers are not bound yet.
        $this->waitForElementViewModel($selector, 'xpath', 'ChooseThemeViewModel');

        $this->session->getPage()->find('xpath', $selector)->click();
        $this->waitForElementPresence('//nav[contains(@class, "nav-main") and not(contains(@class, "nav-mobile"))]', 'xpath');

        // Close the marketing banner if it is present.
        $this->closeCampaignContent();
    }

    /**
     * Closes the marketing campaign dialog if it is present.
     */
    protected function closeCampaignContent() : void
    {
        $this->closeDialog('CampaignsContent');
    }

    /**
     * Closes the security warning if it is present.
     */
    protected function closeSecurityWarning() : void
    {
        $this->closeDialog('ui-dialog-title-lbSecutiryWarnings');
    }

    /**
     * Closes the dialog with the given CSS ID if it is present.
     *
     * @param string $id
     *   The CSS ID of the dialog.
     */
    protected function closeDialog(string $id = NULL) : void
    {
        $page = $this->session->getPage();
        if (empty($id) || $page->find('css', '#' . $id)) {
            $close_button = $page->find('css', 'button.close');
            if (!empty($close_button)) {
                // The close button might be present on the page but not yet
                // clickable since it is fading in.
                $attempts = 0;
                do {
                    try {
                        $close_button->click();
                        break;
                    }
                    catch (ElementNotVisible $e) {
                        usleep(500000);
                    }
                    catch (NoSuchElement $e) {
                        // The close button has disappeared from the DOM while
                        // we were interacting with it. We're assuming the
                        // dialog has disappeared.
                        break;
                    }
                }
                while (++$attempts < 6);
                // Now wait for the dialog to fade out.
                try {
                    $this->waitForElementVisibility('button.close', 'css', FALSE);
                } catch (\Exception $e) {
                    throw new \RuntimeException('Attempted to close dialog but it did not disappear.', 0, $e);
                }
            }
        }
    }

}
