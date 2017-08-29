<?php
/**
 * Created by IntelliJ IDEA.
 * User: mduncan
 * Date: 9/29/15
 * Time: 12:49 PM
 */

namespace Fulfillment\DHL\Api\Utilities;


use GuzzleHttp\Exception\RequestException;

class RequestParser
{
	/**
	 * Returns an object or array of the error parsed from the Guzzle Request exception
	 *
	 * @param RequestException $requestException
	 * @param bool             $isAssoc
	 *
	 * @return mixed
	 */
	public static function parseError(RequestException $requestException, $isAssoc = true)
	{

		$response = json_decode($requestException->getResponse()->getBody(), $isAssoc);

		if (null !== $response)
		{
			return self::searchErrors($response);
		}

		return $requestException->getMessage();
	}

	public static function getErrorCode(RequestException $requestException)
	{
		$error = $error = json_decode($requestException->getResponse()->getBody());

		if (null !== $error && isset($error->meta->error))
		{
			return $error->meta->error[0]->error_type;
		}

		return null;
	}

	/**
	 * @param $array
	 * @param $path
	 *
	 * @return array
	 */
	protected static function searchErrors(array $array, array $path = [])
	{
		$errors = [];
		foreach ($array as $key => $val)
		{
			if(is_array($val)) {
				$nextLevelPath   = $path;
				$nextLevelPath[] = $key;
				if($key === 'errors') {
					foreach ($val as $error)
					{
						$errors[] = array_merge($error, ['path' => implode('/', $nextLevelPath)]);
					}
				} else {
					$nextLevelErrors = self::searchErrors($val, $nextLevelPath);
					if (count($nextLevelErrors) > 0)
					{
						$errors = array_merge($errors, $nextLevelErrors);
					}
				}
			}
		}
		return $errors;
	}
}