<?php

// File generated from our OpenAPI spec

namespace SAHCFWC\Libraries\Stripe\Service\TestHelpers\Treasury;

/**
 * @phpstan-import-type RequestOptionsArray from \SAHCFWC\Libraries\Stripe\Util\RequestOptions
 * @psalm-import-type RequestOptionsArray from \SAHCFWC\Libraries\Stripe\Util\RequestOptions
 */
class ReceivedDebitService extends \SAHCFWC\Libraries\Stripe\Service\AbstractService
{
    /**
     * Use this endpoint to simulate a test mode ReceivedDebit initiated by a third
     * party. In live mode, you can’t directly create ReceivedDebits initiated by third
     * parties.
     *
     * @param null|array $params
     * @param null|RequestOptionsArray|\SAHCFWC\Libraries\Stripe\Util\RequestOptions $opts
     *
     * @throws \SAHCFWC\Libraries\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \SAHCFWC\Libraries\Stripe\Treasury\ReceivedDebit
     */
    public function create($params = null, $opts = null)
    {
        return $this->request('post', '/v1/test_helpers/treasury/received_debits', $params, $opts);
    }
}
