<?php

	/*
	 * Ignore parsing errors related
	 * to any malformed XML that is returned to us.
	 */
	libxml_use_internal_errors(true);
	
	include_once('includes/HTTPAgent.php');
	
	$agent = new HTTPAgent();
	
	/*
	 * This URL is used twice; it directs Shibboleth to
	 * the protected resource we need to access (in this case, UVA's Card Services page).
	 * The "target" parameter value has been changed from "cookie" to the
	 * URL we wish to access (since Shibboleth refuses to reference the shibstate
	 * cookie for some reason). Note that "time" has been
	 * blanked out, which doesn't cause the auth procedure to fail.
	 */
	$REDIRECT_URL = 'https://shib0.itc.virginia.edu/shibboleth-idp/SSO?shire=https%3A%2F%2Fuofvirginia.netcardmanager.com%2FShibboleth.sso%2FSAML%2FPOST&time=&target=https%3A%2F%2Fuofvirginia.netcardmanager.com%2Fstudent%2Flocal_login.php&providerId=https%3A%2F%2Fuofvirginia.netcardmanager.com';
	
   /*
	* REQUEST #1
	*/
	$agent->GET('https://uofvirginia.netcardmanager.com/student/local_login.php', true);
	
   /*
	* REQUEST #2
	*/
	$agent->GET($REDIRECT_URL, true);
	
   /*
	* REQUEST #3
	*/
	$response = $agent->GET('https://netbadge.virginia.edu/', false);
	
	$dom = new DOMDocument;
	$dom->loadHTML($response);
	$xpath = new DOMXPath($dom);
	$inputs = $xpath->evaluate('/html/body/div/div/div/fieldset/span/form[@action="index.cgi"]/input[@type="hidden"]');
		
	$formPostStr = 'user=mjq4aq&pass=<redacted>';
	for($i = 0; $i < $inputs->length; $i++) {
		$formPostStr .= '&' . $inputs->item($i)->getAttribute('name') . '=' . $inputs->item($i)->getAttribute('value');
	}
	
   /*
	* REQUEST #4
	*/
	$response = $agent->POST('https://netbadge.virginia.edu/index.cgi', $formPostStr, true);
	
	if(strpos($response, '<div id="loginError">') == true) {
		//TODO: Handle
		error_log('invalid-credentials');
		exit();
	}
	
   /*
	* REQUEST #5
	*/
	$response = $agent->GET($REDIRECT_URL, true);
	
	$dom = new DOMDocument;
	$dom->loadHTML($response);
	$xpath = new DOMXPath($dom);
	$inputs = $xpath->evaluate('//input[@type="hidden"]');

	/*
	 * Note that the POST data values are encoded using "urlencode".
	 * This is required; otherwise, Shibboleth will be unable to
	 * base64 decode the SAMLResponse parameter, which in turn prevents
	 * Shibboleth from assigning us a valid authenticated session.
	 */
	$formPostStr = '';
	for($i = 0; $i < $inputs->length; $i++) {
		$formPostStr .= '&' . $inputs->item($i)->getAttribute('name') . '=' . urlencode($inputs->item($i)->getAttribute('value'));
	}
	$formPostStr = substr($formPostStr, 1);
	
   /*
	* REQUEST #6
	*/
	$agent->POST('https://uofvirginia.netcardmanager.com/Shibboleth.sso/SAML/POST', $formPostStr, true);
	
   /*
	* REQUEST #7
	*/
	$agent->GET('https://uofvirginia.netcardmanager.com/student/local_login.php', true);
	
   /*
	* REQUEST #8
	*/
	echo $agent->GET('https://uofvirginia.netcardmanager.com/student/welcome.php', false);
	
?>