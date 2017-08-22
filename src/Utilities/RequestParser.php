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
     * @param RequestException $requestException
     * @param bool             $isAssoc
     * @return string
     */
    public static function parseError(RequestException $requestException, $isAssoc = true)
    {

        $error = $error = json_decode($requestException->getResponse()->getBody(), $isAssoc);

        if (null !== $error) {
            return $error->meta->error;
        }
            return $requestException->getMessage();
    }

    public static function getErrorCode(RequestException $requestException)
    {
        $error = $error = json_decode($requestException->getResponse()->getBody());

        if (null !== $error && isset($error->meta->error)) {
            return $error->meta->error[0]->error_type;
        }
            return null;
    }
}