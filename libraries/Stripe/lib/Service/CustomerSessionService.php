<?php

// File generated from our OpenAPI spec

namespace SAHCFWC\Libraries\Stripe\Service;

/**
 * @phpstan-import-type RequestOptionsArray from \SAHCFWC\Libraries\Stripe\Util\RequestOptions
 * @psalm-import-type RequestOptionsArray from \SAHCFWC\Libraries\Stripe\Util\RequestOptions
 */
class CustomerSessionService extends \SAHCFWC\Libraries\Stripe\Service\AbstractService
{
    /**
     * Creates a Customer Session object that includes a single-use client secret that
     * you can use on your front-end to grant client-side API access for certain
     * customer resources.
     *
     * @param null|array $params
     * @param null|RequestOptionsArray|\SAHCFWC\Libraries\Stripe\Util\RequestOptions $opts
     *
     * @throws \SAHCFWC\Libraries\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \SAHCFWC\Libraries\Stripe\CustomerSession
     */
    public function create($params = null, $opts = null)
    {
        return $this->request('post', '/v1/customer_sessions', $params, $opts);
    }
}
