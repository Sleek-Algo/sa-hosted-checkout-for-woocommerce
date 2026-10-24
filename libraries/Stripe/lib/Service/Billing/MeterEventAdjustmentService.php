<?php

// File generated from our OpenAPI spec

namespace SAHCFWC\Libraries\Stripe\Service\Billing;

/**
 * @phpstan-import-type RequestOptionsArray from \SAHCFWC\Libraries\Stripe\Util\RequestOptions
 *
 * @psalm-import-type RequestOptionsArray from \SAHCFWC\Libraries\Stripe\Util\RequestOptions
 */
class MeterEventAdjustmentService extends \SAHCFWC\Libraries\Stripe\Service\AbstractService
{
    /**
     * Creates a billing meter event adjustment.
     *
     * @param null|array{cancel?: array{identifier?: string}, event_name: string, expand?: string[], type: string} $params
     * @param null|RequestOptionsArray|\SAHCFWC\Libraries\Stripe\Util\RequestOptions $opts
     *
     * @return \SAHCFWC\Libraries\Stripe\Billing\MeterEventAdjustment
     *
     * @throws \SAHCFWC\Libraries\Stripe\Exception\ApiErrorException if the request fails
     */
    public function create($params = null, $opts = null)
    {
        return $this->request('post', '/v1/billing/meter_event_adjustments', $params, $opts);
    }
}
