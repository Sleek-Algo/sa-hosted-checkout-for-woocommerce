<?php

// File generated from our OpenAPI spec

namespace SAHCFWC\Libraries\Stripe\Billing;

/**
 * A credit balance transaction is a resource representing a transaction (either a credit or a debit) against an existing credit grant.
 *
 * @property string $id Unique identifier for the object.
 * @property string $object String representing the object's type. Objects of the same type share the same value.
 * @property int $created Time at which the object was created. Measured in seconds since the Unix epoch.
 * @property null|\SAHCFWC\Libraries\Stripe\StripeObject $credit Credit details for this credit balance transaction. Only present if type is <code>credit</code>.
 * @property string|\SAHCFWC\Libraries\Stripe\Billing\CreditGrant $credit_grant The credit grant associated with this credit balance transaction.
 * @property null|\SAHCFWC\Libraries\Stripe\StripeObject $debit Debit details for this credit balance transaction. Only present if type is <code>debit</code>.
 * @property int $effective_at The effective time of this credit balance transaction.
 * @property bool $livemode Has the value <code>true</code> if the object exists in live mode or the value <code>false</code> if the object exists in test mode.
 * @property null|string|\SAHCFWC\Libraries\Stripe\TestHelpers\TestClock $test_clock ID of the test clock this credit balance transaction belongs to.
 * @property null|string $type The type of credit balance transaction (credit or debit).
 */
class CreditBalanceTransaction extends \SAHCFWC\Libraries\Stripe\ApiResource
{
    const OBJECT_NAME = 'billing.credit_balance_transaction';

    const TYPE_CREDIT = 'credit';
    const TYPE_DEBIT = 'debit';

    /**
     * Retrieve a list of credit balance transactions.
     *
     * @param null|array $params
     * @param null|array|string $opts
     *
     * @throws \SAHCFWC\Libraries\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \SAHCFWC\Libraries\Stripe\Collection<\SAHCFWC\Libraries\Stripe\Billing\CreditBalanceTransaction> of ApiResources
     */
    public static function all($params = null, $opts = null)
    {
        $url = static::classUrl();

        return static::_requestPage($url, \SAHCFWC\Libraries\Stripe\Collection::class, $params, $opts);
    }

    /**
     * Retrieves a credit balance transaction.
     *
     * @param array|string $id the ID of the API resource to retrieve, or an options array containing an `id` key
     * @param null|array|string $opts
     *
     * @throws \SAHCFWC\Libraries\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \SAHCFWC\Libraries\Stripe\Billing\CreditBalanceTransaction
     */
    public static function retrieve($id, $opts = null)
    {
        $opts = \SAHCFWC\Libraries\Stripe\Util\RequestOptions::parse($opts);
        $instance = new static($id, $opts);
        $instance->refresh();

        return $instance;
    }
}
