<?php

// File generated from our OpenAPI spec

namespace SAHCFWC\Libraries\Stripe\Service\V2\Core;

/**
 * @phpstan-import-type RequestOptionsArray from \SAHCFWC\Libraries\Stripe\Util\RequestOptions
 *
 * @psalm-import-type RequestOptionsArray from \SAHCFWC\Libraries\Stripe\Util\RequestOptions
 */
class EventService extends \SAHCFWC\Libraries\Stripe\Service\AbstractService
{
    /**
     * List events, going back up to 30 days.
     *
     * @param null|array{limit?: int, object_id: string} $params
     * @param null|RequestOptionsArray|\SAHCFWC\Libraries\Stripe\Util\RequestOptions $opts
     *
     * @return \SAHCFWC\Libraries\Stripe\V2\Collection<\SAHCFWC\Libraries\Stripe\V2\Core\Event>
     *
     * @throws \SAHCFWC\Libraries\Stripe\Exception\ApiErrorException if the request fails
     */
    public function all($params = null, $opts = null)
    {
        return $this->requestCollection('get', '/v2/core/events', $params, $opts);
    }

    /**
     * Retrieves the details of an event.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\SAHCFWC\Libraries\Stripe\Util\RequestOptions $opts
     *
     * @return \SAHCFWC\Libraries\Stripe\V2\Core\Event
     *
     * @throws \SAHCFWC\Libraries\Stripe\Exception\ApiErrorException if the request fails
     */
    public function retrieve($id, $params = null, $opts = null)
    {
        return $this->request('get', $this->buildPath('/v2/core/events/%s', $id), $params, $opts);
    }
}
