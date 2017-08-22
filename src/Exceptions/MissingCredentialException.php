<?php
/**
 * Created by IntelliJ IDEA.
 * User: mduncan
 * Date: 10/21/15
 * Time: 10:27 AM
 */

namespace Fulfillment\DHL\Api\Exceptions;


class MissingCredentialException extends \Exception
{
 public function __construct($missingCredential = null, $advice = null, \Exception $previous = null, $code = 0){
     $message = 'You are missing a credential necessary to retrieve an access token' . (null !== $missingCredential ? ': ' . $missingCredential . '.' : '' . '.');
     if(null !== $advice){
         $message .= ' ' . $advice;
     } else {
         $message .= ' Please check your configuration or environmental variables.';
     }
     parent::__construct($message, $code, $previous);
 }
}