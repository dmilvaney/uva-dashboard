<?php

	libxml_use_internal_errors(true);
	
	include_once('includes/HTTPAgent.php');
	
	$agent = new HTTPAgent();
	
	function parse_redirect($response) {
		$redirect_pos = strpos($response,'Location:');
		if($redirect_pos!=false) {
			$redirect = substr($response, $redirect_pos);
			$parts = preg_split('[\n]', $redirect);
			if(count($parts)>0)
			$redirect = $parts[0];
		}
		$redirect = trim(substr($redirect, strpos($redirect,'http')));
		return $redirect;
	}
	
	/*
	* REQUEST #0 (GET)
	*/
	$response = $agent->GET('https://uofvirginia.netcardmanager.com/student/local_login.php', true);
	$REDIRECT = parse_redirect($response);
	
	$template = 'https://shib0.itc.virginia.edu/shibboleth-idp/SSO?shire=https%3A%2F%2Fuofvirginia.netcardmanager.com%2FShibboleth.sso%2FSAML%2FPOST&time=&target=https%3A%2F%2Fuofvirginia.netcardmanager.com%2Fstudent%2Flocal_login.php&providerId=https%3A%2F%2Fuofvirginia.netcardmanager.com';
	
	$REDIRECT = substr($REDIRECT, strpos($REDIRECT, 'time'));
	$REDIRECT = substr($REDIRECT, 0, strpos($REDIRECT, "&target"));
	$REDIRECT = str_replace("time=", $REDIRECT, $template);
	
	/*
	* REQUEST #1
	*/
	$response = $agent->GET($REDIRECT, true);
	
	/*
	* REQUEST #2
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
	* REQUEST #3
	*/
	$response = $agent->POST('https://netbadge.virginia.edu/index.cgi', $formPostStr, true);
	
	if(strpos($response, '<div id="loginError">') == true) {
		//TODO: Handle
		error_log('invalid-credentials');
		exit();
	}

	$REDIRECT = substr($response, strpos($response, "window.location.replace('"));
	$REDIRECT = substr($REDIRECT, strpos($REDIRECT, "https://"));
	$REDIRECT = substr($REDIRECT, 0, strpos($REDIRECT, "')\">"));
	
	$template = 'https://shib0.itc.virginia.edu/shibboleth-idp/SSO?shire=https%3A%2F%2Fuofvirginia.netcardmanager.com%2FShibboleth.sso%2FSAML%2FPOST&time=&target=https%3A%2F%2Fuofvirginia.netcardmanager.com%2Fstudent%2Flocal_login.php&providerId=https%3A%2F%2Fuofvirginia.netcardmanager.com';
	
	$REDIRECT = substr($REDIRECT, strpos($REDIRECT, 'time'));
	$REDIRECT = substr($REDIRECT, 0, strpos($REDIRECT, "&amp;target"));
	$REDIRECT = str_replace("time=", $REDIRECT, $template);
	
	/*
	* REQUEST #4
	*/
	$response = $agent->GET($REDIRECT, true);
	
	$dom = new DOMDocument;
	$dom->loadHTML($response);
	$xpath = new DOMXPath($dom);
	$inputs = $xpath->evaluate('//input[@type="hidden"]');
	$formPostStr = '';

	/*
	 * MUST URL ENCODE THE POST PARAMTER VALUES OR SHIBBOLETH WONT BE ABLE TO DECODE THE SAMLRESPONSE PARAMETER
	 */
	for($i = 0; $i < $inputs->length; $i++) {
		$formPostStr .= '&' . $inputs->item($i)->getAttribute('name') . '=' . urlencode($inputs->item($i)->getAttribute('value'));
	}
	$formPostStr = substr($formPostStr, 1);
	
	/*
	* REQUEST #5
	*/
	$response = $agent->POST('https://uofvirginia.netcardmanager.com/Shibboleth.sso/SAML/POST', $formPostStr, true);
	$REDIRECT = parse_redirect($response);
	
	/*
	* REQUEST #6
	*/
	$response = $agent->GET('https://uofvirginia.netcardmanager.com/student/local_login.php', true);
	
	/*
	* REQUEST #7
	*/
	$response = $agent->GET('https://uofvirginia.netcardmanager.com/student/welcome.php', false);
	
	echo $response;
	
?>