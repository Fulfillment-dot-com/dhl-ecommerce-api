<?php


namespace Fulfillment\DHL\Api;

use FoxxMD\Utilities\ArrayUtil;
use Fulfillment\DHL\Api\Configuration\ApiConfiguration;
use Fulfillment\DHL\Api\Exceptions\MissingCredentialException;
use Fulfillment\DHL\Api\Http\Request;
use Fulfillment\DHL\Api\Utilities\Helper;
use Fulfillment\DHL\Api\Utilities\RequestParser;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use League\CLImate\CLImate;
use League\CLImate\Util\Writer\File;
use League\CLImate\Util\Writer\WriterInterface;
use Dotenv;


class Api
{
	protected $config;
	protected $http;
	protected $guzzle;
	protected $climate;

	/**
	 * @param $config array|string|\Fulfillment\DHL\Api\Contracts\ApiConfiguration|null
	 * @param $logger WriterInterface|null
	 * @param $guzzle Client|null
	 *
	 * @throws \Exception
	 */

	public function __construct($config = null, $logger = null, $guzzle = null)
	{

		//setup guzzle
		$this->guzzle = null !== $guzzle ? $guzzle : new Client();

		//setup climate
		$this->climate = new CLImate;

		if (null !== $logger) {
			$this->climate->output->add('customLogger', $logger)->defaultTo('customLogger');
		} else {
			if (php_sapi_name() !== 'cli') {
				//if no custom logger and this isn't a CLI app then we need to write to a file
				$path     = Helper::getStoragePath('logs/');
				$file = $path . 'Log--' . date('Y-m-d') . '.log';
				if(!file_exists($path) || !is_writable($path) || !$resource = fopen($file, 'a')) {
					$this->climate->output->defaultTo('buffer');
				} else {
					fclose($resource);
					$logFile = new File($file);
					$this->climate->output->add('file', $logFile)->defaultTo('file');
				}

				if (!getenv('NOANSI')) {
					//we want to logs to have ANSI encoding so we can tail the log remotely and get pretty colors
					$this->climate->forceAnsiOn();
				}
			}
		}

		//setup configuration
		if (is_string($config) || null === $config) {
			if (null !== $config) {
				if (!is_dir($config)) {
					throw new \RuntimeException('The provided directory location does not exist at ' . $config);
				}
				Dotenv::load($config);
			}
			$data         = [
				'username'           => getenv('USERNAME') ?: null,
				'password'           => getenv('PASSWORD') ?: null,
				'clientId'           => getenv('CLIENT_ID') ?: null,
				'accessToken'        => getenv('ACCESS_TOKEN') ?: null,
				'endPoint'           => getenv('API_ENDPOINT') ?: null,
				'authEndpoint'       => getenv('AUTH_ENDPOINT') ?: null,
				'scope'              => getenv('SCOPE') ?: null,
				'storeAuth'         => getenv('STORE_AUTH_DATA') ?: null,
				'loggerPrefix'       => getenv('LOGGER_PREFIX') ?: null,
				'storageAuthDataPrefix' => getenv('STORAGE_AUTH_DATA_PREFIX') ?: null
			];
			$this->config = new ApiConfiguration($data);

		} else {
			if (is_array($config)) {
				$data = [
					'username'           => ArrayUtil::get($config['username']),
					'password'           => ArrayUtil::get($config['password']),
					'clientId'           => ArrayUtil::get($config['clientId']),
					'accessToken'        => ArrayUtil::get($config['accessToken']),
					'endpoint'           => ArrayUtil::get($config['endpoint']),
					'authEndpoint'       => ArrayUtil::get($config['authEndpoint']),
					'scope'              => ArrayUtil::get($config['scope']),
					'storeToken'         => ArrayUtil::get($config['storeAuthData']),
					'loggerPrefix'       => ArrayUtil::get($config['loggerPrefix']),
					'storageTokenPrefix' => ArrayUtil::get($config['storageAuthDataPrefix'])
				];
				$this->config = new ApiConfiguration($data);

			} else {
				if ($config instanceof \Fulfillment\DHL\Api\Contracts\ApiConfiguration) {
					$this->config = $config;
				}
			}
		}

		if ($this->config->shouldStoreAuthData() && null === $this->config->getAccessToken() && file_exists(Helper::getStoragePath($this->config->getStorageAuthDataFilename()))) {
			//try to get from file
				$data = json_decode(file_get_contents(Helper::getStoragePath($this->config->getStorageAuthDataFilename())), true);
				if($data !== null) {
					$this->config->setAccessToken(ArrayUtil::get($data['access_token']));
					$this->config->setState(ArrayUtil::get($data['state']));
					$this->climate->info($this->config->getLoggerPrefix() . 'Got token ' . $this->config->getAccessToken() . ' from storage.');
				} else {
					$this->climate->info($this->config->getLoggerPrefix() . 'No usable data found in storage.');
				}

		}

		if (null === $this->config->getAccessToken() && (null === $this->config->getClientId() || null === $this->config->getUsername() || null === $this->config->getPassword())) {
			throw new \InvalidArgumentException($this->config->getLoggerPrefix() . 'No access token provided -- so client Id, username, and password must be provided');
		}
		if (null === $this->config->getEndPoint()) {
			throw new \InvalidArgumentException($this->config->getLoggerPrefix() . 'Must provide an endpoint');
		}

		$this->http = new Request($this->guzzle, $this->config, $this->climate);

	}

	public function config()
	{
		return $this->config;
	}

	protected function tryRequest($method, $url, $payload = null, $queryString = [], $firstTry = true)
	{
		try {
			return $this->http->makeRequest($method, $url, $payload, $queryString);
		} catch (ConnectException $c) {
			$this->climate->error($this->config->getLoggerPrefix() . 'Error connecting to endpoint: ' . $c->getMessage());
			throw $c;
		} catch (RequestException $e) {
			if ($e->getResponse()->getStatusCode() === 400 && RequestParser::getErrorCode($e) === 'INVALID_TOKEN') {
				if ($firstTry) {
					$this->climate->info($this->config->getLoggerPrefix() . 'Possibly expired token, trying to refresh token...');
					$authData = $this->http->requestAccessToken();
					if (null !== $authData) {
						$this->config->setAccessToken($authData->access_token);
						$this->http = new Request($this->guzzle, $this->config, $this->climate);
						if ($this->config->shouldStoreAuthData()) {
							file_put_contents(Helper::getStoragePath($this->config->getStorageAuthDataFilename()), json_encode($authData));
						}
					}
					$this->climate->info($this->config->getLoggerPrefix() . 'Retrying request...');

					return $this->tryRequest($method, $url, $payload, $queryString, false);
				} else {
					//something else is wrong and requesting a new token isn't going to fix it
					throw new \Exception($this->config->getLoggerPrefix() . 'The request was unauthorized and could not be fixed by refreshing access token.', 0, $e);
				}
			} else {
				throw $e;
			}
		}
	}

	/**
	 * Get a new access token
	 *
	 * @return string|null
	 * @throws MissingCredentialException
	 */
	public function refreshAccessToken()
	{
		$authData = $this->http->requestAccessToken();
		if (null !== $authData) {
			$this->config->setAccessToken($authData->access_token);
			$this->http = new Request($this->guzzle, $this->config, $this->climate);
			if ($this->config->shouldStoreAuthData()) {
				file_put_contents(Helper::getStoragePath($this->config->getStorageAuthDataFilename()), json_encode($authData));
			}
		}

		return $authData;
	}

	/**
	 * Perform a GET request to the Api
	 *
	 * @param      $url string Relative URL from API base URL
	 * @param null $queryString
	 *
	 * @return mixed
	 */
	public function get($url, $queryString = [])
	{
		return $this->tryRequest('get', $url, null, $queryString);
	}

	/**
	 * Perform a POST request to the Api
	 *
	 * @param      $url     string Relative URL from API base URL
	 * @param      $payload array Request contents as json serializable array
	 * @param null $queryString
	 *
	 * @return mixed
	 */
	public function post($url, $payload, $queryString = [])
	{
		return $this->tryRequest('post', $url, $payload, $queryString);
	}

	/**
	 * Perform a PUT request to the Api
	 *
	 * @param      $url     string Relative URL from API base URL
	 * @param      $payload array Request contents as json serializable array
	 * @param null $queryString
	 *
	 * @return mixed
	 */
	public function put($url, $payload, $queryString = [])
	{
		return $this->tryRequest('put', $url, $payload, $queryString);
	}

	/**
	 * Perform a DELETE request to the Api
	 *
	 * @param      $url string Relative URL from API base URL
	 * @param null $queryString
	 *
	 * @return mixed
	 */
	public function delete($url, $queryString = [])
	{
		return $this->tryRequest('delete', $url, null, $queryString);
	}
}