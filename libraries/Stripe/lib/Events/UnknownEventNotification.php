<?php

namespace SAHCFWC\Libraries\Stripe\Events;

use SAHCFWC\Libraries\Stripe\V2\Core\EventNotification;

/**
 * A class representing an EventNotification that the SDK doesn't have types for.
 *
 * @property null|\SAHCFWC\Libraries\Stripe\RelatedObject $related_object Object containing the reference to API resource relevant to the event.
 */
class UnknownEventNotification extends EventNotification
{
    public $related_object;

    /**
     * Retrieve the event's related object from the SAHCFWC\Libraries\Stripe API, if one exists. Returns null otherwise.
     *
     * @return null|\SAHCFWC\Libraries\Stripe\StripeObject
     */
    public function fetchRelatedObject()
    {
        return parent::fetchRelatedObject();
    }
}
