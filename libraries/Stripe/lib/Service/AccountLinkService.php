<?php

// File generated from our OpenAPI spec

namespace SAHCFWC\Libraries\Stripe\Service;

/**
 * @phpstan-import-type RequestOptionsArray from \SAHCFWC\Libraries\Stripe\Util\RequestOptions
 *
 * @psalm-import-type RequestOptionsArray from \SAHCFWC\Libraries\Stripe\Util\RequestOptions
 */
class AccountLinkService extends AbstractService
{
    /**
     * Creates an AccountLink object that includes a single-use SAHCFWC\Libraries\Stripe URL that the
     * platform can redirect their user to in order to take them through the Connect
     * Onboarding flow.
     *
     * @param null|array{account: string, collect?: string, collection_options?: array{fields?: string, future_requirements?: string}, expand?: string[], refresh_url?: string, return_url?: string, type: string} $params
     * @param null|RequestOptionsArray|\SAHCFWC\Libraries\Stripe\Util\RequestOptions $opts
     *
     * @return \SAHCFWC\Libraries\Stripe\AccountLink
     *
     * @throws \SAHCFWC\Libraries\Stripe\Exception\ApiErrorException if the request fails
     */
    public function create($params = null, $opts = null)
    {
        return $this->request('post', '/v1/account_links', $params, $opts);
    }
}
