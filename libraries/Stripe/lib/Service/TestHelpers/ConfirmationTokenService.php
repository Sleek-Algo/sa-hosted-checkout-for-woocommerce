<?php

// File generated from our OpenAPI spec

namespace SAHCFWC\Libraries\Stripe\Service\TestHelpers;

/**
 * @phpstan-import-type RequestOptionsArray from \SAHCFWC\Libraries\Stripe\Util\RequestOptions
 * @psalm-import-type RequestOptionsArray from \SAHCFWC\Libraries\Stripe\Util\RequestOptions
 */
class ConfirmationTokenService extends \SAHCFWC\Libraries\Stripe\Service\AbstractService
{
    /**
     * Creates a test mode Confirmation Token server side for your integration tests.
     *
     * @param null|array $params
     * @param null|RequestOptionsArray|\SAHCFWC\Libraries\Stripe\Util\RequestOptions $opts
     *
     * @throws \SAHCFWC\Libraries\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \SAHCFWC\Libraries\Stripe\ConfirmationToken
     */
    public function create($params = null, $opts = null)
    {
        return $this->request('post', '/v1/test_helpers/confirmation_tokens', $params, $opts);
    }
}
