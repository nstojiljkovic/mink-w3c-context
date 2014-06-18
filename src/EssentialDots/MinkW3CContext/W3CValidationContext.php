<?php

namespace EssentialDots\MinkW3CContext;

use Behat\Behat\Context\ClosuredContextInterface,
	Behat\Behat\Context\TranslatedContextInterface,
	Behat\Behat\Context\BehatContext,
	Behat\Behat\Exception\PendingException;
use Behat\Gherkin\Node\PyStringNode,
	Behat\Gherkin\Node\TableNode;

use Behat\MinkExtension\Context\MinkContext;

use Behat\Mink\Exception\ElementNotFoundException,
	Behat\Mink\Exception\ExpectationException,
	Behat\Mink\Exception\ResponseTextException,
	Behat\Mink\Exception\ElementHtmlException,
	Behat\Mink\Exception\ElementTextException;

use \Guzzle\Http\Exception\CurlException;

/**
 * Features context.
 */
class W3CValidationContext extends \EssentialDots\Weasel\GeneralRawMinkContext {

	/**
	 * @var array;
	 */
	protected $warnings;

	/**
	 * @var array;
	 */
	protected $errors;

	/**
	 * @throws \Behat\Behat\Exception\PendingException
	 */
	protected function initializeSettings(\Behat\Mink\Session $session) {
		$this->isInitialized = TRUE;

		// initialize settings
		// load general settings
		$generalSettingsFile = __DIR__ . DIRECTORY_SEPARATOR . 'Settings' . DIRECTORY_SEPARATOR . 'settings.general.ini';
		if (@file_exists($generalSettingsFile) && @is_file($generalSettingsFile)) {
			$generalSettings = parse_ini_file($generalSettingsFile, true);
			if ($generalSettings === FALSE) {
				throw new \Exception("Wrong format of the settings.general.ini file.");
			}
		} else {
			$generalSettings = array();
		}

		$this->settings = $generalSettings;
	}

	/**
	 * @When /^I check source code on W3C validation service$/
	 */
	public function iCheckSourceCodeOnW3CValidationService() {

		$this->resetErrors();
		$this->resetWarnings();

		// get compressed source code of page
		$compressedSourceCode = $this->compressMarkup($this->getSession()->getPage()->getContent());

		$curlException = null;
		do {
			try {
				$this->getSession()->visit("http://validator.w3.org/#validate_by_input");
				$curlException = null;
			} catch (CurlException $excp) {
				$curlException = $excp;
			}
		} while ($curlException != null);

		// set source code which will be tested

		$inputBox = $this->getSession()->getPage()->find('css', $this->settings['selectors']['sourceTextArea']);
		/** @var $inputBox NodeElement */
		if (!$inputBox) {
			throw new ExpectationException("Cannot find input box in W3C website. W3C validator might be unavailable or changed the markup...", $this->getSession());
		}
		$inputBox->setValue($compressedSourceCode);

		// press Check button
		$submitButton = $this->getSession()->getPage()->find('css', $this->settings['selectors']['submitButton']);
		/** @var $submitButton NodeElement */
		if (!$submitButton) {
			throw new ExpectationException("Cannot find submit box in W3C website. W3C validator might be unavailable or changed the markup...", $this->getSession());
		}

		do {
			try {
				$submitButton->press();
				$curlException = null;
			} catch (CurlException $excp) {
				$curlException = $excp;
			}
		} while ($curlException != null);

		// find warnings
		$warnings = $this->getSession()->getPage()->findAll('css', $this->settings['selectors']['warning']);
		foreach ($warnings as $warn) {
			$this->warnings[] = $warn->getText();
		}

		// find errors
		$errors = $this->getSession()->getPage()->findAll('css', $this->settings['selectors']['errors']);
		foreach ($errors as $err) {
			$this->errors[] = $err->getText();
		}
	}

	/**
	 * @Then /^I should see (.*) W3C validation errors$/
	 */
	public function iShouldSeeNW3CValidationErrors($countStr) {
		$expectedErrorCount = $this->getNumber($countStr);
		$actualErrorCount = count($this->errors);
		if ($actualErrorCount != $expectedErrorCount) {
			throw new ExpectationException("Expected errors: {$expectedErrorCount}. Actual found errors: {$actualErrorCount}." . ($actualErrorCount ? (" Detailed list of errors: \n" . implode("\n", $this->errors)) : ''), $this->getSession());
		}
	}

	/**
	 * @Given /^I should see (.*) W3C validation warnings$/
	 */
	public function iShouldSeeNW3CValidationWarnings($countStr) {
		$expectedWarningCount = $this->getNumber($countStr);
		$actualWarningCount = count($this->errors);
		if ($actualWarningCount != $expectedWarningCount) {
			throw new ExpectationException("Expected warnings: {$expectedWarningCount}. Actual found warnings: {$actualWarningCount}." . ($actualWarningCount ? (" Detailed list of warnings: \n" . implode("\n", $this->warnings)) : ''), $this->getSession());
		}
	}

	/**
	 * @param string $markupCode
	 * @return string
	 */
	protected function compressMarkup($markupCode) {
		// compressing all white spaces
		$markupCode = preg_replace('/(\r\n|\n|\r|\t)/im', '', $markupCode);
		$markupCode = preg_replace('/\s+/m', ' ', $markupCode);

		return $markupCode;
	}

	/**
	 * @param $str
	 * @return int
	 */
	protected function getNumber($str) {
		switch (trim($str)) {
			case 'no':
				$result = 0;
				break;
			default:
				$result = intval($str);
				break;
		}

		return $result;
	}

	/**
	 * @return void
	 */
	protected function resetWarnings() {
		$this->warnings = array();
	}

	/**
	 * @return void
	 */
	protected function resetErrors() {
		$this->errors = array();
	}
}
