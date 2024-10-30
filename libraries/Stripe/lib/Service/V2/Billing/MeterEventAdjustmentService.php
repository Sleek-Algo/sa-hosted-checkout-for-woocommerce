<?php

// File generated from our OpenAPI spec

namespace SAHCFWC\Libraries\Stripe\Service\V2\Billing;

/**
 * @phpstan-import-type RequestOptionsArray from \SAHCFWC\Libraries\Stripe\Util\RequestOptions
 * @psalm-import-type RequestOptionsArray from \SAHCFWC\Libraries\Stripe\Util\RequestOptions
 */
class MeterEventAdjustmentService extends \SAHCFWC\Libraries\Stripe\Service\AbstractService
{
    /**
     * Creates a meter event adjustment to cancel a previously sent meter event.
     *
     * @param null|array $params
     * @param null|RequestOptionsArray|\SAHCFWC\Libraries\Stripe\Util\RequestOptions $opts
     *
     * @throws \SAHCFWC\Libraries\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \SAHCFWC\Libraries\Stripe\V2\Billing\MeterEventAdjustment
     */
    public function create($params = null, $opts = null)
    {
        return $this->request('post', '/v2/billing/meter_event_adjustments', $params, $opts);
    }
}
