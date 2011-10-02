<?php

	libxml_use_internal_errors(true);

	function parse_cookies($response, $cookie_arr) {
		if(strpos($response, '<html') !== false) {
			$body = substr($response, 0, strpos($response, '<html'));
		} else {
			$body = $response;
		}
		$headers = preg_split("[\n|\r]", $body);
		error_log(count($headers));
		foreach($headers as $header) {
			if(strpos($header, 'Set-Cookie: ')!==FALSE) {
				$parts = preg_split('[Set-Cookie: ]', $header);
				$cookie = $parts[1];
				$breakEquals = strpos($cookie, '=');
				$breakSemi = strpos($cookie, '; ');
				$key = substr($cookie, 0, $breakEquals);
				$value = substr($cookie, $breakEquals+1, $breakSemi-$breakEquals-1);
				$cookie_arr[$key] = $value;
			}
		}
		return $cookie_arr;
	}
	
	function generate_cookie_str($cookie_arr) {
		$cookie_str = '';
		foreach($cookie_arr as $key=>$value) {
			$cookie_str .= $key . '=' . $value . '; ';
		}
		return $cookie_str;
	}
	
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
	
	$curlHandle = curl_init();
	curl_setopt($curlHandle, CURLOPT_SSLVERSION, 3);
	curl_setopt($curlHandle, CURLOPT_SSL_VERIFYHOST, FALSE);
	curl_setopt($curlHandle, CURLOPT_SSL_VERIFYPEER, FALSE);
	curl_setopt($curlHandle, CURLOPT_VERBOSE, FALSE);
	curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($curlHandle, CURLOPT_USERAGENT, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.7; rv:6.0.2) Gecko/20100101 Firefox/6.0.2');
	curl_setopt($curlHandle, CURLOPT_COOKIE, '');
	curl_setopt($curlHandle, CURLOPT_ENCODING, 'gzip, deflate');
	curl_setopt($curlHandle, CURLOPT_HTTPHEADER, array("Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8"));
	curl_setopt($curlHandle, CURLOPT_HTTPHEADER, array("Accept-Language: en-us,en;q=0.5"));
	
	$COOKIE_JAR = array();
	
	/*
	* REQUEST #0 (GET)
	*/
	curl_setopt($curlHandle, CURLOPT_POST, false);
	curl_setopt($curlHandle, CURLOPT_HEADER, true);
	curl_setopt($curlHandle, CURLOPT_URL, 'https://uofvirginia.netcardmanager.com/student/local_login.php');
	curl_setopt($curlHandle, CURLOPT_COOKIE, generate_cookie_str($COOKIE_JAR));
	
	$response = curl_exec($curlHandle);
	if($response === false) {
		error_log("FATAL: GET 0 request failed.");
		exit();
	}
	$COOKIE_JAR = parse_cookies($response, $COOKIE_JAR);
	$REDIRECT = parse_redirect($response);
	
	//echo $REDIRECT . "<br/><br/><br/>";
	
	$template = 'https://shib0.itc.virginia.edu/shibboleth-idp/SSO?shire=https%3A%2F%2Fuofvirginia.netcardmanager.com%2FShibboleth.sso%2FSAML%2FPOST&time=&target=https%3A%2F%2Fuofvirginia.netcardmanager.com%2Fstudent%2Flocal_login.php&providerId=https%3A%2F%2Fuofvirginia.netcardmanager.com';
	
	$REDIRECT = substr($REDIRECT, strpos($REDIRECT, 'time'));
	$REDIRECT = substr($REDIRECT, 0, strpos($REDIRECT, "&target"));
	
	//echo 'HERE: ' . $REDIRECT . "<br/><br/><br/>";
	
	$REDIRECT = str_replace("time=", $REDIRECT, $template);
	
	//echo $REDIRECT . "<br/><br/><br/>";
	
	/*
	* REQUEST #1 (GET)
	* Issued to get the pubcookie_pre_s and pubcookie_g_req cookies.
	*/
	curl_setopt($curlHandle, CURLOPT_POST, false);
	curl_setopt($curlHandle, CURLOPT_HEADER, true);
	curl_setopt($curlHandle, CURLOPT_URL, $REDIRECT);
	curl_setopt($curlHandle, CURLOPT_COOKIE, generate_cookie_str($COOKIE_JAR));
	
	$response = curl_exec($curlHandle);
	if($response === false) {
		error_log("FATAL: GET 1 request failed.");
		exit();
	}
	$COOKIE_JAR = parse_cookies($response, $COOKIE_JAR);
	
	/*
	* REQUEST #3 (POST)
	* Sends the login credentials to NetBadge.
	* We need pubcookie_g from the response.
	*/
	curl_setopt($curlHandle, CURLOPT_POST, false);
	curl_setopt($curlHandle, CURLOPT_HEADER, false);
	curl_setopt($curlHandle, CURLOPT_URL, 'https://netbadge.virginia.edu/');
	curl_setopt($curlHandle, CURLOPT_COOKIE, generate_cookie_str($COOKIE_JAR));
	
	$response = curl_exec($curlHandle);
	if($response === false) {
		error_log("FATAL: GET 1 request failed.");
		exit();
	}
	$COOKIE_JAR = parse_cookies($response, $COOKIE_JAR);
	
	$dom = new DOMDocument;
	$dom->loadHTML($response);
	$xpath = new DOMXPath($dom);
	$inputs = $xpath->evaluate('/html/body/div/div/div/fieldset/span/form[@action="index.cgi"]/input[@type="hidden"]');
		
	$formPostStr = 'user=mjq4aq&pass=<redacted>';
		
	for($i = 0; $i < $inputs->length; $i++) {
		$formPostStr .= '&' . $inputs->item($i)->getAttribute('name') . '=' . $inputs->item($i)->getAttribute('value');
	}
	
	/*
	* REQUEST #3 (POST)
	* Sends the login credentials to NetBadge.
	* We need pubcookie_g from the response.
	*/
	curl_setopt($curlHandle, CURLOPT_POST, true);
	curl_setopt($curlHandle, CURLOPT_HEADER, true);
	curl_setopt($curlHandle, CURLOPT_URL, 'https://netbadge.virginia.edu/index.cgi');
	curl_setopt($curlHandle, CURLOPT_POSTFIELDS, $formPostStr);
	curl_setopt($curlHandle, CURLOPT_COOKIE, generate_cookie_str($COOKIE_JAR));
	
	$response = curl_exec($curlHandle);
	if($response === false) {
		error_log("FATAL: GET 1 request failed.");
		exit();
	}
	$COOKIE_JAR = parse_cookies($response, $COOKIE_JAR);
	
	if(strpos($response, '<div id="loginError">') == true) {
		/*
		 * TODO: Handle
		*/
		error_log('invalid-credentials');
		exit();
	}

	$REDIRECT = substr($response, strpos($response, "window.location.replace('"));
	$REDIRECT = substr($REDIRECT, strpos($REDIRECT, "https://"));
	$REDIRECT = substr($REDIRECT, 0, strpos($REDIRECT, "')\">"));
	
// 	echo $REDIRECT;
// 	echo "<br/><br/></br>";
	
// 	$REDIRECT = str_replace('target=cookie', 'target=https%3A%2F%2Fuofvirginia.netcardmanager.com%2Fstudent%2Flocal_login.php', $REDIRECT);
// 	echo $REDIRECT;

	//echo $REDIRECT . "<br/><br/><br/>";
	
	$template = 'https://shib0.itc.virginia.edu/shibboleth-idp/SSO?shire=https%3A%2F%2Fuofvirginia.netcardmanager.com%2FShibboleth.sso%2FSAML%2FPOST&time=&target=https%3A%2F%2Fuofvirginia.netcardmanager.com%2Fstudent%2Flocal_login.php&providerId=https%3A%2F%2Fuofvirginia.netcardmanager.com';
	
	$REDIRECT = substr($REDIRECT, strpos($REDIRECT, 'time'));
	$REDIRECT = substr($REDIRECT, 0, strpos($REDIRECT, "&amp;target"));
	
	//echo $REDIRECT;
	
	$REDIRECT = str_replace("time=", $REDIRECT, $template);
	
	//echo $REDIRECT;
	
	/*
	* REQUEST #4 (GET)
	* Sends pubcookie_g and pubcookie_pre_s to server.
	* We need the form with sha1, random, time, etc. from the response.
	*/
	curl_setopt($curlHandle, CURLOPT_REFERER, 'https://netbadge.virginia.edu/index.cgi');
	curl_setopt($curlHandle, CURLOPT_POST, false);
	curl_setopt($curlHandle, CURLOPT_HEADER, true);
	
	/*
	 * Prevent Shibboleth from looking at the cookie store for redirect info; just tell
	* it via the URL
	*/
	//curl_setopt($curlHandle, CURLOPT_URL, 'https://shib0.itc.virginia.edu/shibboleth-idp/SSO?shire=https%3A%2F%2Fuofvirginia.netcardmanager.com%2FShibboleth.sso%2FSAML%2FPOST&time=1317515214&target=https%3A%2F%2Fuofvirginia.netcardmanager.com%2Fstudent%2Flocal_login.php&providerId=https%3A%2F%2Fuofvirginia.netcardmanager.com');
	curl_setopt($curlHandle, CURLOPT_URL, $REDIRECT);
	curl_setopt($curlHandle, CURLOPT_COOKIE, generate_cookie_str($COOKIE_JAR));
	
	$response = curl_exec($curlHandle);
	if($response === false) {
		error_log("FATAL: GET 1 request failed.");
		exit();
	}
	$COOKIE_JAR = parse_cookies($response, $COOKIE_JAR);
	
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
	* REQUEST #4 (POST)
	* Sends pubcookie_g and pubcookie_pre_s to server.
	* We need the form with sha1, random, time, etc. from the response.
	*/
	curl_setopt($curlHandle, CURLOPT_POST, true);
	curl_setopt($curlHandle, CURLOPT_HEADER, true);
	curl_setopt($curlHandle, CURLOPT_URL, 'https://uofvirginia.netcardmanager.com/Shibboleth.sso/SAML/POST');
	curl_setopt($curlHandle, CURLOPT_POSTFIELDS, $formPostStr);
	curl_setopt($curlHandle, CURLOPT_COOKIE, generate_cookie_str($COOKIE_JAR));
	
	$response = curl_exec($curlHandle);
	if($response === false) {
		error_log("FATAL: GET 1 request failed.");
		exit();
	}
	$COOKIE_JAR = parse_cookies($response, $COOKIE_JAR);
	$REDIRECT = parse_redirect($response);
	
	/*
	* REQUEST #5 (GET)
	* Sends pubcookie_g and pubcookie_pre_s to server.
	* We need the form with sha1, random, time, etc. from the response.
	*/
	curl_setopt($curlHandle, CURLOPT_POST, false);
	curl_setopt($curlHandle, CURLOPT_HEADER, true);
	curl_setopt($curlHandle, CURLOPT_URL, 'https://uofvirginia.netcardmanager.com/student/local_login.php');
	curl_setopt($curlHandle, CURLOPT_COOKIE, generate_cookie_str($COOKIE_JAR));
	
	$response = curl_exec($curlHandle);
	if($response === false) {
		error_log("FATAL: GET 1 request failed.");
		exit();
	}
	$COOKIE_JAR = parse_cookies($response, $COOKIE_JAR);
	
	error_log($response);
	echo $COOKIE_JAR;
	
	/*
	* REQUEST #6 (GET)
	* Sends pubcookie_g and pubcookie_pre_s to server.
	* We need the form with sha1, random, time, etc. from the response.
	*/
	curl_setopt($curlHandle, CURLOPT_POST, false);
	curl_setopt($curlHandle, CURLOPT_HEADER, true);
	curl_setopt($curlHandle, CURLOPT_URL, 'https://uofvirginia.netcardmanager.com/student/welcome.php');
	curl_setopt($curlHandle, CURLOPT_COOKIE, generate_cookie_str($COOKIE_JAR));
	
	$response = curl_exec($curlHandle);
	if($response === false) {
		error_log("FATAL: GET 1 request failed.");
		exit();
	}
	$COOKIE_JAR = parse_cookies($response, $COOKIE_JAR);
	
	error_log($response);
	exit();
	
?>