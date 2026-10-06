<?php
/**
 * WC_Payment_Gateway class.
 *
 * @package sa-hosted-checkout-for-woocommerce
 */

namespace SAHCFWC\Classes;

require_once dirname( __DIR__ ) . '/traits/sahcfwc-order-totals.php';

if ( ! class_exists( '\SAHCFWC\Classes\SAHCFWC_Payment_Gateway' ) ) {
	if ( class_exists( '\WC_Payment_Gateway' ) ) {
		/**
		 * Load payment gatway
		 *
		 * This class load plugin gatway in WooCommerce
		 *
		 * @copyright  sleekalgo
		 * @version    Release: 1.0.0
		 * @link       https://www.sleekalgo.com
		 * @package    SA Hosted Checkout for WooCommerce
		 * @since      Class available since Release 1.0.0
		 */
		class SAHCFWC_Payment_Gateway extends \WC_Payment_Gateway {
			/**
			 * Traits used inside class
			 */
			use \SAHCFWC\Traits\SAHCFWC_Singleton;
			use \SAHCFWC\Traits\SAHCFWC_Helpers;
			use \SAHCFWC\Traits\SAHCFWC_Order_Totals;
			/**
			 * Stripe secret key.
			 *
			 * @since 1.0.0
			 *
			 * @var string
			 */
			private $sahcfwc_stripe_secret = '';
			public $instructions = '';

			/**
			 * Stripe Shipping address.
			 *
			 * @since 1.0.0
			 *
			 * @var string
			 */
			public $sahcfwc_stripe_shipping_address_status;

			/**
			 * Stripe Terms condition status.
			 *
			 * @since 1.0.0
			 *
			 * @var string
			 */
			public $sahcfwc_stripe_terms_condition_status;

			/**
			 * Stripe Phone number status.
			 *
			 * @since 1.0.0
			 *
			 * @var string
			 */
			public $sahcfwc_stripe_phone_num_status;

			/**
			 * Stripe Cancel URL.
			 *
			 * @since 1.0.0
			 *
			 * @var string
			 */
			public $sahcfwc_stripe_cancel_url;

			/**
			 * Stripe Locale.
			 *
			 * @since 1.0.0
			 *
			 * @var string
			 */
			public $sahcfwc_stripe_locale;

			/**
			 * Stripe Locale staus.
			 *
			 * @since 1.0.0
			 *
			 * @var string
			 */
			public $sahcfwc_stripe_locale_staus;

			/**
			 * Stripe Custom field status.
			 *
			 * @since 1.0.0
			 *
			 * @var string
			 */
			public $sahcfwc_stripe_custom_field_status;

			/**
			 * Stripe Custom text field.
			 *
			 * @since 1.0.0
			 *
			 * @var string
			 */
			public $sahcfwc_stripe_custom_text_field;

			/**
			 * Stripe custom text filed label.
			 *
			 * @since 1.0.0
			 *
			 * @var string
			 */
			public $sahcfwc_stripe_custom_text_field_label;

			/**
			 * Stripe custom text field value.
			 *
			 * @since 1.0.0
			 *
			 * @var string
			 */
			public $sahcfwc_stripe_custom_text_field_value;

			/**
			 * Stripe custom text field optional.
			 *
			 * @since 1.0.0
			 *
			 * @var string
			 */
			public $sahcfwc_stripe_custom_text_field_optional;

			/**
			 * Stripe dropdown field optional.
			 *
			 * @since 1.0.0
			 *
			 * @var string
			 */
			public $sahcfwc_stripe_custom_droupdown_field_optional;

			/**
			 * Stripe cusom field number.
			 *
			 * @since 1.0.0
			 *
			 * @var string
			 */
			public $sahcfwc_stripe_custom_number_field;

			/**
			 * Stripe custom number filed label.
			 *
			 * @since 1.0.0
			 *
			 * @var string
			 */
			public $sahcfwc_stripe_custom_number_field_label;

			/**
			 * Stripe  custom number filed optional.
			 *
			 * @since 1.0.0
			 *
			 * @var string
			 */
			public $sahcfwc_stripe_custom_number_field_optional;

			/**
			 * Stripe  custom filed optional.
			 *
			 * @since 1.0.0
			 *
			 * @var string
			 */
			public $sahcfwc_stripe_custom_field_options;

			/**
			 * Stripe  custom dropdown filed.
			 *
			 * @since 1.0.0
			 *
			 * @var string
			 */
			public $sahcfwc_stripe_custom_dropdown_field;

			/**
			 * Stripe custom dropdown filed label.
			 *
			 * @since 1.0.0
			 *
			 * @var string
			 */
			public $sahcfwc_stripe_custom_droupdown_field_label;

			/**
			 * Stripe country payment method status.
			 *
			 * @since 1.0.0
			 *
			 * @var string
			 */
			public $sahcfwc_stripe_country_payment_method_status;

			/**
			 * Stripe adjustment quantity.
			 *
			 * @since 1.0.0
			 *
			 * @var string
			 */
			public $sahcfwc_stripe_adjustment_quantity;

			/**
			 * A constructor to prevent this class from being loaded more than once.
			 *
			 * @see Filter Hook
			 *
			 * @since 1.0.0
			 * @access public
			 */
			public function __construct() {
				// Setup general properties.
				$this->sahcfwc_set_stripe_secret();
				$this->sahcfwc_setup_properties();
				// Load the settings.
				$this->init_form_fields();
				$this->init_settings();
				// Get settings.
				$this->title        = $this->get_option( 'title' );
				$this->description  = $this->get_option( 'description' );
				$this->instructions = $this->get_option( 'instructions' );
				// Actions.
				add_action( 'wp_enqueue_scripts', array( $this, 'sahcfwc_payment_scripts' ) );
				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
				add_action( 'set_logged_in_cookie', array( $this, 'sahcfwc_eh_set_cookie_on_current_request' ) );
			}

			/**
			 * Handle properties.
			 *
			 * @since 1.0.0
			 *
			 * @return void.
			 */
			protected function sahcfwc_setup_properties() {
				$this->id                 = 'sahcfwc_stripe_checkout';
				$this->icon               = apply_filters( 'sahcfwc_stripe_checkout_payment_gateway_icon', '' );
				$this->method_title       = esc_html__( 'Stripe Checkout', 'sa-hosted-checkout-for-woocommerce' );
				$this->method_description = esc_html__( 'Let your customers pay with confidence using highly optimized, Stripe hosted checkout.', 'sa-hosted-checkout-for-woocommerce' );
				$this->has_fields         = false;
			}

			/**
			 * Handle form fields.
			 *
			 * @since 1.0.0
			 *
			 * @return void
			 */
			public function init_form_fields() {
				$this->form_fields = array(
					'enabled'      => array(
						'title'       => esc_html__( 'Enable/Disable', 'sa-hosted-checkout-for-woocommerce' ),
						'label'       => esc_html__( 'Enable Stripe Checkout', 'sa-hosted-checkout-for-woocommerce' ),
						'type'        => 'checkbox',
						'description' => '',
						'default'     => 'no',
					),
					'title'        => array(
						'title'       => esc_html__( 'Title', 'sa-hosted-checkout-for-woocommerce' ),
						'type'        => 'safe_text',
						'description' => esc_html__( 'Payment method description that the customer will see on your checkout.', 'sa-hosted-checkout-for-woocommerce' ),
						'default'     => esc_html__( 'Stripe Checkout', 'sa-hosted-checkout-for-woocommerce' ),
						'desc_tip'    => true,
					),
					'description'  => array(
						'title'       => esc_html__( 'Description', 'sa-hosted-checkout-for-woocommerce' ),
						'type'        => 'textarea',
						'description' => esc_html__( 'Payment method description that the customer will see on your website.', 'sa-hosted-checkout-for-woocommerce' ),
						'default'     => esc_html__( 'Stripe Checkout redirects users to a secure, Stripe-hosted payment page to accept payment.', 'sa-hosted-checkout-for-woocommerce' ),
						'desc_tip'    => true,
					),
					'instructions' => array(
						'title'       => esc_html__( 'Instructions', 'sa-hosted-checkout-for-woocommerce' ),
						'type'        => 'textarea',
						'description' => esc_html__( 'Instructions that will be added to the thank you page.', 'sa-hosted-checkout-for-woocommerce' ),
						'default'     => esc_html__( 'Thanks you have paid with stripe hosted checkout.', 'sa-hosted-checkout-for-woocommerce' ),
						'desc_tip'    => true,
					),
				);
			}

			/**
			 * Set enable.
			 *
			 * @since 1.0.0
			 *
			 * @return string
			 */
			public function is_enabled() {
				return 'yes' === $this->get_option( 'enabled' );
			}

			/**
			 * Check enable.
			 *
			 * @since 1.0.0
			 *
			 * @return boolean
			 */
			public function is_available() {
				return 'yes' === get_option( 'sahcfwc_stripe_checkout_status' )
					&& $this->is_enabled()
					&& ! empty( $this->sahcfwc_get_stripe_secret_key() )
					&& parent::is_available();
			}

			/**
			 * Handle payment fields.
			 *
			 * @since 1.0.0
			 *
			 * @return void The Stripe checkout.
			 */
			public function payment_fields() {
				echo '<div class="status-box">';
				if ( $this->description ) {
                    // @codingStandardsIgnoreStart
                    echo apply_filters( 'sahcfwc_stripe_checkout_payment_desc', wpautop( wp_kses_post( '<span>' . $this->description . '</span>' ) ) );
                    // @codingStandardsIgnoreEnd
				}
				echo '</div>';
			}

			/**
			 * Round ammound .
			 *
			 * @since 1.0.0
			 *
			 * @param  string $cookie .
			 * @return void.
			 */
			public function sahcfwc_eh_set_cookie_on_current_request( $cookie ) {
				$_COOKIE[ LOGGED_IN_COOKIE ] = $cookie;
			}

			/**
			 * Handle payment fields.
			 *
			 * @since 1.0.0
			 *
			 * @return void The Stripe checkout.
			 */
			public function sahcfwc_payment_scripts() {
			}

			/**
			 * Checkout url .
			 *
			 * @since 1.0.0
			 *
			 * @param  int|   $session_id Stripe session id.
			 * @param  array| $order WooCommerce order data.
			 * @return array.
			 */
			public function get_payment_session_checkout_url( $session_id, $order ) {
				// @codingStandardsIgnoreStart
				return sprintf(
					'#response=%s',
                    base64_encode( wp_json_encode( array(
                        'session_id' => $session_id,
                        'order_id'   => ( WC()->version < '2.7.0' ? $order->id : $order->get_id() ),
                        'time'       => wp_rand( 0, 999999 ),
                    ) ) )
                 );
				 // @codingStandardsIgnoreEnd
			}

			/**
			 * Round ammound.
			 *
			 * @since 1.0.0
			 *
			 * @param  int| $order_id WooCommerce order id.
			 * @return array.
			 */
			public function process_payment( $order_id ) {
				$order = wc_get_order( $order_id );
				if ( ! $this->is_available() || ! $order || ! $order->needs_payment() || empty( $this->sahcfwc_stripe_secret ) ) {
					wc_add_notice( __( 'This order cannot be paid through Stripe Checkout. Please check the order and payment settings.', 'sa-hosted-checkout-for-woocommerce' ), 'error' );
					return array( 'result' => 'failure' );
				}
				$currency = $order->get_currency();
				$lineitems = $this->sahcfwc_order_line_items( $order );
				if ( isset( $this->sahcfwc_stripe_secret ) && ! empty( $this->sahcfwc_stripe_secret ) ) {
					if ( class_exists( '\SAHCFWC\Libraries\Stripe\Stripe' ) ) {
						\SAHCFWC\Libraries\Stripe\Stripe::setApiKey( $this->sahcfwc_stripe_secret );
					}
					if ( class_exists( '\SAHCFWC\Libraries\Stripe\StripeClient' ) ) {
						$stripe = new \SAHCFWC\Libraries\Stripe\StripeClient( $this->sahcfwc_stripe_secret );
					} else {
						$stripe = array();
					}
                    // @codingStandardsIgnoreStart

                    // @codingStandardsIgnoreEnd
					$checkoutarray = array(
						'line_items'                 => $lineitems,
						'mode'                       => 'payment',
						'success_url'                => wp_sanitize_redirect( home_url() ) . '/?wc-ajax=sahcfwc_stripe_checkout_order&sessionid={CHECKOUT_SESSION_ID}&order_id=' . $order_id . '&_wpnonce=' . wp_create_nonce( 'sahcfwc_checkout_nonce' ),
						// @codingStandardsIgnoreStart
						'cancel_url'                 => wp_sanitize_redirect( home_url() ) . '/?wc-ajax=sahcfwc_stripe_cancel_order&sessionid={CHECKOUT_SESSION_ID}&_wpnonce=' . wp_create_nonce( 'sahcfwc_checkout_nonce' ) . '&order_id=' . $order_id,
						// @codingStandardsIgnoreEnd
						'billing_address_collection' => 'required',
						'expires_at'                 => time() + 3600 * 1,
					);
					if ( ! empty( $this->sahcfwc_stripe_phone_num_status ) && 'yes' === $this->sahcfwc_stripe_phone_num_status ) {
						$checkoutarray['phone_number_collection'] = array(
							'enabled' => true,
						);
					}
					if ( ! empty( $this->sahcfwc_stripe_shipping_address_status ) && 'yes' === $this->sahcfwc_stripe_shipping_address_status ) {
						// The shared builder validates the saved destination and country fallback.
						$checkoutarray['shipping_address_collection'] = array(
							'allowed_countries' => array( $order->get_shipping_country() ),
						);
					}
					if ( ! empty( $this->sahcfwc_stripe_terms_condition_status ) && 'yes' === $this->sahcfwc_stripe_terms_condition_status ) {
						$checkoutarray['consent_collection'] = array(
							'terms_of_service' => 'required',
						);
					}
					$checkoutarray['payment_method_types'] = array( 'card' );
					// check customer token is exist for the logged in user.
					$user             = wp_get_current_user();
					$logged_in_userid = $user->ID;
					$customer_id      = get_user_meta( $logged_in_userid, 'sahcfwc_stripe_ch_customer_id', true );
					if ( is_user_logged_in() ) {
						$current_user                         = wp_get_current_user();
						$checkoutarray['customer_email']      = $current_user->user_email;
						$checkoutarray['client_reference_id'] = get_current_user_id();
					}
					$shipping_items  = $order->get_items( 'shipping' );
					$shipping_d_name = '';
					foreach ( $shipping_items as $shipping_item ) {
						$shipping_d_name = $shipping_item->get_name();
					}
					if ( 0 < $order->get_shipping_total() ) {
						$shipping = $order->get_shipping_total();
						$checkoutarray['shipping_options'] = array(
							array(
								'shipping_rate_data' => array(
									'display_name' => $shipping_d_name,
									'fixed_amount' => array(
										'amount'   => $this->sahcfwc_get_stripe_amount( $shipping, $currency ),
										'currency' => $currency,
									),
									'type'         => 'fixed_amount',
								),
							),
						);
					}
					/**
					 * Set Checkout Session - Custom Meta Data.
					 */
					$name                                 = sanitize_text_field( $order->get_billing_first_name() ) . ' ' . sanitize_text_field( $order->get_billing_last_name() );
					$email                                = sanitize_email( $order->get_billing_email() );
					$checkoutarray['payment_intent_data'] = array(
						'description' => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) . ' Order #' . $order->get_order_number(),
						'metadata'    => array(
							'sahcfwc_order_id'       => $order->get_id(),
							'sahcfwc_name'           => $name,
							'sahcfwc_customer_email' => $email,
							'sahcfwc_order_key'      => $order->get_order_key(),
							'sahcfwc_site_url'       => get_bloginfo( 'name' ),
							'sahcfwc_plugin_name'    => get_site_url(),
						),
					);
					if ( class_exists( '\SAHCFWC\Libraries\Stripe\Checkout\Session' ) ) {
						try {
							$checkout_session = $this->sahcfwc_create_order_checkout_session( $order, $checkoutarray );
						} catch ( \Exception $error ) {
							wc_add_notice( __( 'Unable to start Stripe Checkout. Please try again or contact the store.', 'sa-hosted-checkout-for-woocommerce' ), 'error' );
							return array( 'result' => 'failure' );
						}
					}
					// The shared builder has persisted the session-to-order binding.
					if ( isset( $checkout_session->url ) && ! empty( $checkout_session->url ) ) {
						return array(
							'result'   => 'success',
							'redirect' => $checkout_session->url,
						);
					} else {
						return array(
							'result' => 'failure',
						);
					}
				}
			}

			/**
			 * Add WooCommerce fees to the Stripe Checkout total.
			 *
			 * @param array    $lineitems Stripe Checkout line items.
			 * @param WC_Order $order WooCommerce order.
			 * @param string   $currency Order currency.
			 * @return array
			 */
			private function sahcfwc_add_order_fees_line_item( $lineitems, $order, $currency ) {
				foreach ( $order->get_items( 'fee' ) as $fee ) {
					$fee_total = (float) $fee->get_total();

					if ( 0 >= $fee_total ) {
						continue;
					}

					$lineitems[] = array(
						'quantity'   => 1,
						'price_data' => array(
							'currency'     => $currency,
							'product_data' => array(
								'name' => sanitize_text_field( $fee->get_name() ),
							),
							'unit_amount'  => $this->sahcfwc_get_stripe_amount( $fee_total, $currency ),
						),
					);
				}

				return $lineitems;
			}

			/**
			 * Add WooCommerce tax rates to the Stripe Checkout total.
			 *
			 * @param array    $lineitems Stripe Checkout line items.
			 * @param WC_Order $order WooCommerce order.
			 * @param string   $currency Order currency.
			 * @return array
			 */
			private function sahcfwc_add_order_tax_line_items( $lineitems, $order, $currency ) {
				foreach ( $order->get_items( 'tax' ) as $tax ) {
					$tax_total = (float) $tax->get_tax_total() + (float) $tax->get_shipping_tax_total();

					if ( 0 >= $tax_total ) {
						continue;
					}

					$tax_label   = sanitize_text_field( $tax->get_label() );
					$lineitems[] = array(
						'quantity'   => 1,
						'price_data' => array(
							'currency'     => $currency,
							'product_data' => array(
								'name' => $tax_label ? $tax_label : esc_html__( 'Tax', 'sa-hosted-checkout-for-woocommerce' ),
							),
							'unit_amount'  => $this->sahcfwc_get_stripe_amount( $tax_total, $currency ),
						),
					);
				}

				return $lineitems;
			}

			/**
			 * Convert a WooCommerce unit price to Stripe minor units without losing precision.
			 *
			 * @param float  $total Unit price.
			 * @param string $currency Order currency.
			 * @return string
			 */
			private function sahcfwc_get_stripe_decimal_amount( $total, $currency ) {
				$multiplier = in_array( strtoupper( $currency ), $this->sahcfwc_zerocurrency(), true ) ? 1 : 100;
				$amount     = number_format( max( 0, (float) $total ) * $multiplier, 12, '.', '' );
				$amount     = rtrim( rtrim( $amount, '0' ), '.' );

				return '' === $amount ? '0' : $amount;
			}

			/**
			 * Round ammound .
			 *
			 * @since 1.0.0
			 *
			 * @param  float|  $total total ammount.
			 * @param  string| $currency currency.
			 * @return float.
			 */
			public function sahcfwc_get_stripe_amount( $total, $currency = '' ) {
				return \SAHCFWC\Classes\SAHCFWC_Money::to_minor( $total, $currency ? $currency : get_woocommerce_currency() );
			}

			/**
			 * Currencys.
			 *
			 * @since 1.0.0
			 *
			 * @return array.
			 */
			public function sahcfwc_zerocurrency() {
				return array(
					'BIF',
					'CLP',
					'DJF',
					'GNF',
					'JPY',
					'KMF',
					'KRW',
					'MGA',
					'PYG',
					'RWF',
					'VUV',
					'XAF',
					'XOF',
					'XPF',
					'VND',
				);
			}

			/**
			 * Create customer.
			 *
			 * @since 1.0.0
			 *
			 * @param  array| $order WooCommerce order.
			 * @param  array| $user_obj user order.
			 * @return object.
			 */
			public function sahcfwc_create_stripe_customer( $order, $user_obj ) {
				if ( ! empty( $order ) ) {
					$order_no = $order->get_order_number();
					$params   = array(
						'description' => 'Customer for Order #' . $order_no,
						'email'       => ( WC()->version < '2.7.0' ? $order->billing_email : $order->get_billing_email() ),
						'address'     => array(
							'city'        => ( method_exists( $order, 'get_billing_city' ) ? $order->get_billing_city() : $order->billing_city ),
							'country'     => ( method_exists( $order, 'get_billing_country' ) ? $order->get_billing_country() : $order->billing_country ),
							'line1'       => ( method_exists( $order, 'get_billing_address_1' ) ? $order->get_billing_address_1() : $order->billing_address_1 ),
							'line2'       => ( method_exists( $order, 'get_billing_address_2' ) ? $order->get_billing_address_2() : $order->billing_address_2 ),
							'postal_code' => ( method_exists( $order, 'get_billing_postcode' ) ? $order->get_billing_postcode() : $order->billing_postcode ),
							'state'       => ( method_exists( $order, 'get_billing_state' ) ? $order->get_billing_state() : $order->billing_state ),
						),
						'name'        => ( ( method_exists( $order, 'get_billing_first_name' ) ? $order->get_billing_first_name() : $order->billing_first_name ) ) . ' ' . ( ( method_exists( $order, 'get_billing_last_name' ) ? $order->get_billing_last_name() : $order->billing_last_name ) ),
					);
				} else {
					$fname  = ( isset( $user_obj->user_firstname ) ? $user_obj->user_firstname : '' );
					$lname  = ( isset( $user_obj->user_lastname ) ? $user_obj->user_lastname : '' );
					$params = array(
						'description' => 'Added manually',
						'name'        => $fname . ' ' . $lname,
						'email'       => $user_obj->user_email,
					);
				}
				if ( class_exists( '\SAHCFWC\Libraries\Stripe\Customer' ) ) {
					$response = \SAHCFWC\Libraries\Stripe\Customer::create( $params );
				} else {
					$response = array();
				}
				if ( empty( $response->id ) ) {
					return false;
				}
				return $response;
			}

			/**
			 * Thankyou page.
			 *
			 * @since 1.0.0
			 *
			 * @return void.
			 */
			public function thankyou_page() {
				if ( $this->instructions ) {
					echo wp_kses_post( wpautop( wptexturize( $this->instructions ) ) );
				}
			}

			/**
			 * Change payment complete order status to completed for COD orders.
			 *
			 * @since  3.1.0
			 *
			 * @param string         $status Current order status.
			 * @param int            $order_id Order ID.
			 * @param WC_Order|false $order Order object.
			 *
			 * @return string
			 */
			public function change_payment_complete_order_status( $status, $order_id = 0, $order = false ) {
				if ( $order && 'cod' === $order->get_payment_method() ) {
					$status = 'completed';
				}
				return $status;
			}

			/**
			 * Add content to the WC emails.
			 *
			 * @param WC_Order $order Order object.
			 * @param bool     $sent_to_admin Sent to admin.
			 * @param bool     $plain_text Email format: plain text or HTML.
			 */
			public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
				if ( $this->instructions && ! $sent_to_admin && $this->id === $order->get_payment_method() ) {
					echo wp_kses_post( wpautop( wptexturize( $this->instructions ) ) . PHP_EOL );
				}
			}

			/**
			 * Process payment from checkout .
			 *
			 * @since 1.0.0
			 *
			 * @return void.
			 */
			private function sahcfwc_set_stripe_secret() {
				$this->sahcfwc_stripe_shipping_address_status = sanitize_text_field( get_option( 'sahcfwc_stripe_shipping_address_status' ) );
				$this->sahcfwc_stripe_terms_condition_status  = sanitize_text_field( get_option( 'sahcfwc_stripe_terms_condition_status' ) );
				$this->sahcfwc_stripe_phone_num_status        = sanitize_text_field( get_option( 'sahcfwc_stripe_phone_num_status' ) );
				$this->sahcfwc_stripe_cancel_url              = get_option( 'sahcfwc_stripe_cancel_url' );
				$this->sahcfwc_stripe_secret                  = $this->sahcfwc_get_stripe_secret_key();
			}

		}

	}
}
