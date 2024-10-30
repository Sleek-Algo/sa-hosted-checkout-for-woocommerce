<?php

// File generated from our OpenAPI spec

namespace SAHCFWC\Libraries\Stripe\Service\Forwarding;

/**
 * @phpstan-import-type RequestOptionsArray from \SAHCFWC\Libraries\Stripe\Util\RequestOptions
 * @psalm-import-type RequestOptionsArray from \SAHCFWC\Libraries\Stripe\Util\RequestOptions
 */
class RequestService extends \SAHCFWC\Libraries\Stripe\Service\AbstractService
{
    /**
     * Lists all ForwardingRequest objects.
     *
     * @param null|array $params
     * @param null|RequestOptionsArray|\SAHCFWC\Libraries\Stripe\Util\RequestOptions $opts
     *
     * @throws \SAHCFWC\Libraries\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \SAHCFWC\Libraries\Stripe\Collection<\SAHCFWC\Libraries\Stripe\Forwarding\Request>
     */
    public function all($params = null, $opts = null)
    {
        return $this->requestCollection('get', '/v1/forwarding/requests', $params, $opts);
    }

    /**
     * Creates a ForwardingRequest object.
     *
     * @param null|array $params
     * @param null|RequestOptionsArray|\SAHCFWC\Libraries\Stripe\Util\RequestOptions $opts
     *
     * @throws \SAHCFWC\Libraries\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \SAHCFWC\Libraries\Stripe\Forwarding\Request
     */
    public function create($params = null, $opts = null)
    {
        return $this->request('post', '/v1/forwarding/requests', $params, $opts);
    }

    /**
     * Retrieves a ForwardingRequest object.
     *
     * @param string $id
     * @param null|array $params
     * @param null|RequestOptionsArray|\SAHCFWC\Libraries\Stripe\Util\RequestOptions $opts
     *
     * @throws \SAHCFWC\Libraries\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \SAHCFWC\Libraries\Stripe\Forwarding\Request
     */
    public function retrieve($id, $params = null, $opts = null)
    {
        return $this->request('get', $this->buildPath('/v1/forwarding/requests/%s', $id), $params, $opts);
    }
}
