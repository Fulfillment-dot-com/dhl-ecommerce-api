<?php


namespace Fulfillment\DHL\Api\Configuration;

use FoxxMD\Utilities\ArrayUtil;
use \Fulfillment\DHL\Api\Contracts\ApiConfiguration as ConfigContract;

class ApiConfiguration implements ConfigContract
{
	protected $username;
	protected $password;
	protected $clientId;
	protected $scope;
	protected $accessToken;
	protected $state;
	protected $endpoint;
	protected $authEndpoint;
	protected $storeAuthData;
	protected $loggerPrefix;
	protected $storageAuthDataPrefix;

	public function __construct($data = null)
	{
		$this->username              = ArrayUtil::get($data['username']);
		$this->password              = ArrayUtil::get($data['password']);
		$this->clientId              = ArrayUtil::get($data['clientId']);
		$this->state                 = ArrayUtil::get($data['state']);
		$this->accessToken           = ArrayUtil::get($data['accessToken']);
		$this->endpoint              = ArrayUtil::get($data['endpoint'], 'https://api.dhlglobalmail.com/v2');
		$this->scope                 = ArrayUtil::get($data['scope']);
		$this->authEndpoint          = ArrayUtil::get($data['authEndpoint'], 'https://api.dhlglobalmail.com/v2/auth/access_token');
		$this->storeAuthData         = ArrayUtil::get($data['storeAuthData'], true);
		$this->loggerPrefix          = ArrayUtil::get($data['loggerPrefix']);
		$this->storageAuthDataPrefix = ArrayUtil::get($data['storageAuthDataPrefix']);
	}

	public function getUsername()
	{
		return $this->username;
	}

	public function getPassword()
	{
		return $this->password;
	}

	public function setClientId($clientId)
	{
		$this->clientId = $clientId;
	}

	public function getClientId()
	{
		return $this->clientId;
	}

	public function getAccessToken()
	{
		return $this->accessToken;
	}

	public function setAccessToken($token)
	{
		$this->accessToken = $token;
	}

	public function getScope()
	{
		return $this->scope;
	}

	public function getEndpoint()
	{
		return $this->endpoint;
	}

	public function getAuthEndpoint()
	{
		return $this->authEndpoint;
	}

	public function setShouldStoreAuthData($bool)
	{
		$this->storeAuthData = $bool;
	}

	public function shouldStoreAuthData()
	{
		return $this->storeAuthData;
	}

	public function setLoggerPrefix($prefix)
	{
		$this->loggerPrefix = $prefix;
	}

	public function getLoggerPrefix()
	{
		return (null !== $this->loggerPrefix ? ('[' . $this->loggerPrefix . '] ') : '');
	}

	public function setStorageAuthDataPrefix($prefix)
	{
		$this->storageAuthDataPrefix = $prefix;
	}

	public function getStorageAuthDataPrefix()
	{
		return $this->storageAuthDataPrefix;
	}

	public function getStorageAuthDataFilename()
	{
		return ((null === $this->getStorageAuthDataPrefix() ? '' : ($this->getStorageAuthDataPrefix() . '-')) . 'authData.json');
	}

	public function setState($state)
	{
		$this->state = $state;
	}

	public function getState()
	{
		return $this->state;
	}
}