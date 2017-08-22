<?php

namespace Fulfillment\DHL\Api\Http;

use Fulfillment\DHL\Api\Configuration\ApiConfiguration;
use Fulfillment\DHL\Api\Exceptions\MissingCredentialException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use League\CLImate\CLImate;

class Request
{
    protected $guzzle;
    protected $config;
    protected $climate;

    /**
     * @param Client           $guzzle
     * @param ApiConfiguration $config array
     * @param CLImate          $climate
     */
    public function __construct(Client $guzzle, ApiConfiguration $config, CLImate $climate)
    {
        $this->guzzle  = $guzzle;
        $this->config  = $config;
        $this->climate = $climate;
    }

    public function requestAccessToken()
    {

        try {
            $this->checkForCredentials();
        } catch (MissingCredentialException $e) {
            $this->climate->error($e->getMessage());
            throw $e;
        }

        $this->climate->info($this->config->getLoggerPrefix() . 'Requesting new access token...');

        $authEndPoint = $this->config->getAuthEndpoint();

        $this->climate->out($this->config->getLoggerPrefix() . 'URL: ' . $authEndPoint);

        try {
            $accessTokenResponse = $this->guzzle->get($authEndPoint, [
                'query' => [
                    'username' => $this->config->getUsername(),
                    'password' => $this->config->getPassword()
                ],
                'http_errors' => false
            ]
            );
        } catch (RequestException $e) {
            $this->climate->error($this->config->getLoggerPrefix() . 'Requesting access token has failed.');

            $this->printError($e);

            throw $e;
        }
        $accessTokenJson = json_decode($accessTokenResponse->getBody());

        $this->climate->info($this->config->getLoggerPrefix() . 'Got new access token!');
        $this->climate->info($this->config->getLoggerPrefix() . 'Token: ' . $accessTokenJson->data->access_token);

        return $accessTokenJson->data;


    }

	/**
	 * Make a request to the API using Guzzle
	 *
	 * @param      $method      string The HTTP VERB to use for this request
	 * @param      $url         string The relative URL after the hostname
	 * @param null $requestData  array The contents of the api body
	 * @param $queryString  array Data to add as a queryString to the url
	 *
	 * @return mixed
	 * @throws \Exception
	 */
    public function makeRequest($method, $url, $requestData = null, $queryString = [])
    {
        $urlEndPoint = $this->config->getEndpoint() . '/' . $url;

        //we want to see the url being called
        $this->climate->out($this->config->getLoggerPrefix() . 'URL: ' . $urlEndPoint);

        $authData = [
	        'client_id' => $this->config->getClientId(),
	        'access_token' => $this->config->getAccessToken()
        ];

        $data = [
        	'json' => $requestData
        ];

	    $data['query'] = array_merge($queryString, $authData);


        try {
            switch ($method) {
                case 'post':
                    $response = $this->guzzle->post($urlEndPoint, $data);
                    break;
                case 'put':
                    $response = $this->guzzle->put($urlEndPoint, $data);
                    break;
                case 'delete':
	                $response = $this->guzzle->delete($urlEndPoint, $data);
	                break;
	            case 'get':
		            $response = $this->guzzle->get($urlEndPoint, $data);
                    break;
                default:
                    throw new \Exception($this->config->getLoggerPrefix() . 'Missing request method!');

            }

            $this->climate->info($this->config->getLoggerPrefix() . 'Request successful.');

            if (in_array(current($response->getHeader('Content-Type')), ['image/png','image/jpg'])) {
                $result = $response->getBody()->getContents();
            } else {
                $result = json_decode($response->getBody());
                if(null === $result) {
                    // may not be json, return as string
                    $result = (string)$response->getBody();
                }
            }

            return $result;

        } catch (ConnectException $c) {
            $this->climate->error($this->config->getLoggerPrefix() . 'Error connecting to endpoint: ' . $c->getMessage());
            throw $c;
        } catch (RequestException $e) {
            $this->climate->error($this->config->getLoggerPrefix() . 'Request failed with status code ' . $e->getResponse()->getStatusCode());
            $this->printError($e);
            throw $e;
        }
    }

    private function printError(RequestException $requestException)
    {

        $error = $error = json_decode($requestException->getResponse()->getBody(), true);

        if (null !== $error && isset($error['error'])) {
            $this->climate->error($this->config->getLoggerPrefix() . '<bold>Error: </bold>' . $error['error']);
            $this->climate->error($this->config->getLoggerPrefix() . '<bold>Description: </bold> ' . $error['error_description']);
        } else {
            if (null !== $error && isset($error['message'])) {
                $this->climate->error($this->config->getLoggerPrefix() . '<bold>Error: </bold>' . $error['message']);
                if (isset($error['validationErrors'])) {
                    if (is_array($error['validationErrors'])) {
                        foreach ($error['validationErrors'] as $prop => $message) {
                            $this->climate->error($this->config->getLoggerPrefix() . '-- ' . $prop . ': ' . $message);
                        }
                    } else {
                        $this->climate->error($this->config->getLoggerPrefix() . '-- ' . $error['validationErrors']);
                    }
                }
            } else {
                $this->climate->error($this->config->getLoggerPrefix() . '<bold>Error: </bold>' . $requestException->getMessage());
            }
        }
    }

    private function checkForCredentials()
    {
        if (empty($this->config->getUsername())) {
            throw new MissingCredentialException($this->config->getLoggerPrefix() . 'username');
        } elseif (empty($this->config->getPassword())) {
            throw new MissingCredentialException($this->config->getLoggerPrefix() . 'password');
        } elseif( empty($this->config->getClientId())) {
	        throw new MissingCredentialException($this->config->getLoggerPrefix() . 'clientId');
        }
    }
}