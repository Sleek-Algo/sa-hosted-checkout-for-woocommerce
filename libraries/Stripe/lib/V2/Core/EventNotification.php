<?php

namespace SAHCFWC\Libraries\Stripe\V2\Core;

use SAHCFWC\Libraries\Stripe\Events\UnknownEventNotification;
use SAHCFWC\Libraries\Stripe\Reason;
use SAHCFWC\Libraries\Stripe\RelatedObject;
use SAHCFWC\Libraries\Stripe\Util\EventNotificationTypes;

/**
 * EventNotification represents the json that's delivered from an Event Destination.
 * It's a basic class with no additional methods or properties. Use it to check basic information about a delivered event.
 * If you want more details, use `$stripeClient->v2->core->events->retrieve(thin_event.id)` to fetch the full event object.
 *
 * @property string             $id       Unique identifier for the event.
 * @property string             $type     The type of the event.
 * @property string             $created  Time at which the object was created.
 * @property null|\SAHCFWC\Libraries\Stripe\StripeContext        $context  Authentication context needed to fetch the event or related object.
 * @property null|Reason $reason Reason for the event.
 * @property bool $livemode Livemode indicates if the event is from a production(true) or test(false) account.
 */
abstract class EventNotification
{
    public $id;
    public $type;
    public $created;
    public $context;
    public $reason;
    public $livemode;

    protected $client;
    // only available on children
    protected $related_object;

    /**
     * @param array $json the raw json body
     * @param \SAHCFWC\Libraries\Stripe\StripeClient $client a StripeClient instance that this can use to make requests
     */
    public function __construct($json, $client)
    {
        $this->client = $client;

        if (\array_key_exists('id', $json)) {
            $this->id = $json['id'];
        }
        if (\array_key_exists('type', $json)) {
            $this->type = $json['type'];
        }
        if (\array_key_exists('created', $json)) {
            $this->created = $json['created'];
        }
        if (\array_key_exists('context', $json) && null !== $json['context']) {
            $this->context = \SAHCFWC\Libraries\Stripe\StripeContext::parse($json['context']);
        }
        if (\array_key_exists('livemode', $json)) {
            $this->livemode = $json['livemode'];
        }
        if (\array_key_exists('related_object', $json)) {
            $this->related_object = new RelatedObject($json['related_object']);
        }
        if (\array_key_exists('reason', $json)) {
            $this->reason = new Reason($json['reason']);
        }
    }

    /**
     * Helper for constructing an Event Notification. Doesn't perform signature validation, so you
     * should use \SAHCFWC\Libraries\Stripe\BaseStripeClient#parseEventNotification instead for
     * initial handling. This is useful in unit tests and working with EventNotifications that you've
     * already validated the authenticity of.
     *
     * @param string $jsonStr the raw json payload
     * @param \SAHCFWC\Libraries\Stripe\StripeClient $client a StripeClient instance that this can use to make requests
     *
     * @return EventNotification
     */
    public static function fromJson($jsonStr, $client)
    {
        $json = json_decode($jsonStr, true);

        $class = UnknownEventNotification::class;
        $eventNotificationTypes = EventNotificationTypes::v2EventMapping;
        if (\array_key_exists($json['type'], $eventNotificationTypes)) {
            $class = $eventNotificationTypes[$json['type']];
        }

        return new $class($json, $client);
    }

    /**
     * Retrieve the full Event from the SAHCFWC\Libraries\Stripe API.
     *
     * @return Event
     */
    public function fetchEvent()
    {
        $response = $this->client->rawRequest(
            'get',
            "/v2/core/events/{$this->id}",
            null,
            ['stripe_context' => $this->context],
            null,
            ['fetch_event']
        );

        return $this->client->deserialize($response->body, 'v2');
    }

    protected function fetchRelatedObject()
    {
        if (null === $this->related_object) {
            return null;
        }

        $options = [];
        if (null !== $this->context) {
            $options['stripe_context'] = $this->context;
        }

        $response = $this->client->rawRequest(
            'get',
            $this->related_object->url,
            null,
            $options,
            null,
            ['fetch_related_object']
        );

        return $this->client->deserialize($response->body, \SAHCFWC\Libraries\Stripe\Util\Util::getApiMode($this->related_object->url));
    }
}
