<?php

// File generated from our OpenAPI spec

namespace SAHCFWC\Libraries\Stripe\Service\Tax;

/**
 * @phpstan-import-type RequestOptionsArray from \SAHCFWC\Libraries\Stripe\Util\RequestOptions
 * @psalm-import-type RequestOptionsArray from \SAHCFWC\Libraries\Stripe\Util\RequestOptions
 */
class SettingsService extends \SAHCFWC\Libraries\Stripe\Service\AbstractService
{
    /**
     * Retrieves Tax <code>Settings</code> for a merchant.
     *
     * @param null|array $params
     * @param null|RequestOptionsArray|\SAHCFWC\Libraries\Stripe\Util\RequestOptions $opts
     *
     * @throws \SAHCFWC\Libraries\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \SAHCFWC\Libraries\Stripe\Tax\Settings
     */
    public function retrieve($params = null, $opts = null)
    {
        return $this->request('get', '/v1/tax/settings', $params, $opts);
    }

    /**
     * Updates Tax <code>Settings</code> parameters used in tax calculations. All
     * parameters are editable but none can be removed once set.
     *
     * @param null|array $params
     * @param null|RequestOptionsArray|\SAHCFWC\Libraries\Stripe\Util\RequestOptions $opts
     *
     * @throws \SAHCFWC\Libraries\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \SAHCFWC\Libraries\Stripe\Tax\Settings
     */
    public function update($params = null, $opts = null)
    {
        return $this->request('post', '/v1/tax/settings', $params, $opts);
    }
}
