<?php

namespace SAHCFWC\Libraries\Stripe;

/**
 * Interface for a SAHCFWC\Libraries\Stripe client.
 */
interface StripeClientInterface extends BaseStripeClientInterface
{
    /**
     * Sends a request to Stripe's API.
     *
     * @param 'delete'|'get'|'post' $method the HTTP method
     * @param string $path the path of the request
     * @param array $params the parameters of the request
     * @param array|\SAHCFWC\Libraries\Stripe\Util\RequestOptions $opts the special modifiers of the request
     *
     * @return \SAHCFWC\Libraries\Stripe\StripeObject the object returned by Stripe's API
     */
    public function request($method, $path, $params, $opts);
}
