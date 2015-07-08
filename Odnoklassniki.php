<?php
	
	/*
	 * (c) digitorum.ru
	 */
	
	Class Social_APIClient_Odnoklassniki {
		
		/*
		 * данные для подключения
		 */
		protected $connectionData = array();
		
		/*
		 *  Ссылки для аутентификации
		 */
		protected $authUrl = 'http://www.odnoklassniki.ru/oauth/authorize';
		
		/*
		 * ссылка для получения токена
		 */
		protected $tokenUrl = 'http://api.odnoklassniki.ru/oauth/token.do?';
		
		/*
		 * Ссылка для отправки запросов 
		 */
		protected $apiUrl = 'http://api.odnoklassniki.ru/fb.do?';
		
		/*
		 * Редирект урл
		 */
		protected $redirectUrl = '';
		
		/*
		 * Токены для доступа
		 */
		protected $token = array();
		
		/*
		 * Конструктор
		 */
		public function __construct($connectionData = array()) {
			$this->connectionData = $connectionData;
		}
		
		/*
		 * Установить редирект урл 
		 */
		public function setRedirectUrl($url = '') {
			$this->redirectUrl = $url;
		}
		
		/*
		 * Получить ссылку для подключения
		 */
		public function getLoginUrl($scope = array()) {
			return $this->authUrl . '?'
					. http_build_query(
						array(
							'client_id'     => $this->connectionData['client_id'],
							'response_type' => 'code',
							'redirect_uri'  => $this->redirectUrl,
							'scope' => implode(';', $scope)
						)
					);
		}
		
		/*
		 * Выбросить ошибку
		 */
		public function error($array) {
			throw new Exception($array['error'] . ':' . (isset($array['error_description']) ? $array['error_description'] : ''));
		}
		
		/*
		 * Выставить токен
		 */
		public function setToken($token) {
			if(is_string($token)) {
				$token = json_decode($token, true);
			}
			$this->token = $token;
		} 
		
		/*
		 * Получить строку токена
		 */
		public function getTokenStr() {
			return json_encode($this->token);
		}
		
		/*
		 * Получить токен
		 */
		public function getToken($code = '') {
			if($code) {
				$this->token = $this->sendRequest(
					$this->tokenUrl,
					array(
						'code' => $code,
						'redirect_uri' => $this->redirectUrl,
						'grant_type' => 'authorization_code',
						'client_id' => $this->connectionData['client_id'],
						'client_secret' => $this->connectionData['client_secret']
					)
				);
				if(isset($this->token['error'])) {
					$this->error($this->token);
				} else {
					$this->token['expires'] = time() + 30 * 60; // Маркер доступа имеет ограниченное время существования - 30 минут
				}
			}
		}
		
		/*
		 * Обновить токен
		 */
		public function refreshToken() {
			$this->token = $this->sendRequest(
				$this->tokenUrl,
				array(
					'refresh_token' => $this->token['refresh_token'],
					'grant_type' => 'refresh_token',
					'client_id' => $this->connectionData['client_id'],
					'client_secret' => $this->connectionData['client_secret']
				)
			);
			if(isset($this->token['error'])) {
				$this->error($this->token);
			}
		}
		
		/*
		 * Получить аксес токен
		 */
		public function getAccessToken() {
			if(isset($this->token['access_token'])) {
				if(isset($this->token['expires']) && $this->token['expires'] < time()) {
					$this->refreshToken();
				}
				return $this->token['access_token'];
			}
			return false;
		}
		
		/*
		 * Обратиться к апи 
		 */
		public function api($action = '', $parameters = array(), $method='POST') {
			$accessToken = $this->getAccessToken();
			$paramsArray = array_merge(
				array(
					'application_key' => $this->connectionData['application_key'],
					'method' => $action
				),
				$parameters
			);
			ksort($paramsArray);
			$paramsStr = "";
			foreach($paramsArray as $k=>$v) {
				$paramsStr .= $k . "=" . $v;
			}
			$sig = strtolower(
				md5(
					$paramsStr
					. md5(
						$accessToken
						. $this->connectionData['client_secret']
					)
				)
			);
			//$paramsArray = array_map('urlencode', $paramsArray);
			$paramsArray['access_token'] = $accessToken;
			$paramsArray['sig'] = $sig;
			return $this->sendRequest(
				$this->apiUrl,
				$paramsArray,
				$method
			);
		}
		
		/*
		 * Отправить реквест
		 */
		protected function sendRequest($url = '', $params = array(), $method = 'POST') {
			if(is_array($params)) {
				$params = http_build_query($params);
			}
			$ch = curl_init();
			if($method == 'GET') {
				$url .= $params;
			} else if($method == 'POST') {
				curl_setopt($ch, CURLOPT_POST, 1);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
			}
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
			$result = curl_exec($ch);
			curl_close($ch);
			return json_decode($result, true);
		}
	}
