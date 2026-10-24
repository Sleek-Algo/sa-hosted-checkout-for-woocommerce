import { useEffect, useState } from 'react';
import { Alert, Button, Card, Form, Input, Radio, Select, Space, Spin, Typography, message } from 'antd';
import apiFetch from '@wordpress/api-fetch';
import { __, sprintf } from '@wordpress/i18n';

const defaults = {
	sahcfwc_stripe_checkout_status: 'no',
	sahcfwc_stripe_integration_mode: 'test-mode',
	sahcfwc_stripe_admin_test_mode_status: 'no',
	sahcfwc_api_key_type: 'standard',
	sahcfwc_stripe_test_secret_key: '',
	sahcfwc_stripe_live_secret_key: '',
	sahcfwc_restricted_test_key: '',
	sahcfwc_restricted_live_key: '',
	sahcfwc_stripe_test_public_key: '',
	sahcfwc_stripe_live_public_key: '',
	sahcfwc_stripe_webhook_key: '',
	sahcfwc_stripe_checkout_type: 'hosted',
	sahcfwc_stripe_embedded_page_id: 0,
	sahcfwc_stripe_branding_enabled: 'no',
	sahcfwc_stripe_branding_settings: {
		display_name: '', logo_url: '', background_color: '', button_color: '', font_family: '', border_style: '',
	},
};
const fonts = [
	'be_vietnam_pro', 'bitter', 'chakra_petch', 'default', 'hahmlet',
	'inconsolata', 'inter', 'lato', 'lora', 'm_plus_1_code', 'montserrat',
	'noto_sans', 'noto_sans_jp', 'noto_serif', 'nunito', 'open_sans',
	'pridi', 'pt_sans', 'pt_serif', 'raleway', 'roboto', 'roboto_slab',
	'source_sans_pro', 'titillium_web', 'ubuntu_mono', 'zen_maru_gothic',
];

function normalize( response ) {
	const values = {};
	Object.keys( defaults ).forEach( ( key ) => {
		values[ key ] = response[ key ] === undefined ? defaults[ key ] : response[ key ];
	} );
	values.sahcfwc_stripe_branding_settings = {
		...defaults.sahcfwc_stripe_branding_settings,
		...( response.sahcfwc_stripe_branding_settings || {} ),
	};
	if ( values.sahcfwc_stripe_checkout_type === 'embedded' ) {
		values.sahcfwc_stripe_checkout_type = 'embedded_popup';
	}
	return values;
}

export default function StripeSettings( { premium = false, setCheckoutType } ) {
	const [ form ] = Form.useForm();
	const [ loading, setLoading ] = useState( true );
	const [ saving, setSaving ] = useState( false );
	const [ loadError, setLoadError ] = useState( false );
	const [ pages, setPages ] = useState( [] );
	const [ pageError, setPageError ] = useState( false );
	const enabled = Form.useWatch( 'sahcfwc_stripe_checkout_status', form ) === 'yes';
	const mode = Form.useWatch( 'sahcfwc_stripe_integration_mode', form ) || 'test-mode';
	const keyType = Form.useWatch( 'sahcfwc_api_key_type', form ) || 'standard';
	const checkoutType = Form.useWatch( 'sahcfwc_stripe_checkout_type', form ) || 'hosted';
	const branding = Form.useWatch( 'sahcfwc_stripe_branding_enabled', form ) === 'yes';
	const adminTestSetting = Form.useWatch( 'sahcfwc_stripe_admin_test_mode_status', form );
	const adminTesting = premium && adminTestSetting === 'yes';
	const activeTest = mode === 'test-mode' || adminTesting;
	const activeLive = mode === 'live-mode';
	const embedded = checkoutType !== 'hosted';
	const config = window.sahcfwc_customizations_localized_objects || {};
	const yesNo = [
		{ value: 'yes', label: __( 'Enable', 'sa-hosted-checkout-for-woocommerce' ) },
		{ value: 'no', label: __( 'Disable', 'sa-hosted-checkout-for-woocommerce' ) },
	];
	const inherit = { value: '', label: __( 'Use Stripe Dashboard setting', 'sa-hosted-checkout-for-woocommerce' ) };

	async function load() {
		setLoading( true );
		setLoadError( false );
		try {
			const response = await apiFetch( { path: '/wp/v2/settings/' } );
			const values = normalize( response );
			form.setFieldsValue( values );
			if ( setCheckoutType ) {
				setCheckoutType( values.sahcfwc_stripe_checkout_type );
			}
		} catch ( error ) {
			setLoadError( true );
		} finally {
			setLoading( false );
		}
	}
	useEffect( () => {
		load();
		let active = true;
		apiFetch( { path: '/wp/v2/pages?context=edit&status=publish&per_page=100&_fields=id,title' } )
			.then( ( response ) => {
				if ( active && Array.isArray( response ) ) {
					setPages( response.map( ( page ) => ( {
						value: page.id,
						label: page.title.raw || page.title.rendered || String( page.id ),
					} ) ) );
				}
			} ).catch( () => {
				if ( active ) {
					setPageError( true );
				}
			} );
		return () => { active = false; };
	}, [] );

	async function save( values ) {
		setSaving( true );
		try {
			const data = normalize( { ...form.getFieldsValue( true ), ...values } );
			if ( ! premium ) {
				delete data.sahcfwc_stripe_admin_test_mode_status;
			}
			// Keep inactive credentials intact when changing mode or key type.
			const response = await apiFetch( { path: '/wp/v2/settings/', method: 'POST', data } );
			const saved = normalize( response );
			form.setFieldsValue( saved );
			if ( setCheckoutType ) {
				setCheckoutType( saved.sahcfwc_stripe_checkout_type );
			}
			if ( Number( data.sahcfwc_stripe_embedded_page_id ) > 0 && Number( saved.sahcfwc_stripe_embedded_page_id ) !== Number( data.sahcfwc_stripe_embedded_page_id ) ) {
				message.error( __( 'The selected payment page could not be used. Choose a published, unprotected page containing [sahcfwc_checkout], not your cart, checkout or account page.', 'sa-hosted-checkout-for-woocommerce' ) );
			} else if ( saved.sahcfwc_stripe_checkout_type !== data.sahcfwc_stripe_checkout_type ||
				saved.sahcfwc_stripe_checkout_status !== data.sahcfwc_stripe_checkout_status ) {
				message.error( __( 'Some settings could not be saved. Check your Stripe keys and selected payment page.', 'sa-hosted-checkout-for-woocommerce' ) );
			} else if ( data.sahcfwc_stripe_branding_enabled === 'yes' && Object.keys( defaults.sahcfwc_stripe_branding_settings ).some( ( key ) => String( saved.sahcfwc_stripe_branding_settings[ key ] || '' ).trim().toLowerCase() !== String( data.sahcfwc_stripe_branding_settings[ key ] || '' ).trim().toLowerCase() ) ) {
				message.error( __( 'Some branding settings were not accepted. Check the public logo URL and supported color, font and shape values.', 'sa-hosted-checkout-for-woocommerce' ) );
			} else {
				message.success( __( 'Stripe settings saved.', 'sa-hosted-checkout-for-woocommerce' ) );
			}
		} catch ( error ) {
			// Do not echo API errors that might include a credential or request payload.
			message.error( __( 'Unable to save Stripe settings. Check the values and try again.', 'sa-hosted-checkout-for-woocommerce' ) );
		} finally {
			setSaving( false );
		}
	}

	function credentialRules( prefix, required ) {
		return [ {
			validator: async ( rule, value ) => {
				if ( ! value && required ) {
					// translators: %s is the required Stripe API key prefix.
					throw new Error( sprintf( __( 'Enter a key beginning with %s.', 'sa-hosted-checkout-for-woocommerce' ), prefix ) );
				}
				if ( value && ! new RegExp( '^' + prefix + '[A-Za-z0-9]+$' ).test( value ) ) {
					// translators: %s is the required Stripe API key prefix.
					throw new Error( sprintf( __( 'This field only accepts a key beginning with %s.', 'sa-hosted-checkout-for-woocommerce' ), prefix ) );
				}
			},
		} ];
	}

	function credentialFields( environment, required ) {
		const test = environment === 'test';
		const label = test ? __( 'Test', 'sa-hosted-checkout-for-woocommerce' ) : __( 'Live', 'sa-hosted-checkout-for-woocommerce' );
		const secretName = keyType === 'restricted' ? 'sahcfwc_restricted_' + environment + '_key' :
			'sahcfwc_stripe_' + environment + '_secret_key';
		const secretPrefix = ( keyType === 'restricted' ? 'rk_' : 'sk_' ) + environment + '_';
		return (
			<div key={ environment }>
				<Form.Item name={ secretName }
					label={ sprintf( keyType === 'restricted' ?
						// translators: %s is Test or Live, the Stripe environment.
						__( '%s restricted key', 'sa-hosted-checkout-for-woocommerce' ) :
						// translators: %s is Test or Live, the Stripe environment.
						__( '%s secret key', 'sa-hosted-checkout-for-woocommerce' ), label ) }
					rules={ credentialRules( secretPrefix, enabled && required ) }>
					<Input.Password autoComplete="off" spellCheck={ false } />
				</Form.Item>
				<Form.Item name={ 'sahcfwc_stripe_' + environment + '_public_key' }
					label={ sprintf(
						// translators: %s is Test or Live, the Stripe environment.
						__( '%s publishable key', 'sa-hosted-checkout-for-woocommerce' ), label ) }
					rules={ credentialRules( 'pk_' + environment + '_', enabled && embedded && required ) }
					extra={ __( 'Required for embedded page and popup checkout. Use the publishable key from the same Stripe account and mode as your secret or restricted key.', 'sa-hosted-checkout-for-woocommerce' ) }>
					<Input autoComplete="off" spellCheck={ false } />
				</Form.Item>
			</div>
		);
	}

	if ( loadError ) {
		return <Alert type="error" showIcon message={ __( 'Unable to load Stripe settings.', 'sa-hosted-checkout-for-woocommerce' ) }
			action={ <Button onClick={ load }>{ __( 'Try again', 'sa-hosted-checkout-for-woocommerce' ) }</Button> } />;
	}
	return (
		<div className="sahcfwc-app-tab-content-container">
			<Spin spinning={ loading }>
				<Form form={ form } initialValues={ defaults } onFinish={ save }
					layout="vertical" className="sahcfwc-stripe-settings-form" disabled={ saving || loading }
					onValuesChange={ ( changed ) => {
						if ( changed.sahcfwc_stripe_checkout_type && setCheckoutType ) {
							setCheckoutType( changed.sahcfwc_stripe_checkout_type );
						}
					} }>
					<Card title={ __( 'Stripe settings', 'sa-hosted-checkout-for-woocommerce' ) }>
						<Form.Item name="sahcfwc_stripe_checkout_status" label={ __( 'Enable Stripe Checkout', 'sa-hosted-checkout-for-woocommerce' ) }>
							<Radio.Group options={ yesNo } optionType="button" />
						</Form.Item>
						<Form.Item name="sahcfwc_stripe_integration_mode" label={ __( 'Stripe mode', 'sa-hosted-checkout-for-woocommerce' ) }>
							<Radio.Group options={ [
								{ value: 'test-mode', label: __( 'Test mode', 'sa-hosted-checkout-for-woocommerce' ) },
								{ value: 'live-mode', label: __( 'Live mode', 'sa-hosted-checkout-for-woocommerce' ) },
							] } optionType="button" />
						</Form.Item>
						<Form.Item name="sahcfwc_api_key_type" label={ __( 'API key type', 'sa-hosted-checkout-for-woocommerce' ) }>
							<Radio.Group options={ [
								{ value: 'standard', label: __( 'Secret key', 'sa-hosted-checkout-for-woocommerce' ) },
								{ value: 'restricted', label: __( 'Restricted key', 'sa-hosted-checkout-for-woocommerce' ) },
							] } />
						</Form.Item>
						{ premium && <Form.Item name="sahcfwc_stripe_admin_test_mode_status"
							label={ __( 'Administrator-only test mode', 'sa-hosted-checkout-for-woocommerce' ) }
							extra={ __( 'Administrators use test credentials while customers continue using the selected Stripe mode.', 'sa-hosted-checkout-for-woocommerce' ) }>
							<Radio.Group options={ yesNo } />
						</Form.Item> }
						<Form.Item name="sahcfwc_stripe_checkout_type" label={ __( 'Checkout experience', 'sa-hosted-checkout-for-woocommerce' ) }>
							<Select options={ [
								{ value: 'hosted', label: __( 'Hosted — redirect to Stripe', 'sa-hosted-checkout-for-woocommerce' ) },
								{ value: 'embedded_page', label: __( 'Embedded — payment page', 'sa-hosted-checkout-for-woocommerce' ) },
								{ value: 'embedded_popup', label: __( 'Embedded — popup', 'sa-hosted-checkout-for-woocommerce' ) },
							] } />
						</Form.Item>
						{ embedded && <>
							<Form.Item name="sahcfwc_stripe_embedded_page_id" label={ __( 'Embedded payment page', 'sa-hosted-checkout-for-woocommerce' ) }
								extra={ __( 'Use a dedicated page containing [sahcfwc_checkout]. Automatic creates one when you save. Popup checkout also uses this page for order-payment links and redirects.', 'sa-hosted-checkout-for-woocommerce' ) }>
								<Select showSearch optionFilterProp="label" options={ [
									{ value: 0, label: __( 'Automatic — create a payment page', 'sa-hosted-checkout-for-woocommerce' ) }, ...pages,
								] } />
							</Form.Item>
							{ pageError && <Alert type="warning" showIcon style={ { marginBottom: 20 } }
								message={ __( 'The page list could not be loaded. Use Automatic or reload settings to select an existing page.', 'sa-hosted-checkout-for-woocommerce' ) } /> }
							<Alert type="info" showIcon style={ { marginBottom: 20 } }
								message={ __( 'Embedded Checkout requires HTTPS in production. Some payment methods still redirect customers for authorization. Apple Pay and Google Pay also require Stripe domain registration and an eligible browser or device.', 'sa-hosted-checkout-for-woocommerce' ) } />
						</> }
						{ activeTest && credentialFields( 'test', true ) }
						{ activeLive && credentialFields( 'live', true ) }
						<Form.Item label={ __( 'Webhook URL', 'sa-hosted-checkout-for-woocommerce' ) }>
							<Input readOnly value={ config.webhook_URL || '' } />
							<Typography.Paragraph copyable={ { text: config.webhook_URL || '' } }>
								{ __( 'Copy this URL into your Stripe webhook destination.', 'sa-hosted-checkout-for-woocommerce' ) }
							</Typography.Paragraph>
						</Form.Item>
						<Form.Item name="sahcfwc_stripe_webhook_key" label={ __( 'Webhook signing secret', 'sa-hosted-checkout-for-woocommerce' ) }
							rules={ [ {
								required: enabled,
								message: __( 'Enter the webhook signing secret.', 'sa-hosted-checkout-for-woocommerce' ),
							}, {
								pattern: /^whsec_[A-Za-z0-9]+$/,
								message: __( 'The webhook signing secret must begin with whsec_.', 'sa-hosted-checkout-for-woocommerce' ),
							} ] }>
							<Input.Password autoComplete="off" spellCheck={ false } />
						</Form.Item>
					</Card>
					<Card title={ __( 'Checkout branding', 'sa-hosted-checkout-for-woocommerce' ) } style={ { marginTop: 20 } }>
						<Form.Item name="sahcfwc_stripe_branding_enabled" label={ __( 'Customize checkout branding', 'sa-hosted-checkout-for-woocommerce' ) }
							extra={ __( 'Available in both free and premium. Overrides apply only to new checkout sessions and do not change your Stripe account settings.', 'sa-hosted-checkout-for-woocommerce' ) }>
							<Radio.Group options={ yesNo } />
						</Form.Item>
						{ branding && <>
							<Alert type="info" showIcon style={ { marginBottom: 20 } }
								message={ __( 'Leave any field empty to use your Stripe Dashboard branding. Receipts and invoices retain Stripe account branding. Checkout appearance is limited to the controls supported by Stripe.', 'sa-hosted-checkout-for-woocommerce' ) } />
							<Form.Item name={ [ 'sahcfwc_stripe_branding_settings', 'display_name' ] } label={ __( 'Checkout display name', 'sa-hosted-checkout-for-woocommerce' ) }>
								<Input maxLength={ 40 } />
							</Form.Item>
							<Form.Item name={ [ 'sahcfwc_stripe_branding_settings', 'logo_url' ] } label={ __( 'Hosted checkout logo URL', 'sa-hosted-checkout-for-woocommerce' ) }
								extra={ __( 'Logo overrides apply to hosted checkout only. Embedded page and popup checkout do not support this logo override. Enter a public HTTPS PNG, JPEG or SVG image URL; WebP, localhost, private network and password-protected images are not supported. Your saved hosted logo is kept when switching checkout modes.', 'sa-hosted-checkout-for-woocommerce' ) }
								rules={ [ { validator: async ( rule, value ) => {
									if ( ! value ) { return; }
									try {
										const url = new URL( value );
										if ( url.protocol === 'https:' && ! url.username && ! url.password &&
											! /^(?:localhost|127\.|0\.|10\.|192\.168\.|169\.254\.|\[)/i.test( url.hostname ) &&
											! /\.(?:local|localhost|internal)$/i.test( url.hostname ) &&
											/\.(?:png|jpg|jpeg|jfif|pjpeg|pjp|svg)$/i.test( url.pathname ) ) { return; }
									} catch ( error ) { /* Use the same safe validation message. */ }
									throw new Error( __( 'Enter a public HTTPS PNG, JPEG or SVG image URL. WebP and other image formats are not supported.', 'sa-hosted-checkout-for-woocommerce' ) );
								} } ] }>
								<Input type="url" placeholder="https://example.com/logo.png" />
							</Form.Item>
							{ [ [ 'background_color', __( 'Background color', 'sa-hosted-checkout-for-woocommerce' ) ], [ 'button_color', __( 'Button color', 'sa-hosted-checkout-for-woocommerce' ) ] ].map( ( [ key, label ] ) => (
								<Form.Item key={ key } name={ [ 'sahcfwc_stripe_branding_settings', key ] } label={ label }
									extra={ __( 'Use a six-digit hex color, or leave empty to inherit.', 'sa-hosted-checkout-for-woocommerce' ) }
									rules={ [ { pattern: /^#[0-9a-fA-F]{6}$/, message: __( 'Enter a color such as #176BFA.', 'sa-hosted-checkout-for-woocommerce' ) } ] }>
									<Input placeholder="#176BFA" maxLength={ 7 } />
								</Form.Item>
							) ) }
							<Form.Item name={ [ 'sahcfwc_stripe_branding_settings', 'font_family' ] } label={ __( 'Font', 'sa-hosted-checkout-for-woocommerce' ) }>
								<Select options={ [ inherit, ...fonts.map( ( value ) => ( { value, label: value.replace( /_/g, ' ' ).replace( /\b\w/g, ( letter ) => letter.toUpperCase() ) } ) ) ] } />
							</Form.Item>
							<Form.Item name={ [ 'sahcfwc_stripe_branding_settings', 'border_style' ] } label={ __( 'Corner shape', 'sa-hosted-checkout-for-woocommerce' ) }>
								<Select options={ [ inherit,
									{ value: 'rounded', label: __( 'Rounded', 'sa-hosted-checkout-for-woocommerce' ) },
									{ value: 'rectangular', label: __( 'Rectangular', 'sa-hosted-checkout-for-woocommerce' ) },
									{ value: 'pill', label: __( 'Pill', 'sa-hosted-checkout-for-woocommerce' ) },
								] } />
							</Form.Item>
						</> }
					</Card>
					<Space style={ { padding: 20 } }>
						<Button type="primary" htmlType="submit" loading={ saving }>{ __( 'Save Stripe settings', 'sa-hosted-checkout-for-woocommerce' ) }</Button>
					</Space>
				</Form>
			</Spin>
		</div>
	);
}

