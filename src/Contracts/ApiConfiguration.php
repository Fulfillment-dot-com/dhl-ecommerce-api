<?php


namespace Fulfillment\DHL\Api\Contracts;


interface ApiConfiguration
{
	public function getUsername();

	public function getPassword();

	public function getClientId();

	public function setClientId($clientId);

	public function getAccessToken();

	public function setAccessToken($token);

	public function getScope();

	public function setState($state);

	public function getState();

	public function getEndpoint();

	public function getAuthEndpoint();

	public function setShouldStoreAuthData($token);

	public function shouldStoreAuthData();

	public function setLoggerPrefix($prefix);

	public function getLoggerPrefix();

	public function setStorageAuthDataPrefix($prefix);

	public function getStorageAuthDataPrefix();

	public function getStorageAuthDataFilename();
}