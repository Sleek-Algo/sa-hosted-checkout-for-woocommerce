<?php

// File generated from our OpenAPI spec

namespace SAHCFWC\Libraries\Stripe\Service\V2\Billing;

/**
 * @phpstan-import-type RequestOptionsArray from \SAHCFWC\Libraries\Stripe\Util\RequestOptions
 * @psalm-import-type RequestOptionsArray from \SAHCFWC\Libraries\Stripe\Util\RequestOptions
 */
class MeterEventStreamService extends \SAHCFWC\Libraries\Stripe\Service\AbstractService
{
    /**
     * Creates meter events. Events are processed asynchronously, including validation.
     * Requires a meter event session for authentication. Supports up to 10,000
     * requests per second in livemode. For even higher rate-limits, contact sales.
     *
     * @param null|array $params
     * @param null|RequestOptionsArray|\SAHCFWC\Libraries\Stripe\Util\RequestOptions $opts
     *
     * @throws \SAHCFWC\Libraries\Stripe\Exception\TemporarySessionExpiredException
     *
     * @return void
     */
    public function create($params = null, $opts = null)
    {
        $opts = \SAHCFWC\Libraries\Stripe\Util\RequestOptions::parse($opts);
        if (!isset($opts->apiBase)) {
            $opts->apiBase = $this->getClient()->getMeterEventsBase();
        }
        $this->request('post', '/v2/billing/meter_event_stream', $params, $opts);
    }
}
