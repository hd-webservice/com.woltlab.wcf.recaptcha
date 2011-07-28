<div class="formFieldDesc">
	<p>{lang}wcf.recaptcha.recaptchaString.description{/lang}</p>
</div>
<div class="formField">
	<script type="text/javascript">
		//<![CDATA[
		var RecaptchaOptions = {
			lang: '{@$recaptchaLanguageCode}'
		}
		//]]>
	</script>
	<script type="text/javascript" src="http{if $recaptchaUseSSL}s{/if}://www.google.com/recaptcha/api/challenge?k={$recaptchaPublicKey}"></script>

	<noscript>
		<iframe src="http{if $recaptchaUseSSL}s{/if}://www.google.com/recaptcha/api/challenge?k={$recaptchaPublicKey}" height="300" width="500" frameborder="0"></iframe><br />
		<textarea name="recaptcha_challende_field" rows="3" cols="40"></textarea>
		<input type="hidden" name="recaptcha_response_field" value="manuel_challenge" />
	</noscript>
	{if $errorField == 'recaptchaString'}
		<p class="innerError">
			{if $errorType == 'empty'}{lang}wcf.global.error.empty{/lang}{/if}
			{if $errorType == 'false'}{lang}wcf.recaptcha.error.recaptchaString.false{/lang}{/if}
		</p>
	{/if}
</div>
