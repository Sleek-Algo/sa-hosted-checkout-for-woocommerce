<?php

// File generated from our OpenAPI spec

namespace SAHCFWC\Libraries\Stripe\Terminal;

/**
 * A Configurations object represents how features should be configured for terminal readers.
 *
 * @property string $id Unique identifier for the object.
 * @property string $object String representing the object's type. Objects of the same type share the same value.
 * @property null|\SAHCFWC\Libraries\Stripe\StripeObject $bbpos_wisepos_e
 * @property null|bool $is_account_default Whether this Configuration is the default for your account
 * @property bool $livemode Has the value <code>true</code> if the object exists in live mode or the value <code>false</code> if the object exists in test mode.
 * @property null|string $name String indicating the name of the Configuration object, set by the user
 * @property null|\SAHCFWC\Libraries\Stripe\StripeObject $offline
 * @property null|\SAHCFWC\Libraries\Stripe\StripeObject $reboot_window
 * @property null|\SAHCFWC\Libraries\Stripe\StripeObject $stripe_s700
 * @property null|\SAHCFWC\Libraries\Stripe\StripeObject $tipping
 * @property null|\SAHCFWC\Libraries\Stripe\StripeObject $verifone_p400
 */
class Configuration extends \SAHCFWC\Libraries\Stripe\ApiResource
{
    const OBJECT_NAME = 'terminal.configuration';

    use \SAHCFWC\Libraries\Stripe\ApiOperations\Update;

    /**
     * Creates a new <code>Configuration</code> object.
     *
     * @param null|array $params
     * @param null|array|string $options
     *
     * @throws \SAHCFWC\Libraries\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \SAHCFWC\Libraries\Stripe\Terminal\Configuration the created resource
     */
    public static function create($params = null, $options = null)
    {
        self::_validateParams($params);
        $url = static::classUrl();

        list($response, $opts) = static::_staticRequest('post', $url, $params, $options);
        $obj = \SAHCFWC\Libraries\Stripe\Util\Util::convertToStripeObject($response->json, $opts);
        $obj->setLastResponse($response);

        return $obj;
    }

    /**
     * Deletes a <code>Configuration</code> object.
     *
     * @param null|array $params
     * @param null|array|string $opts
     *
     * @throws \SAHCFWC\Libraries\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \SAHCFWC\Libraries\Stripe\Terminal\Configuration the deleted resource
     */
    public function delete($params = null, $opts = null)
    {
        self::_validateParams($params);

        $url = $this->instanceUrl();
        list($response, $opts) = $this->_request('delete', $url, $params, $opts);
        $this->refreshFrom($response, $opts);

        return $this;
    }

    /**
     * Returns a list of <code>Configuration</code> objects.
     *
     * @param null|array $params
     * @param null|array|string $opts
     *
     * @throws \SAHCFWC\Libraries\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \SAHCFWC\Libraries\Stripe\Collection<\SAHCFWC\Libraries\Stripe\Terminal\Configuration> of ApiResources
     */
    public static function all($params = null, $opts = null)
    {
        $url = static::classUrl();

        return static::_requestPage($url, \SAHCFWC\Libraries\Stripe\Collection::class, $params, $opts);
    }

    /**
     * Retrieves a <code>Configuration</code> object.
     *
     * @param array|string $id the ID of the API resource to retrieve, or an options array containing an `id` key
     * @param null|array|string $opts
     *
     * @throws \SAHCFWC\Libraries\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \SAHCFWC\Libraries\Stripe\Terminal\Configuration
     */
    public static function retrieve($id, $opts = null)
    {
        $opts = \SAHCFWC\Libraries\Stripe\Util\RequestOptions::parse($opts);
        $instance = new static($id, $opts);
        $instance->refresh();

        return $instance;
    }

    /**
     * Updates a new <code>Configuration</code> object.
     *
     * @param string $id the ID of the resource to update
     * @param null|array $params
     * @param null|array|string $opts
     *
     * @throws \SAHCFWC\Libraries\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \SAHCFWC\Libraries\Stripe\Terminal\Configuration the updated resource
     */
    public static function update($id, $params = null, $opts = null)
    {
        self::_validateParams($params);
        $url = static::resourceUrl($id);

        list($response, $opts) = static::_staticRequest('post', $url, $params, $opts);
        $obj = \SAHCFWC\Libraries\Stripe\Util\Util::convertToStripeObject($response->json, $opts);
        $obj->setLastResponse($response);

        return $obj;
    }
}
