<?php

// File generated from our OpenAPI spec

namespace SAHCFWC\Libraries\Stripe\Events;

/**
 * @property \SAHCFWC\Libraries\Stripe\RelatedObject $related_object Object containing the reference to API resource relevant to the event
 */
class V2CoreEventDestinationPingEvent extends \SAHCFWC\Libraries\Stripe\V2\Core\Event
{
    const LOOKUP_TYPE = 'v2.core.event_destination.ping';

    /**
     * Retrieves the related object from the API. Make an API request on every call.
     *
     * @return \SAHCFWC\Libraries\Stripe\V2\Core\EventDestination
     *
     * @throws \SAHCFWC\Libraries\Stripe\Exception\ApiErrorException if the request fails
     */
    public function fetchRelatedObject()
    {
        $apiMode = \SAHCFWC\Libraries\Stripe\Util\Util::getApiMode($this->related_object->url);
        list($object, $options) = $this->_request('get', $this->related_object->url, [], [
            'stripe_context' => $this->context,
        ], [], $apiMode);

        return \SAHCFWC\Libraries\Stripe\Util\Util::convertToStripeObject($object, $options, $apiMode);
    }
}
