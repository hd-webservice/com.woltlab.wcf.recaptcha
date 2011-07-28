<?php
namespace wcf\form;
use wcf\system\recaptcha\RecaptchaHandler;
use wcf\system\WCF;
use wcf\util\StringUtil;

/**
 * RecaptchaForm is an abstract form implementation for the use of reCAPTCHA.
 * 
 * @author 	Marcel Werk
 * @copyright	2001-2011 WoltLab GmbH
 * @license	GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @package	com.woltlab.wcf.recaptcha
 * @subpackage	form
 * @category 	Community Framework
 */
abstract class RecaptchaForm extends AbstractForm {
	/**
	 * challenge
	 * @var	string
	 */	
	public $challenge = '';
	
	/**
	 * response
	 * @var	string
	 */	
	public $response = '';
	
	/**
	 * enable recaptcha
	 * @var	boolean
	 */
	public $useCaptcha = true;
	
	/**
	 * @see wcf\page\Page::readParameters()
	 */
	public function readParameters() {
		parent::readParameters();
		
		if (WCF::getUser()->userID || WCF::getSession()->getVar('captchaDone')) {
			$this->useCaptcha = false;
		}
	}
	
	/**
	 * @see wcf\form\Form::readFormParameters()
	 */
	public function readFormParameters() {
		parent::readFormParameters();
		
		if (isset($_POST['recaptcha_challenge_field'])) $this->challenge = StringUtil::trim($_POST['recaptcha_challenge_field']);
		if (isset($_POST['recaptcha_response_field'])) $this->response = StringUtil::trim($_POST['recaptcha_response_field']);
	}
	
	/**
	 * @see wcf\form\Form::validate()
	 */
	public function validate() {
		parent::validate();
		
		$this->validateCaptcha();
	}
	
	/**
	 * Validates the captcha.
	 */
	protected function validateCaptcha() {
		if ($this->useCaptcha) {
			RecaptchaHandler::getInstance()->validate($this->challenge, $this->response);
			$this->useCaptcha = false;
		}
	}
	
	/**
	 * @see	wcf\form\Form::save()
	 */
	public function save() {
		parent::save();
		
		WCF::getSession()->unregister('captchaDone');
	}
	
	/**
	 * @see	wcf\page\Page::assignVariables()
	 */
	public function assignVariables() {
		parent::assignVariables();
		
		RecaptchaHandler::getInstance()->assignVariables();
	}
}