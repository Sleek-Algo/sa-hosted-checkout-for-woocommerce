<?php

namespace SAHCFWC\Libraries\Stripe\Util;

class EventTypes
{
    const v2EventMapping = [
        // The beginning of the section generated from our OpenAPI spec
        \SAHCFWC\Libraries\Stripe\Events\V1BillingMeterErrorReportTriggeredEvent::LOOKUP_TYPE => \SAHCFWC\Libraries\Stripe\Events\V1BillingMeterErrorReportTriggeredEvent::class,
        \SAHCFWC\Libraries\Stripe\Events\V1BillingMeterNoMeterFoundEvent::LOOKUP_TYPE => \SAHCFWC\Libraries\Stripe\Events\V1BillingMeterNoMeterFoundEvent::class,
        \SAHCFWC\Libraries\Stripe\Events\V2CoreEventDestinationPingEvent::LOOKUP_TYPE => \SAHCFWC\Libraries\Stripe\Events\V2CoreEventDestinationPingEvent::class,
        // The end of the section generated from our OpenAPI spec
    ];
}
