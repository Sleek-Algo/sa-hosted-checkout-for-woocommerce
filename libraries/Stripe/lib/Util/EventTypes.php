<?php

namespace SAHCFWC\Libraries\Stripe\Util;

class EventTypes
{
    const thinEventMapping = [
        // The beginning of the section generated from our OpenAPI spec
        \SAHCFWC\Libraries\Stripe\Events\V1BillingMeterErrorReportTriggeredEvent::LOOKUP_TYPE => \SAHCFWC\Libraries\Stripe\Events\V1BillingMeterErrorReportTriggeredEvent::class,
        \SAHCFWC\Libraries\Stripe\Events\V1BillingMeterNoMeterFoundEvent::LOOKUP_TYPE => \SAHCFWC\Libraries\Stripe\Events\V1BillingMeterNoMeterFoundEvent::class,
        // The end of the section generated from our OpenAPI spec
    ];
}
