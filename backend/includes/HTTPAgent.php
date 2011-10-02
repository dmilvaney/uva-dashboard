<?php

class HTTPAgent
{
	private $COOKIE_ARRAY;
	private $curlHandle;
	
	public function __construct() {
		$this->COOKIE_ARRAY = array();
		$this->curlHandle = curl_init();
		
		curl_setopt($this->curlHandle, CURLOPT_SSLVERSION, 3);
		curl_setopt($this->curlHandle, CURLOPT_SSL_VERIFYHOST, FALSE);
		curl_setopt($this->curlHandle, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($this->curlHandle, CURLOPT_VERBOSE, FALSE);
		curl_setopt($this->curlHandle, CURLOPT_RETURNTRANSFER, TRUE);
	}
	
	public function GET($url, $need_header) {
		curl_setopt($this->curlHandle, CURLOPT_POST, false);
		curl_setopt($this->curlHandle, CURLOPT_HEADER, $need_header);
		curl_setopt($this->curlHandle, CURLOPT_URL, $url);
		
		curl_setopt($this->curlHandle, CURLOPT_COOKIE, $this->generate_cookie_str());
		
		$response = curl_exec($this->curlHandle);
		if($response === false) {
			//TODO: Handle
			error_log("FATAL: GET request failed.");
			exit();
		}
		
		$this->parse_cookies($response);
		return $response;
	}
	
	public function POST($url, $post_data, $need_header) {
		curl_setopt($this->curlHandle, CURLOPT_POST, true);
		curl_setopt($this->curlHandle, CURLOPT_HEADER, $need_header);
		curl_setopt($this->curlHandle, CURLOPT_URL, $url);
		curl_setopt($this->curlHandle, CURLOPT_POSTFIELDS, $post_data);
		
		curl_setopt($this->curlHandle, CURLOPT_COOKIE, $this->generate_cookie_str());
		
		$response = curl_exec($this->curlHandle);
		if($response === false) {
			//TODO: Handle
			error_log("FATAL: POST request failed.");
			exit();
		}
		
		$this->parse_cookies($response);
		return $response;
	}
	
	private function generate_cookie_str() {
		$cookie_str = '';
		foreach($this->COOKIE_ARRAY as $key=>$value) {
			$cookie_str .= $key . '=' . $value . '; ';
		}
		return $cookie_str;
	}
	
	private function parse_cookies($response) {
		if(strpos($response, '<html') !== false) {
			$body = substr($response, 0, strpos($response, '<html'));
		} else {
			$body = $response;
		}
		$headers = preg_split("[\n|\r]", $body);
		foreach($headers as $header) {
			if(strpos($header, 'Set-Cookie: ')!==FALSE) {
				$parts = preg_split('[Set-Cookie: ]', $header);
				$cookie = $parts[1];
				$breakEquals = strpos($cookie, '=');
				$breakSemi = strpos($cookie, '; ');
				$key = substr($cookie, 0, $breakEquals);
				$value = substr($cookie, $breakEquals+1, $breakSemi-$breakEquals-1);
				$this->COOKIE_ARRAY[$key] = $value;
			}
		}
	}
}
?>