<?php

// File generated from our OpenAPI spec

namespace SAHCFWC\Libraries\Stripe\Treasury;

/**
 * TransactionEntries represent individual units of money movements within a single <a href="https://stripe.com/docs/api#transactions">Transaction</a>.
 *
 * @property string $id Unique identifier for the object.
 * @property string $object String representing the object's type. Objects of the same type share the same value.
 * @property \SAHCFWC\Libraries\Stripe\StripeObject $balance_impact Change to a FinancialAccount's balance
 * @property int $created Time at which the object was created. Measured in seconds since the Unix epoch.
 * @property string $currency Three-letter <a href="https://www.iso.org/iso-4217-currency-codes.html">ISO currency code</a>, in lowercase. Must be a <a href="https://stripe.com/docs/currencies">supported currency</a>.
 * @property int $effective_at When the TransactionEntry will impact the FinancialAccount's balance.
 * @property string $financial_account The FinancialAccount associated with this object.
 * @property null|string $flow Token of the flow associated with the TransactionEntry.
 * @property null|\SAHCFWC\Libraries\Stripe\StripeObject $flow_details Details of the flow associated with the TransactionEntry.
 * @property string $flow_type Type of the flow associated with the TransactionEntry.
 * @property bool $livemode Has the value <code>true</code> if the object exists in live mode or the value <code>false</code> if the object exists in test mode.
 * @property string|\SAHCFWC\Libraries\Stripe\Treasury\Transaction $transaction The Transaction associated with this object.
 * @property string $type The specific money movement that generated the TransactionEntry.
 */
class TransactionEntry extends \SAHCFWC\Libraries\Stripe\ApiResource
{
    const OBJECT_NAME = 'treasury.transaction_entry';

    const FLOW_TYPE_CREDIT_REVERSAL = 'credit_reversal';
    const FLOW_TYPE_DEBIT_REVERSAL = 'debit_reversal';
    const FLOW_TYPE_INBOUND_TRANSFER = 'inbound_transfer';
    const FLOW_TYPE_ISSUING_AUTHORIZATION = 'issuing_authorization';
    const FLOW_TYPE_OTHER = 'other';
    const FLOW_TYPE_OUTBOUND_PAYMENT = 'outbound_payment';
    const FLOW_TYPE_OUTBOUND_TRANSFER = 'outbound_transfer';
    const FLOW_TYPE_RECEIVED_CREDIT = 'received_credit';
    const FLOW_TYPE_RECEIVED_DEBIT = 'received_debit';

    const TYPE_CREDIT_REVERSAL = 'credit_reversal';
    const TYPE_CREDIT_REVERSAL_POSTING = 'credit_reversal_posting';
    const TYPE_DEBIT_REVERSAL = 'debit_reversal';
    const TYPE_INBOUND_TRANSFER = 'inbound_transfer';
    const TYPE_INBOUND_TRANSFER_RETURN = 'inbound_transfer_return';
    const TYPE_ISSUING_AUTHORIZATION_HOLD = 'issuing_authorization_hold';
    const TYPE_ISSUING_AUTHORIZATION_RELEASE = 'issuing_authorization_release';
    const TYPE_OTHER = 'other';
    const TYPE_OUTBOUND_PAYMENT = 'outbound_payment';
    const TYPE_OUTBOUND_PAYMENT_CANCELLATION = 'outbound_payment_cancellation';
    const TYPE_OUTBOUND_PAYMENT_FAILURE = 'outbound_payment_failure';
    const TYPE_OUTBOUND_PAYMENT_POSTING = 'outbound_payment_posting';
    const TYPE_OUTBOUND_PAYMENT_RETURN = 'outbound_payment_return';
    const TYPE_OUTBOUND_TRANSFER = 'outbound_transfer';
    const TYPE_OUTBOUND_TRANSFER_CANCELLATION = 'outbound_transfer_cancellation';
    const TYPE_OUTBOUND_TRANSFER_FAILURE = 'outbound_transfer_failure';
    const TYPE_OUTBOUND_TRANSFER_POSTING = 'outbound_transfer_posting';
    const TYPE_OUTBOUND_TRANSFER_RETURN = 'outbound_transfer_return';
    const TYPE_RECEIVED_CREDIT = 'received_credit';
    const TYPE_RECEIVED_DEBIT = 'received_debit';

    /**
     * Retrieves a list of TransactionEntry objects.
     *
     * @param null|array $params
     * @param null|array|string $opts
     *
     * @throws \SAHCFWC\Libraries\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \SAHCFWC\Libraries\Stripe\Collection<\SAHCFWC\Libraries\Stripe\Treasury\TransactionEntry> of ApiResources
     */
    public static function all($params = null, $opts = null)
    {
        $url = static::classUrl();

        return static::_requestPage($url, \SAHCFWC\Libraries\Stripe\Collection::class, $params, $opts);
    }

    /**
     * Retrieves a TransactionEntry object.
     *
     * @param array|string $id the ID of the API resource to retrieve, or an options array containing an `id` key
     * @param null|array|string $opts
     *
     * @throws \SAHCFWC\Libraries\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \SAHCFWC\Libraries\Stripe\Treasury\TransactionEntry
     */
    public static function retrieve($id, $opts = null)
    {
        $opts = \SAHCFWC\Libraries\Stripe\Util\RequestOptions::parse($opts);
        $instance = new static($id, $opts);
        $instance->refresh();

        return $instance;
    }
}