<?php

namespace SAHCFWC\Libraries\Stripe\Exception\OAuth;

/**
 * Implements properties and methods common to all (non-SPL) SAHCFWC\Libraries\Stripe OAuth
 * exceptions.
 */
abstract class OAuthErrorException extends \SAHCFWC\Libraries\Stripe\Exception\ApiErrorException
{
    protected function constructErrorObject()
    {
        if (null === $this->jsonBody) {
            return null;
        }

        return \SAHCFWC\Libraries\Stripe\OAuthErrorObject::constructFrom($this->jsonBody);
    }
}
