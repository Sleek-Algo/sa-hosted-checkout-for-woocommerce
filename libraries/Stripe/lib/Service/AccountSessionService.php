<?php

// File generated from our OpenAPI spec

namespace SAHCFWC\Libraries\Stripe\Service;

/**
 * @phpstan-import-type RequestOptionsArray from \SAHCFWC\Libraries\Stripe\Util\RequestOptions
 * @psalm-import-type RequestOptionsArray from \SAHCFWC\Libraries\Stripe\Util\RequestOptions
 */
class AccountSessionService extends \SAHCFWC\Libraries\Stripe\Service\AbstractService
{
    /**
     * Creates a AccountSession object that includes a single-use token that the
     * platform can use on their front-end to grant client-side API access.
     *
     * @param null|array $params
     * @param null|RequestOptionsArray|\SAHCFWC\Libraries\Stripe\Util\RequestOptions $opts
     *
     * @throws \SAHCFWC\Libraries\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \SAHCFWC\Libraries\Stripe\AccountSession
     */
    public function create($params = null, $opts = null)
    {
        return $this->request('post', '/v1/account_sessions', $params, $opts);
    }
}
