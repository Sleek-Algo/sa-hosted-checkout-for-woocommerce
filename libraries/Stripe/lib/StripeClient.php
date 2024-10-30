<?php

namespace SAHCFWC\Libraries\Stripe;

/**
 * Client used to send requests to Stripe's API.
 *
 * @property \SAHCFWC\Libraries\Stripe\Service\OAuthService $oauth
 * // The beginning of the section generated from our OpenAPI spec
 * @property \SAHCFWC\Libraries\Stripe\Service\AccountLinkService $accountLinks
 * @property \SAHCFWC\Libraries\Stripe\Service\AccountService $accounts
 * @property \SAHCFWC\Libraries\Stripe\Service\AccountSessionService $accountSessions
 * @property \SAHCFWC\Libraries\Stripe\Service\ApplePayDomainService $applePayDomains
 * @property \SAHCFWC\Libraries\Stripe\Service\ApplicationFeeService $applicationFees
 * @property \SAHCFWC\Libraries\Stripe\Service\Apps\AppsServiceFactory $apps
 * @property \SAHCFWC\Libraries\Stripe\Service\BalanceService $balance
 * @property \SAHCFWC\Libraries\Stripe\Service\BalanceTransactionService $balanceTransactions
 * @property \SAHCFWC\Libraries\Stripe\Service\Billing\BillingServiceFactory $billing
 * @property \SAHCFWC\Libraries\Stripe\Service\BillingPortal\BillingPortalServiceFactory $billingPortal
 * @property \SAHCFWC\Libraries\Stripe\Service\ChargeService $charges
 * @property \SAHCFWC\Libraries\Stripe\Service\Checkout\CheckoutServiceFactory $checkout
 * @property \SAHCFWC\Libraries\Stripe\Service\Climate\ClimateServiceFactory $climate
 * @property \SAHCFWC\Libraries\Stripe\Service\ConfirmationTokenService $confirmationTokens
 * @property \SAHCFWC\Libraries\Stripe\Service\CountrySpecService $countrySpecs
 * @property \SAHCFWC\Libraries\Stripe\Service\CouponService $coupons
 * @property \SAHCFWC\Libraries\Stripe\Service\CreditNoteService $creditNotes
 * @property \SAHCFWC\Libraries\Stripe\Service\CustomerService $customers
 * @property \SAHCFWC\Libraries\Stripe\Service\CustomerSessionService $customerSessions
 * @property \SAHCFWC\Libraries\Stripe\Service\DisputeService $disputes
 * @property \SAHCFWC\Libraries\Stripe\Service\Entitlements\EntitlementsServiceFactory $entitlements
 * @property \SAHCFWC\Libraries\Stripe\Service\EphemeralKeyService $ephemeralKeys
 * @property \SAHCFWC\Libraries\Stripe\Service\EventService $events
 * @property \SAHCFWC\Libraries\Stripe\Service\ExchangeRateService $exchangeRates
 * @property \SAHCFWC\Libraries\Stripe\Service\FileLinkService $fileLinks
 * @property \SAHCFWC\Libraries\Stripe\Service\FileService $files
 * @property \SAHCFWC\Libraries\Stripe\Service\FinancialConnections\FinancialConnectionsServiceFactory $financialConnections
 * @property \SAHCFWC\Libraries\Stripe\Service\Forwarding\ForwardingServiceFactory $forwarding
 * @property \SAHCFWC\Libraries\Stripe\Service\Identity\IdentityServiceFactory $identity
 * @property \SAHCFWC\Libraries\Stripe\Service\InvoiceItemService $invoiceItems
 * @property \SAHCFWC\Libraries\Stripe\Service\InvoiceRenderingTemplateService $invoiceRenderingTemplates
 * @property \SAHCFWC\Libraries\Stripe\Service\InvoiceService $invoices
 * @property \SAHCFWC\Libraries\Stripe\Service\Issuing\IssuingServiceFactory $issuing
 * @property \SAHCFWC\Libraries\Stripe\Service\MandateService $mandates
 * @property \SAHCFWC\Libraries\Stripe\Service\PaymentIntentService $paymentIntents
 * @property \SAHCFWC\Libraries\Stripe\Service\PaymentLinkService $paymentLinks
 * @property \SAHCFWC\Libraries\Stripe\Service\PaymentMethodConfigurationService $paymentMethodConfigurations
 * @property \SAHCFWC\Libraries\Stripe\Service\PaymentMethodDomainService $paymentMethodDomains
 * @property \SAHCFWC\Libraries\Stripe\Service\PaymentMethodService $paymentMethods
 * @property \SAHCFWC\Libraries\Stripe\Service\PayoutService $payouts
 * @property \SAHCFWC\Libraries\Stripe\Service\PlanService $plans
 * @property \SAHCFWC\Libraries\Stripe\Service\PriceService $prices
 * @property \SAHCFWC\Libraries\Stripe\Service\ProductService $products
 * @property \SAHCFWC\Libraries\Stripe\Service\PromotionCodeService $promotionCodes
 * @property \SAHCFWC\Libraries\Stripe\Service\QuoteService $quotes
 * @property \SAHCFWC\Libraries\Stripe\Service\Radar\RadarServiceFactory $radar
 * @property \SAHCFWC\Libraries\Stripe\Service\RefundService $refunds
 * @property \SAHCFWC\Libraries\Stripe\Service\Reporting\ReportingServiceFactory $reporting
 * @property \SAHCFWC\Libraries\Stripe\Service\ReviewService $reviews
 * @property \SAHCFWC\Libraries\Stripe\Service\SetupAttemptService $setupAttempts
 * @property \SAHCFWC\Libraries\Stripe\Service\SetupIntentService $setupIntents
 * @property \SAHCFWC\Libraries\Stripe\Service\ShippingRateService $shippingRates
 * @property \SAHCFWC\Libraries\Stripe\Service\Sigma\SigmaServiceFactory $sigma
 * @property \SAHCFWC\Libraries\Stripe\Service\SourceService $sources
 * @property \SAHCFWC\Libraries\Stripe\Service\SubscriptionItemService $subscriptionItems
 * @property \SAHCFWC\Libraries\Stripe\Service\SubscriptionService $subscriptions
 * @property \SAHCFWC\Libraries\Stripe\Service\SubscriptionScheduleService $subscriptionSchedules
 * @property \SAHCFWC\Libraries\Stripe\Service\Tax\TaxServiceFactory $tax
 * @property \SAHCFWC\Libraries\Stripe\Service\TaxCodeService $taxCodes
 * @property \SAHCFWC\Libraries\Stripe\Service\TaxIdService $taxIds
 * @property \SAHCFWC\Libraries\Stripe\Service\TaxRateService $taxRates
 * @property \SAHCFWC\Libraries\Stripe\Service\Terminal\TerminalServiceFactory $terminal
 * @property \SAHCFWC\Libraries\Stripe\Service\TestHelpers\TestHelpersServiceFactory $testHelpers
 * @property \SAHCFWC\Libraries\Stripe\Service\TokenService $tokens
 * @property \SAHCFWC\Libraries\Stripe\Service\TopupService $topups
 * @property \SAHCFWC\Libraries\Stripe\Service\TransferService $transfers
 * @property \SAHCFWC\Libraries\Stripe\Service\Treasury\TreasuryServiceFactory $treasury
 * @property \SAHCFWC\Libraries\Stripe\Service\V2\V2ServiceFactory $v2
 * @property \SAHCFWC\Libraries\Stripe\Service\WebhookEndpointService $webhookEndpoints
 * // The end of the section generated from our OpenAPI spec
 */
class StripeClient extends BaseStripeClient
{
    /**
     * @var \SAHCFWC\Libraries\Stripe\Service\CoreServiceFactory
     */
    private $coreServiceFactory;

    public function __get($name)
    {
        return $this->getService($name);
    }

    public function getService($name)
    {
        if (null === $this->coreServiceFactory) {
            $this->coreServiceFactory = new \SAHCFWC\Libraries\Stripe\Service\CoreServiceFactory($this);
        }

        return $this->coreServiceFactory->getService($name);
    }
}
