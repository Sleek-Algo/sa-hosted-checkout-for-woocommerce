<?php
/**
 * Canonical Checkout payment-method labels for the administration screen.
 *
 * @package sa-hosted-checkout-for-woocommerce
 */

namespace SAHCFWC\Traits;

require_once dirname( __DIR__ ) . '/classes/class-sahcfwc-payment-methods.php';

if ( ! trait_exists( 'SAHCFWC\\Traits\\SAHCFWC_Stripe_Local_Payment_Methods_List' ) ) {
	trait SAHCFWC_Stripe_Local_Payment_Methods_List {
		/**
		 * Show the supported catalog, not a claim of Stripe account eligibility.
		 *
		 * Store country alone cannot determine availability: Stripe also checks
		 * the account, currency, customer, order amount and payment flow.
		 *
		 * @param string $wc_country Retained for callers of the old catalog.
		 * @return array
		 */
		public function sahcfwc_get_stripe_local_payment_methods( $wc_country = '' ) {
			$catalog = array(
				'card' => array( __( 'Cards', 'sa-hosted-checkout-for-woocommerce' ), '', __( 'Includes eligible Visa, Mastercard, American Express, Discover, Diners Club, JCB, UnionPay and other card networks. Card brands are not separate payment methods.', 'sa-hosted-checkout-for-woocommerce' ), false ),
				'customer_balance' => array( __( 'USD bank transfer', 'sa-hosted-checkout-for-woocommerce' ), 'usd-bank-transfers.svg', __( 'Customers send USD to bank details provided by Stripe. This is a bank transfer, not an ACH debit.', 'sa-hosted-checkout-for-woocommerce' ), true ),
				'us_bank_account' => array( __( 'ACH Direct Debit', 'sa-hosted-checkout-for-woocommerce' ), 'ach.svg', __( 'Debit a US bank account in USD after customer authorization.', 'sa-hosted-checkout-for-woocommerce' ), true ),
				'ideal' => array( __( 'iDEAL | Wero', 'sa-hosted-checkout-for-woocommerce' ), '', __( 'Bank payments for customers in the Netherlands, in EUR.', 'sa-hosted-checkout-for-woocommerce' ), false ),
				'affirm' => array( __( 'Affirm', 'sa-hosted-checkout-for-woocommerce' ), 'affirm.svg', __( 'Buy now, pay later in supported markets and currencies.', 'sa-hosted-checkout-for-woocommerce' ), false ),
				'afterpay_clearpay' => array( __( 'Afterpay / Clearpay', 'sa-hosted-checkout-for-woocommerce' ), 'afterpay.svg', __( 'Buy now, pay later in supported markets and currencies.', 'sa-hosted-checkout-for-woocommerce' ), false ),
				'paynow' => array( __( 'PayNow', 'sa-hosted-checkout-for-woocommerce' ), 'paynow.svg', __( 'Singapore payments in SGD.', 'sa-hosted-checkout-for-woocommerce' ), false ),
				'paypal' => array( __( 'PayPal', 'sa-hosted-checkout-for-woocommerce' ), 'paypal.svg', __( 'PayPal wallet payments, where supported by the Stripe account.', 'sa-hosted-checkout-for-woocommerce' ), false ),
				'oxxo' => array( __( 'OXXO', 'sa-hosted-checkout-for-woocommerce' ), 'oxxo.svg', __( 'Cash voucher payments in Mexico, in MXN.', 'sa-hosted-checkout-for-woocommerce' ), true ),
				'acss_debit' => array( __( 'Canadian pre-authorized debit', 'sa-hosted-checkout-for-woocommerce' ), 'preauth.svg', __( 'Debit a Canadian bank account in CAD or USD after customer authorization.', 'sa-hosted-checkout-for-woocommerce' ), true ),
				'konbini' => array( __( 'Konbini', 'sa-hosted-checkout-for-woocommerce' ), 'konbini.svg', __( 'Convenience-store payments in Japan, in JPY.', 'sa-hosted-checkout-for-woocommerce' ), true ),
				'promptpay' => array( __( 'PromptPay', 'sa-hosted-checkout-for-woocommerce' ), 'promptpay.svg', __( 'Thailand payments in THB.', 'sa-hosted-checkout-for-woocommerce' ), false ),
				'p24' => array( __( 'Przelewy24', 'sa-hosted-checkout-for-woocommerce' ), 'prze.svg', __( 'Polish bank payments in EUR or PLN.', 'sa-hosted-checkout-for-woocommerce' ), false ),
				'sepa_debit' => array( __( 'SEPA Direct Debit', 'sa-hosted-checkout-for-woocommerce' ), 'sepa.svg', __( 'Debit a SEPA bank account in EUR after customer authorization.', 'sa-hosted-checkout-for-woocommerce' ), true ),
				'eps' => array( __( 'EPS', 'sa-hosted-checkout-for-woocommerce' ), 'eps.svg', __( 'Austrian online-bank payments in EUR.', 'sa-hosted-checkout-for-woocommerce' ), false ),
				'fpx' => array( __( 'FPX', 'sa-hosted-checkout-for-woocommerce' ), 'fpx.svg', __( 'Malaysian bank payments in MYR.', 'sa-hosted-checkout-for-woocommerce' ), false ),
				'grabpay' => array( __( 'GrabPay', 'sa-hosted-checkout-for-woocommerce' ), 'grabpay.svg', __( 'Wallet payments in MYR or SGD.', 'sa-hosted-checkout-for-woocommerce' ), false ),
				'klarna' => array( __( 'Klarna', 'sa-hosted-checkout-for-woocommerce' ), 'klarna.svg', __( 'Klarna payment options depend on the customer, currency and order.', 'sa-hosted-checkout-for-woocommerce' ), false ),
				'link' => array( __( 'Link', 'sa-hosted-checkout-for-woocommerce' ), 'link.svg', __( 'Faster checkout using payment details saved with Link. Cards must also be selected.', 'sa-hosted-checkout-for-woocommerce' ), false ),
				'boleto' => array( __( 'Boleto', 'sa-hosted-checkout-for-woocommerce' ), 'boleto.svg', __( 'Brazilian voucher payments in BRL.', 'sa-hosted-checkout-for-woocommerce' ), true ),
				'blik' => array( __( 'BLIK', 'sa-hosted-checkout-for-woocommerce' ), 'blik.svg', __( 'Polish payments in PLN.', 'sa-hosted-checkout-for-woocommerce' ), false ),
				'au_becs_debit' => array( __( 'BECS Direct Debit', 'sa-hosted-checkout-for-woocommerce' ), 'becs.svg', __( 'Debit an Australian bank account in AUD after customer authorization.', 'sa-hosted-checkout-for-woocommerce' ), true ),
				'bacs_debit' => array( __( 'Bacs Direct Debit', 'sa-hosted-checkout-for-woocommerce' ), 'bacs.svg', __( 'Debit a UK bank account in GBP after customer authorization.', 'sa-hosted-checkout-for-woocommerce' ), true ),
				'bancontact' => array( __( 'Bancontact', 'sa-hosted-checkout-for-woocommerce' ), 'bancontact.svg', __( 'Belgian payments in EUR.', 'sa-hosted-checkout-for-woocommerce' ), false ),
				'alipay' => array( __( 'Alipay', 'sa-hosted-checkout-for-woocommerce' ), 'alipay.svg', __( 'Alipay wallet payments in supported currencies.', 'sa-hosted-checkout-for-woocommerce' ), false ),
				'wechat_pay' => array( __( 'WeChat Pay', 'sa-hosted-checkout-for-woocommerce' ), 'wechat.svg', __( 'WeChat wallet payments in supported currencies.', 'sa-hosted-checkout-for-woocommerce' ), false ),
				'zip' => array( __( 'Zip', 'sa-hosted-checkout-for-woocommerce' ), 'zip.svg', __( 'Buy now, pay later in AUD, or USD where Stripe has granted access.', 'sa-hosted-checkout-for-woocommerce' ), false ),
				'cashapp' => array( __( 'Cash App Pay', 'sa-hosted-checkout-for-woocommerce' ), 'cash.svg', __( 'Cash App wallet payments in USD.', 'sa-hosted-checkout-for-woocommerce' ), false ),
				'revolut_pay' => array( __( 'Revolut Pay', 'sa-hosted-checkout-for-woocommerce' ), 'rpay.svg', __( 'Revolut wallet payments in supported European currencies.', 'sa-hosted-checkout-for-woocommerce' ), false ),
				'mobilepay' => array( __( 'MobilePay', 'sa-hosted-checkout-for-woocommerce' ), 'mobile.svg', __( 'Mobile wallet payments for eligible Nordic customers.', 'sa-hosted-checkout-for-woocommerce' ), false ),
				'swish' => array( __( 'Swish', 'sa-hosted-checkout-for-woocommerce' ), 'swish.svg', __( 'Swedish mobile payments in SEK.', 'sa-hosted-checkout-for-woocommerce' ), false ),
			);
			$selected = \SAHCFWC\Classes\SAHCFWC_Payment_Methods::normalize( get_option( 'sahcfwc_stripe_payment_methodes', array() ) );
			$methods = array();
			foreach ( \SAHCFWC\Classes\SAHCFWC_Payment_Methods::allowed() as $id ) {
				if ( ! isset( $catalog[ $id ] ) ) {
					continue;
				}
				$entry = $catalog[ $id ];
				$methods[] = array(
					'id'          => $id,
					'title'       => $entry[0],
					'icon_url'    => $entry[1] ? SAHCFWC_URL_ASSETS_BACKEND_LOCAL_PAYMENT_METHODS_IMAGES . '/' . $entry[1] : '',
					'countries'   => array(),
					'description' => $entry[2],
					'help'        => '',
					'legal'       => '',
					'created_at'  => '',
					'updated_at'  => '',
					'status'      => in_array( $id, $selected, true ) ? 'enabled' : 'disabled',
					'delayed'     => $entry[3],
				);
			}
			return $methods;
		}
	}
}
