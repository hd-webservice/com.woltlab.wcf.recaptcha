<?php
namespace wcf\system\recaptcha;
use wcf\system\exception\SystemException;
use wcf\system\exception\UserInputException;
use wcf\system\io\RemoteFile;
use wcf\system\SingletonFactory;
use wcf\system\WCF;
use wcf\util\StringUtil;
use wcf\util\UserUtil;

/**
 * Handles reCAPTCHA support.
 * 
 * Based upon reCAPTCHA-plugin originally created in 2010 by Markus Bartz <roul@codingcorner.info>
 * and released under the conditions of the GNU Lesser General Public License.
 *
 * @author	Alexander Ebert
 * @copyright	2001-2011 WoltLab GmbH
 * @license	GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @package	com.woltlab.wcf.recaptcha
 * @subpackage	system.recaptcha
 * @category 	Community Framework
 */
class RecaptchaHandler extends SingletonFactory {
	/**
	 * list of supported languages
	 * @var	array<string>
	 * @see	http://code.google.com/intl/de-DE/apis/recaptcha/docs/customization.html#i18n
	 */	
	protected $supportedLanguages = array(
		'de', 'en', 'es', 'fr', 'nl', 'pt', 'ru', 'tr'
	);
	
	/**
	 * language code
	 * @var	string
	 */	
	protected $languageCode = '';
	
	/**
	 * public key
	 * @var	string
	 */	
	protected $publicKey = '';
	
	/**
	 * private key
	 * @var	string
	 */	
	protected $privateKey = '';
	
	/**
	 * SSL support
	 * @var	boolean
	 */	
	protected $useSSL = false;
	
	// reply codes (see <http://code.google.com/intl/de-DE/apis/recaptcha/docs/verify.html>)
	const VALID_ANSWER = 'valid';
	const ERROR_UNKNOWN = 'unknown';
	const ERROR_INVALID_PUBLICKEY = 'invalid-site-public-key';
	const ERROR_INVALID_PRIVATEKEY = 'invalid-site-private-key';
	const ERROR_INVALID_COOKIE = 'invalid-request-cookie';
	const ERROR_INCORRECT_SOLUTION = 'incorrect-captcha-sol';
	const ERROR_INCORRECT_PARAMS = 'verify-params-incorrect';
	const ERROR_INVALID_REFFERER = 'invalid-referrer';
	const ERROR_NOT_REACHABLE = 'recaptcha-not-reachable';
	
	/**
	 * @see	wcf\system\SingletonFactory::init()
	 */
	protected function init() {
		// set appropriate language code, fallback to EN if language code is not known to reCAPTCHA-API
		$this->languageCode = WCF::getLanguage()->getFixedLanguageCode();
		if (!in_array($this->languageCode, $this->supportedLanguages)) {
			$this->languageCode = 'en';
		}
		
		// fetch appropriate keys
		$this->publicKey = $this->getKey(RECAPTCHA_PUBLICKEY, 'public');
		$this->privateKey = $this->getKey(RECAPTCHA_PRIVATEKEY, 'private');
		
		// determine SSL support
		if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') {
			$this->useSSL = true;
		}
	}
	
	/**
	 * Returns appropriate public or private key, supports multiple hosts.
	 * 
	 * @param	string		$pubKey
	 * @param	string		$type
	 * @return	string
	 */	
	protected function getKey($pubKey, $type) {
		// check if multiple keys are given
		$keys = explode("\n", $pubKey);
		if (count($keys) > 1) {
			foreach ($keys as $key) {
				$keyParts = explode(':', $key);
				
				if (StringUtil::trim($keyParts[0]) == $_SERVER['HTTP_HOST']) {
					return StringUtil::trim($keyParts[1]);
				}
			}
		}
		else {
			return $pubKey;
		}
		
		throw new SystemException('No valid '.$type.' key for reCAPTCHA found.');
	}
	
	/**
	 * Validates response against given challenge.
	 * 
	 * @param	string		$challenge
	 * @param	string		$response
	 */
	public function validate($challenge, $response) {
		$response = $this->verify($challenge, $response);
		switch ($response) {
			case self::VALID_ANSWER:
				break;
			
			case self::ERROR_INCORRECT_SOLUTION:
				throw new UserInputException('recaptchaString', 'false');
				break;
			
			case self::ERROR_NOT_REACHABLE:
				// if reCaptcha server is unreachable mark captcha as done
				// this should be better than block users until server is back.
				// - RouL
				break;
			
			default:
				throw new SystemException('reCAPTCHA returned the following error: '.$response);
		}
		
		WCF::getSession()->register('recaptchaDone', true);
	}
	
	/**
	 * Queries server to verify successful response.
	 * 
	 * @param	string		$challenge
	 * @param	string		$response
	 */	
	protected function verify($challenge, $response) {
		// get proxy
		$options = array();
		if (PROXY_SERVER_HTTP) $options['http']['proxy'] = PROXY_SERVER_HTTP;
		
		$remoteFile = new RemoteFile('www.google.com', 80, 30, $options); // the file to read.
		if (!isset($remoteFile)) {
			return self::ERROR_NOT_REACHABLE;
		}
		
		// build post string
		$postData = 'privatekey='.urlencode($this->privateKey);
		$postData .= '&remoteip='.urlencode(UserUtil::getIpAddress());
		$postData .= '&challenge='.urlencode($challenge);
		$postData .= '&response='.urlencode($response);
		
		// build and send the http request.
		$request = "POST /recaptcha/api/verify HTTP/1.0\r\n";
		$request .= "User-Agent: HTTP.PHP (RecaptchaHandler.class.php; WoltLab Community Framework/".WCF_VERSION."; ".WCF::getLanguage()->getFixedLanguageCode().")\r\n";
		$request .= "Accept: */*\r\n";
		$request .= "Accept-Language: ".WCF::getLanguage()->getFixedLanguageCode()."\r\n";
		$request .= "Host: www.google.com\r\n";
		$request .= "Content-Type: application/x-www-form-urlencoded;\r\n";
		$request .= "Content-Length: ".strlen($postData)."\r\n";
		$request .= "Connection: Close\r\n\r\n";
		$request .= $postData;
		$remoteFile->puts($request);
		
		$waiting = true;
		$readResponse = array();
		$reCaptchaResponse = array();
		
		// read http response.
		while (!$remoteFile->eof()) {
			$readResponse[] = $remoteFile->gets();
			// look if we are done with transferring the requested file.					 
			if ($waiting) {
				if (rtrim($readResponse[count($readResponse) - 1]) == '') {
					$waiting = false;
				}						
			}
			else {
				// look if the webserver sent an error http statuscode
				// This has still to be checked if really sufficient!
				$arrayHeader = array('201', '301', '302', '303', '307', '404');
				foreach ($arrayHeader as $code) {
					$error = strpos($readResponse[0], $code);
				}
				if ($error !== false) {
					return self::ERROR_NOT_REACHABLE;
				}
				// write to the target system.
				$reCaptchaResponse[] = $readResponse[count($readResponse) - 1];
			}
		}
		
		if (StringUtil::trim($reCaptchaResponse[0]) == "true") {
			return self::VALID_ANSWER;
		}
		else {
			return StringUtil::trim($reCaptchaResponse[1]);
		}
	}
	
	/**
	 * Assigns template variables for reCAPTCHA
	 */
	public function assignVariables() {
		WCF::getTPL()->assign(array(
			'recaptchaLanguageCode' => $this->languageCode,
			'recaptchaPublicKey' => $this->publicKey,
			'recaptchaUseSSL' => $this->useSSL
		));
	}
}
