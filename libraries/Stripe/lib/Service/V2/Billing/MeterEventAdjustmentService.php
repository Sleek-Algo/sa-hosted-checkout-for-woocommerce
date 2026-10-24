<?php

// File generated from our OpenAPI spec

namespace SAHCFWC\Libraries\Stripe\Service\V2\Billing;

/**
 * @phpstan-import-type RequestOptionsArray from \SAHCFWC\Libraries\Stripe\Util\RequestOptions
 *
 * @psalm-import-type RequestOptionsArray from \SAHCFWC\Libraries\Stripe\Util\RequestOptions
 */
class MeterEventAdjustmentService extends \SAHCFWC\Libraries\Stripe\Service\AbstractService
{
    /**
     * Creates a meter event adjustment to cancel a previously sent meter event.
     *
     * @param null|array{cancel: array{identifier: string}, event_name: string, type: string} $params
     * @param null|RequestOptionsArray|\SAHCFWC\Libraries\Stripe\Util\RequestOptions $opts
     *
     * @return \SAHCFWC\Libraries\Stripe\V2\Billing\MeterEventAdjustment
     *
     * @throws \SAHCFWC\Libraries\Stripe\Exception\ApiErrorException if the request fails
     */
    public function create($params = null, $opts = null)
    {
        return $this->request('post', '/v2/billing/meter_event_adjustments', $params, $opts);
    }
}
