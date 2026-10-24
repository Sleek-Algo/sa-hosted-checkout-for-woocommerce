<?php

namespace SAHCFWC\Libraries\Stripe\Util;

class EventNotificationTypes
{
    const v2EventMapping = [
        // The beginning of the section generated from our OpenAPI spec
        \SAHCFWC\Libraries\Stripe\Events\V1BillingMeterErrorReportTriggeredEventNotification::LOOKUP_TYPE => \SAHCFWC\Libraries\Stripe\Events\V1BillingMeterErrorReportTriggeredEventNotification::class,
        \SAHCFWC\Libraries\Stripe\Events\V1BillingMeterNoMeterFoundEventNotification::LOOKUP_TYPE => \SAHCFWC\Libraries\Stripe\Events\V1BillingMeterNoMeterFoundEventNotification::class,
        \SAHCFWC\Libraries\Stripe\Events\V2CoreEventDestinationPingEventNotification::LOOKUP_TYPE => \SAHCFWC\Libraries\Stripe\Events\V2CoreEventDestinationPingEventNotification::class,
        // The end of the section generated from our OpenAPI spec
    ];
}
