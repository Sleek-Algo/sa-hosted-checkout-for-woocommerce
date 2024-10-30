<?php

// File generated from our OpenAPI spec

namespace SAHCFWC\Libraries\Stripe;

/**
 * PaymentMethod objects represent your customer's payment instruments.
 * You can use them with <a href="https://stripe.com/docs/payments/payment-intents">PaymentIntents</a> to collect payments or save them to
 * Customer objects to store instrument details for future payments.
 *
 * Related guides: <a href="https://stripe.com/docs/payments/payment-methods">Payment Methods</a> and <a href="https://stripe.com/docs/payments/more-payment-scenarios">More Payment Scenarios</a>.
 *
 * @property string $id Unique identifier for the object.
 * @property string $object String representing the object's type. Objects of the same type share the same value.
 * @property null|\SAHCFWC\Libraries\Stripe\StripeObject $acss_debit
 * @property null|\SAHCFWC\Libraries\Stripe\StripeObject $affirm
 * @property null|\SAHCFWC\Libraries\Stripe\StripeObject $afterpay_clearpay
 * @property null|\SAHCFWC\Libraries\Stripe\StripeObject $alipay
 * @property null|string $allow_redisplay This field indicates whether this payment method can be shown again to its customer in a checkout flow. SAHCFWC\Libraries\Stripe products such as Checkout and Elements use this field to determine whether a payment method can be shown as a saved payment method in a checkout flow. The field defaults to “unspecified”.
 * @property null|\SAHCFWC\Libraries\Stripe\StripeObject $alma
 * @property null|\SAHCFWC\Libraries\Stripe\StripeObject $amazon_pay
 * @property null|\SAHCFWC\Libraries\Stripe\StripeObject $au_becs_debit
 * @property null|\SAHCFWC\Libraries\Stripe\StripeObject $bacs_debit
 * @property null|\SAHCFWC\Libraries\Stripe\StripeObject $bancontact
 * @property \SAHCFWC\Libraries\Stripe\StripeObject $billing_details
 * @property null|\SAHCFWC\Libraries\Stripe\StripeObject $blik
 * @property null|\SAHCFWC\Libraries\Stripe\StripeObject $boleto
 * @property null|\SAHCFWC\Libraries\Stripe\StripeObject $card
 * @property null|\SAHCFWC\Libraries\Stripe\StripeObject $card_present
 * @property null|\SAHCFWC\Libraries\Stripe\StripeObject $cashapp
 * @property int $created Time at which the object was created. Measured in seconds since the Unix epoch.
 * @property null|string|\SAHCFWC\Libraries\Stripe\Customer $customer The ID of the Customer to which this PaymentMethod is saved. This will not be set when the PaymentMethod has not been saved to a Customer.
 * @property null|\SAHCFWC\Libraries\Stripe\StripeObject $customer_balance
 * @property null|\SAHCFWC\Libraries\Stripe\StripeObject $eps
 * @property null|\SAHCFWC\Libraries\Stripe\StripeObject $fpx
 * @property null|\SAHCFWC\Libraries\Stripe\StripeObject $giropay
 * @property null|\SAHCFWC\Libraries\Stripe\StripeObject $grabpay
 * @property null|\SAHCFWC\Libraries\Stripe\StripeObject $ideal
 * @property null|\SAHCFWC\Libraries\Stripe\StripeObject $interac_present
 * @property null|\SAHCFWC\Libraries\Stripe\StripeObject $kakao_pay
 * @property null|\SAHCFWC\Libraries\Stripe\StripeObject $klarna
 * @property null|\SAHCFWC\Libraries\Stripe\StripeObject $konbini
 * @property null|\SAHCFWC\Libraries\Stripe\StripeObject $kr_card
 * @property null|\SAHCFWC\Libraries\Stripe\StripeObject $link
 * @property bool $livemode Has the value <code>true</code> if the object exists in live mode or the value <code>false</code> if the object exists in test mode.
 * @property null|\SAHCFWC\Libraries\Stripe\StripeObject $metadata Set of <a href="https://stripe.com/docs/api/metadata">key-value pairs</a> that you can attach to an object. This can be useful for storing additional information about the object in a structured format.
 * @property null|\SAHCFWC\Libraries\Stripe\StripeObject $mobilepay
 * @property null|\SAHCFWC\Libraries\Stripe\StripeObject $multibanco
 * @property null|\SAHCFWC\Libraries\Stripe\StripeObject $naver_pay
 * @property null|\SAHCFWC\Libraries\Stripe\StripeObject $oxxo
 * @property null|\SAHCFWC\Libraries\Stripe\StripeObject $p24
 * @property null|\SAHCFWC\Libraries\Stripe\StripeObject $payco
 * @property null|\SAHCFWC\Libraries\Stripe\StripeObject $paynow
 * @property null|\SAHCFWC\Libraries\Stripe\StripeObject $paypal
 * @property null|\SAHCFWC\Libraries\Stripe\StripeObject $pix
 * @property null|\SAHCFWC\Libraries\Stripe\StripeObject $promptpay
 * @property null|\SAHCFWC\Libraries\Stripe\StripeObject $radar_options Options to configure Radar. See <a href="https://stripe.com/docs/radar/radar-session">Radar Session</a> for more information.
 * @property null|\SAHCFWC\Libraries\Stripe\StripeObject $revolut_pay
 * @property null|\SAHCFWC\Libraries\Stripe\StripeObject $samsung_pay
 * @property null|\SAHCFWC\Libraries\Stripe\StripeObject $sepa_debit
 * @property null|\SAHCFWC\Libraries\Stripe\StripeObject $sofort
 * @property null|\SAHCFWC\Libraries\Stripe\StripeObject $swish
 * @property null|\SAHCFWC\Libraries\Stripe\StripeObject $twint
 * @property string $type The type of the PaymentMethod. An additional hash is included on the PaymentMethod with a name matching this value. It contains additional information specific to the PaymentMethod type.
 * @property null|\SAHCFWC\Libraries\Stripe\StripeObject $us_bank_account
 * @property null|\SAHCFWC\Libraries\Stripe\StripeObject $wechat_pay
 * @property null|\SAHCFWC\Libraries\Stripe\StripeObject $zip
 */
class PaymentMethod extends ApiResource
{
    const OBJECT_NAME = 'payment_method';

    use ApiOperations\Update;

    const ALLOW_REDISPLAY_ALWAYS = 'always';
    const ALLOW_REDISPLAY_LIMITED = 'limited';
    const ALLOW_REDISPLAY_UNSPECIFIED = 'unspecified';

    const TYPE_ACSS_DEBIT = 'acss_debit';
    const TYPE_AFFIRM = 'affirm';
    const TYPE_AFTERPAY_CLEARPAY = 'afterpay_clearpay';
    const TYPE_ALIPAY = 'alipay';
    const TYPE_ALMA = 'alma';
    const TYPE_AMAZON_PAY = 'amazon_pay';
    const TYPE_AU_BECS_DEBIT = 'au_becs_debit';
    const TYPE_BACS_DEBIT = 'bacs_debit';
    const TYPE_BANCONTACT = 'bancontact';
    const TYPE_BLIK = 'blik';
    const TYPE_BOLETO = 'boleto';
    const TYPE_CARD = 'card';
    const TYPE_CARD_PRESENT = 'card_present';
    const TYPE_CASHAPP = 'cashapp';
    const TYPE_CUSTOMER_BALANCE = 'customer_balance';
    const TYPE_EPS = 'eps';
    const TYPE_FPX = 'fpx';
    const TYPE_GIROPAY = 'giropay';
    const TYPE_GRABPAY = 'grabpay';
    const TYPE_IDEAL = 'ideal';
    const TYPE_INTERAC_PRESENT = 'interac_present';
    const TYPE_KAKAO_PAY = 'kakao_pay';
    const TYPE_KLARNA = 'klarna';
    const TYPE_KONBINI = 'konbini';
    const TYPE_KR_CARD = 'kr_card';
    const TYPE_LINK = 'link';
    const TYPE_MOBILEPAY = 'mobilepay';
    const TYPE_MULTIBANCO = 'multibanco';
    const TYPE_NAVER_PAY = 'naver_pay';
    const TYPE_OXXO = 'oxxo';
    const TYPE_P24 = 'p24';
    const TYPE_PAYCO = 'payco';
    const TYPE_PAYNOW = 'paynow';
    const TYPE_PAYPAL = 'paypal';
    const TYPE_PIX = 'pix';
    const TYPE_PROMPTPAY = 'promptpay';
    const TYPE_REVOLUT_PAY = 'revolut_pay';
    const TYPE_SAMSUNG_PAY = 'samsung_pay';
    const TYPE_SEPA_DEBIT = 'sepa_debit';
    const TYPE_SOFORT = 'sofort';
    const TYPE_SWISH = 'swish';
    const TYPE_TWINT = 'twint';
    const TYPE_US_BANK_ACCOUNT = 'us_bank_account';
    const TYPE_WECHAT_PAY = 'wechat_pay';
    const TYPE_ZIP = 'zip';

    /**
     * Creates a PaymentMethod object. Read the <a
     * href="/docs/stripe-js/reference#stripe-create-payment-method">Stripe.js
     * reference</a> to learn how to create PaymentMethods via Stripe.js.
     *
     * Instead of creating a PaymentMethod directly, we recommend using the <a
     * href="/docs/payments/accept-a-payment">PaymentIntents</a> API to accept a
     * payment immediately or the <a
     * href="/docs/payments/save-and-reuse">SetupIntent</a> API to collect payment
     * method details ahead of a future payment.
     *
     * @param null|array $params
     * @param null|array|string $options
     *
     * @throws \SAHCFWC\Libraries\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \SAHCFWC\Libraries\Stripe\PaymentMethod the created resource
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
     * Returns a list of PaymentMethods for Treasury flows. If you want to list the
     * PaymentMethods attached to a Customer for payments, you should use the <a
     * href="/docs/api/payment_methods/customer_list">List a Customer’s
     * PaymentMethods</a> API instead.
     *
     * @param null|array $params
     * @param null|array|string $opts
     *
     * @throws \SAHCFWC\Libraries\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \SAHCFWC\Libraries\Stripe\Collection<\SAHCFWC\Libraries\Stripe\PaymentMethod> of ApiResources
     */
    public static function all($params = null, $opts = null)
    {
        $url = static::classUrl();

        return static::_requestPage($url, \SAHCFWC\Libraries\Stripe\Collection::class, $params, $opts);
    }

    /**
     * Retrieves a PaymentMethod object attached to the StripeAccount. To retrieve a
     * payment method attached to a Customer, you should use <a
     * href="/docs/api/payment_methods/customer">Retrieve a Customer’s
     * PaymentMethods</a>.
     *
     * @param array|string $id the ID of the API resource to retrieve, or an options array containing an `id` key
     * @param null|array|string $opts
     *
     * @throws \SAHCFWC\Libraries\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \SAHCFWC\Libraries\Stripe\PaymentMethod
     */
    public static function retrieve($id, $opts = null)
    {
        $opts = \SAHCFWC\Libraries\Stripe\Util\RequestOptions::parse($opts);
        $instance = new static($id, $opts);
        $instance->refresh();

        return $instance;
    }

    /**
     * Updates a PaymentMethod object. A PaymentMethod must be attached a customer to
     * be updated.
     *
     * @param string $id the ID of the resource to update
     * @param null|array $params
     * @param null|array|string $opts
     *
     * @throws \SAHCFWC\Libraries\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \SAHCFWC\Libraries\Stripe\PaymentMethod the updated resource
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

    /**
     * @param null|array $params
     * @param null|array|string $opts
     *
     * @throws \SAHCFWC\Libraries\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \SAHCFWC\Libraries\Stripe\PaymentMethod the attached payment method
     */
    public function attach($params = null, $opts = null)
    {
        $url = $this->instanceUrl() . '/attach';
        list($response, $opts) = $this->_request('post', $url, $params, $opts);
        $this->refreshFrom($response, $opts);

        return $this;
    }

    /**
     * @param null|array $params
     * @param null|array|string $opts
     *
     * @throws \SAHCFWC\Libraries\Stripe\Exception\ApiErrorException if the request fails
     *
     * @return \SAHCFWC\Libraries\Stripe\PaymentMethod the detached payment method
     */
    public function detach($params = null, $opts = null)
    {
        $url = $this->instanceUrl() . '/detach';
        list($response, $opts) = $this->_request('post', $url, $params, $opts);
        $this->refreshFrom($response, $opts);

        return $this;
    }
}
