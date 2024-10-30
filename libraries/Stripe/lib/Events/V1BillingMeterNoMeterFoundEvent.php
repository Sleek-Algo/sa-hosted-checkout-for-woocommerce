<?php

// File generated from our OpenAPI spec

namespace SAHCFWC\Libraries\Stripe\Events;

/**
 * @property \SAHCFWC\Libraries\Stripe\EventData\V1BillingMeterNoMeterFoundEventData $data data associated with the event
 */
class V1BillingMeterNoMeterFoundEvent extends \SAHCFWC\Libraries\Stripe\V2\Event
{
    const LOOKUP_TYPE = 'v1.billing.meter.no_meter_found';

    public static function constructFrom($values, $opts = null, $apiMode = 'v2')
    {
        $evt = parent::constructFrom($values, $opts, $apiMode);
        if (null !== $evt->data) {
            $evt->data = \SAHCFWC\Libraries\Stripe\EventData\V1BillingMeterNoMeterFoundEventData::constructFrom($evt->data, $opts, $apiMode);
        }

        return $evt;
    }
}
