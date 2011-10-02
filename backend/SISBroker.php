<?php

class SISBroker
{
	private $log;
	private $job_type;
	private $curl_handle;
	private $persistentCookieJar;
	private $transactionCookieJar;
	
	public function __construct($job_type, $cookie_jar = '') {
		$this->job_type = $job_type;
		
		$this->curl_handle = curl_init();
		curl_setopt($this->curl_handle, CURLOPT_SSLVERSION, 3);
		curl_setopt($this->curl_handle, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($this->curl_handle, CURLOPT_VERBOSE, FALSE);
		curl_setopt($this->curl_handle, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($this->curl_handle, CURLOPT_USERAGENT, 'Windows 3.1');
		curl_setopt($this->curl_handle, CURLOPT_COOKIE, '');
		
		$this->persistentCookieJar = $cookie_jar;
	}
	
	public function transact($job_args) {		
		/*
		 * Execute job using local cURL.
		 */
		if($job_args['HttpMethod']=='GET') {
			return $this->GET($job_args['NeedHeader'], $job_args['Path'], $job_args['CookieJar']);
		} else {
			return $this->POST($job_args['NeedHeader'], $job_args['Path'], $job_args['PostParams'], $job_args['CookieJar']);
		}
	}
	
	private function GET($needHeader, $path, $cookie_str = '') {
		if(strlen($cookie_str)!=0) {
			$this->transactionCookieJar = $cookie_str;
		}
		
		curl_setopt($this->curl_handle, CURLOPT_POST, false);
		
		//TODO: Do this better.
		if($needHeader=='true') {
			curl_setopt($this->curl_handle, CURLOPT_HEADER, true);
		} else {
			curl_setopt($this->curl_handle, CURLOPT_HEADER, false);
		}
		curl_setopt($this->curl_handle, CURLOPT_URL, $path);
		curl_setopt($this->curl_handle, CURLOPT_COOKIE, $this->transactionCookieJar);
		
		//error_log('COOKIE JAR');
		//error_log($this->transactionCookieJar);
		
		$response = curl_exec($this->curl_handle);
		//error_log($response);
		
		if($response==false) {
			error_log("FATAL: SIS_Channel GET request failed to: $path.");
			exit();
		} else {
			return $response;
		}
	}
	
	private function POST($needHeader, $path, $postString, $cookie_str = '') {
		if(strlen($cookie_str)!=0) {
			$this->transactionCookieJar = $cookie_str;
		}
		
		curl_setopt($this->curl_handle, CURLOPT_POST, true);
		
		//TODO: Do this better.
		if($needHeader=='true') {
			curl_setopt($this->curl_handle, CURLOPT_HEADER, true);
		} else {
			curl_setopt($this->curl_handle, CURLOPT_HEADER, false);
		}
		curl_setopt($this->curl_handle, CURLOPT_URL, $path);
		curl_setopt($this->curl_handle, CURLOPT_COOKIE, $this->transactionCookieJar);
		curl_setopt($this->curl_handle, CURLOPT_POSTFIELDS, $postString);
		
		$response = curl_exec($this->curl_handle);
		//error_log($response);
		
		if($response==false) {
			error_log("FATAL: SIS_Channel POST request failed to: $path.");
			exit();
		} else {
			return $response;
		}
	}
	
	public function end() {
		//Do nothing for now.
	}
	
	public function __destruct() {
		curl_close($this->curl_handle);
    }
}

?>