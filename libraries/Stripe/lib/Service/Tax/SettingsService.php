<?php

// File generated from our OpenAPI spec

namespace SAHCFWC\Libraries\Stripe\Service\Tax;

/**
 * @phpstan-import-type RequestOptionsArray from \SAHCFWC\Libraries\Stripe\Util\RequestOptions
 *
 * @psalm-import-type RequestOptionsArray from \SAHCFWC\Libraries\Stripe\Util\RequestOptions
 */
class SettingsService extends \SAHCFWC\Libraries\Stripe\Service\AbstractService
{
    /**
     * Retrieves Tax <code>Settings</code> for a merchant.
     *
     * @param null|array{expand?: string[]} $params
     * @param null|RequestOptionsArray|\SAHCFWC\Libraries\Stripe\Util\RequestOptions $opts
     *
     * @return \SAHCFWC\Libraries\Stripe\Tax\Settings
     *
     * @throws \SAHCFWC\Libraries\Stripe\Exception\ApiErrorException if the request fails
     */
    public function retrieve($params = null, $opts = null)
    {
        return $this->request('get', '/v1/tax/settings', $params, $opts);
    }

    /**
     * Updates Tax <code>Settings</code> parameters used in tax calculations. All
     * parameters are editable but none can be removed once set.
     *
     * @param null|array{defaults?: array{tax_behavior?: string, tax_code?: string}, expand?: string[], head_office?: array{address: array{city?: string, country?: string, line1?: string, line2?: string, postal_code?: string, state?: string}}} $params
     * @param null|RequestOptionsArray|\SAHCFWC\Libraries\Stripe\Util\RequestOptions $opts
     *
     * @return \SAHCFWC\Libraries\Stripe\Tax\Settings
     *
     * @throws \SAHCFWC\Libraries\Stripe\Exception\ApiErrorException if the request fails
     */
    public function update($params = null, $opts = null)
    {
        return $this->request('post', '/v1/tax/settings', $params, $opts);
    }
}
