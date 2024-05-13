<?php

class TelegramBot {
	private $API_URL = 'https://api.telegram.org/';

	public static function sendMessage(string $chat_id, string $text): array | bool {
		if (!$chat_id) {
			error_log("Empty chat id\n");
			return false;
		}
		if (!$text) {
			error_log("Empty text\n");
			return false;
		}
		$botApi = new static();
		return $botApi->apiRequestJson("sendMessage", ['chat_id' => trim($chat_id), "text" => $text]);
	}

	private function getApiUrl(): string {
		return $this->API_URL.'bot'.constant('BOT_TOKEN').'/';
	}

	private function apiRequestJson(string $method, array $parameters): array | bool {
		if (!is_string($method)) {
			error_log("Method name must be a string\n");
			return false;
		}

		if (!$parameters) {
			$parameters = [];
		} else if (!is_array($parameters)) {
			error_log("Parameters must be an array\n");
			return false;
		}

		$parameters["method"] = $method;

		$handle = curl_init($this->getApiUrl());
		curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5);
		curl_setopt($handle, CURLOPT_TIMEOUT, 60);
		curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($parameters));
		curl_setopt($handle, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
		curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, 2);

		return $this->exec_curl_request($handle);
	}

	private function apiRequest(string $method, array $parameters): array | bool {
		if (!is_string($method)) {
			error_log("Method name must be a string\n");
			return false;
		}

		if (!$parameters) {
			$parameters = [];
		} else if (!is_array($parameters)) {
			error_log("Parameters must be an array\n");
			return false;
		}

		foreach ($parameters as &$val) {
			// encoding to JSON array parameters, for example reply_markup
			if (!is_numeric($val) && !is_string($val)) {
				$val = json_encode($val);
			}
		}
		$url = $this->getApiUrl().$method.'?'.http_build_query($parameters);

		$handle = curl_init($url);
		curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5);
		curl_setopt($handle, CURLOPT_TIMEOUT, 60);
		curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, 2);

		return $this->exec_curl_request($handle);
	}

	private function exec_curl_request(object | bool $handle): array | bool {
		$response = curl_exec($handle);

		if ($response === false) {
			$errno = curl_errno($handle);
			$error = curl_error($handle);
			error_log("Curl returned error $errno: $error\n");
			curl_close($handle);
			return false;
		}

		$http_code = intval(curl_getinfo($handle, CURLINFO_HTTP_CODE));
		curl_close($handle);

		if ($http_code >= 500) {
			// do not wat to DDOS server if something goes wrong
			sleep(10);
			return false;
		} else if ($http_code != 200) {
			$response = json_decode($response, true);
			error_log("Request has failed with error {$response['error_code']}: {$response['description']}\n");
			if ($http_code == 401) {
				throw new Exception('Invalid access token provided');
			}
			return false;
		} else {
			$response = json_decode($response, true);
			if (isset($response['description'])) {
				error_log("Request was successfull: {$response['description']}\n");
			}
			$response = $response['result'];
		}

		return $response;
	}
}
