import { useState, useEffect } from 'react';
import {
	message,
	Typography,
	Divider,
	Popover,
	Alert,
	Tooltip,
	Badge,
} from 'antd';
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';
import { __ } from '@wordpress/i18n';
import '../../styles/checkoutSetting.scss';
import {
	ProCard,
	ProForm,
	ProFormSegmented,
	ProFormText,
	ProFormSelect,
	ProFormDigit,
	ProFormTextArea,
	ProFormRadio,
	ProFormList,
} from '@ant-design/pro-components';
const { Title, Paragraph, Text } = Typography;
const localeOptions = sahcfwc_customizations_localized_objects?.stripe_locale_options || [];
const normalizeLocale = ( value ) => localeOptions.some( ( option ) => option.value === value ) ? value : 'auto';
const normalizeQuantityMaximum = ( value ) => Number.isInteger( Number( value ) ) && Number( value ) >= 1 && Number( value ) <= 999999 ? Number( value ) : 10;

const CheckoutSetting = () => {
	const [ isApiProcessing, setIsApiProcessing ] = useState( false );
	const [ paymentMethodStatus, setPaymentMethodStatus ] = useState( false );
	const [ deleteTempOrderStatus, setDeleteTempOrderStatus ] =
		useState( false );
	const [ showSuccess, setShowSuccess ] = useState( false );
	const [ localeStatus, setLocaleStatus ] = useState( false );
	const [ quantityEnabled, setQuantityEnabled ] = useState( false );

	const copyText = () => {
		const textToCopy = document.querySelector(
			'.sahcfwc-short-code-copy'
		).textContent;
		if ( textToCopy ) {
			navigator.clipboard
				.writeText( textToCopy )
				.then( () => {
					setShowSuccess( true );
					setTimeout( () => {
						setShowSuccess( false );
					}, 3000 );
				} )
				.catch( ( err ) => {
					console.error( 'Failed to copy text: ', err );
				} );
		}
	};

	const handleClick = async ( formData ) => {
		setIsApiProcessing( true );
		message.config( { top: 100 } );
		try {
			await apiFetch( {
				path: '/wp/v2/settings/',
				method: 'POST',
				data: formData,
			} );
			message.success(
				__( 'Checkout settings saved.', 'sa-hosted-checkout-for-woocommerce' )
			);
			return true;
		} catch ( error ) {
			message.error(
				__( 'Unable to save checkout settings. Please try again.', 'sa-hosted-checkout-for-woocommerce' )
			);
			return false;
		} finally {
			setIsApiProcessing( false );
		}
	};

	const formInitialValues = {
		sahcfwc_stripe_shipping_address_status: '',
		sahcfwc_stripe_terms_condition_status: '',
		sahcfwc_stripe_phone_num_status: '',
		sahcfwc_stripe_adjustment_quantity: 'no',
		sahcfwc_stripe_quantity_maximum: 10,
		sahcfwc_stripe_delete_temp_order_status: '',
		sahcfwc_stripe_cancel_url: '',
		sahcfwc_stripe_locale_status: 'default',
		sahcfwc_stripe_locale: 'en',
	};

	return (
		<div className="sahcfwc-app-tab-content-container">
			<ProForm
				className="sahcfwc-checkout-settings-form"
				loading={ isApiProcessing }
				grid={ true }
				initialValues={ formInitialValues }
				layout="horizontal"
				submitter={ {
					searchConfig: {
						resetText: __(
							'reset',
							'sa-hosted-checkout-for-woocommerce'
						),
						submitText: __(
							'Submit',
							'sa-hosted-checkout-for-woocommerce'
						),
					},
					resetButtonProps: {
						style: {
							display: 'none',
						},
					},
					submitButtonProps: {},
				} }
				onFinish={ async ( values ) => {
					return await handleClick( values );
				} }
				request={ async ( params = {} ) => {
					return await apiFetch( {
						path: '/wp/v2/settings/',
						method: 'GET',
					} ).then( ( response ) => {
						setLocaleStatus( response?.sahcfwc_stripe_locale_status === 'custom' );
						setQuantityEnabled( response?.sahcfwc_stripe_adjustment_quantity === 'yes' );
						setPaymentMethodStatus(
							response?.sahcfwc_stripe_payment_method_status ===
								'manual'
								? true
								: false
						);
						setDeleteTempOrderStatus(
							response?.sahcfwc_stripe_delete_temp_order_status ===
								'yes'
								? true
								: false
						);
						return {
							...formInitialValues,
							sahcfwc_stripe_shipping_address_status:
								response?.sahcfwc_stripe_shipping_address_status,
							sahcfwc_stripe_terms_condition_status:
								response?.sahcfwc_stripe_terms_condition_status,
							sahcfwc_stripe_phone_num_status:
								response?.sahcfwc_stripe_phone_num_status,
							sahcfwc_stripe_adjustment_quantity: response?.sahcfwc_stripe_adjustment_quantity === 'yes' ? 'yes' : 'no',
							sahcfwc_stripe_quantity_maximum: normalizeQuantityMaximum( response?.sahcfwc_stripe_quantity_maximum ),
							sahcfwc_stripe_delete_temp_order_status:
								response?.sahcfwc_stripe_delete_temp_order_status,
							sahcfwc_stripe_cancel_url:
								response?.sahcfwc_stripe_cancel_url,
							sahcfwc_stripe_locale_status: response?.sahcfwc_stripe_locale_status === 'custom' ? 'custom' : 'default',
							sahcfwc_stripe_locale: normalizeLocale( response?.sahcfwc_stripe_locale ?? 'en' ),
						};
					} );
				} }
			>
				<ProCard
					title={ __(
						'Checkout Settings',
						'sa-hosted-checkout-for-woocommerce'
					) }
					headerBordered={ true }
				>
					<Title level={ 3 }>
						{ __(
							'Checkout Setting',
							'sa-hosted-checkout-for-woocommerce'
						) }
					</Title>

					<ProFormSegmented
						label={ __(
							'Enable Stripe Shipping Address',
							'sa-hosted-checkout-for-woocommerce'
						) }
						name="sahcfwc_stripe_shipping_address_status"
						tooltip={ __(
							'This option allows the display of the shipping address field on the Stripe checkout page.',
							'sa-hosted-checkout-for-woocommerce'
						) }
						request={ async () => [
							{
								label: __(
									'Enable',
									'sa-hosted-checkout-for-woocommerce'
								),
								value: 'yes',
							},
							{
								label: __(
									'Disable',
									'sa-hosted-checkout-for-woocommerce'
								),
								value: 'no',
							},
						] }
						onChange={ ( value ) => {} }
					/>
					<Badge />
					<ProFormSegmented
						label={ __( 'Allow quantity changes in Stripe Checkout', 'sa-hosted-checkout-for-woocommerce' ) }
						name="sahcfwc_stripe_adjustment_quantity"
						tooltip={ __( 'Let customers change quantities for eligible products before submitting Stripe Checkout.', 'sa-hosted-checkout-for-woocommerce' ) }
						request={ async () => [
							{ label: __( 'Enable', 'sa-hosted-checkout-for-woocommerce' ), value: 'yes' },
							{ label: __( 'Disable', 'sa-hosted-checkout-for-woocommerce' ), value: 'no' },
						] }
						onChange={ ( value ) => setQuantityEnabled( value === 'yes' ) }
						extra={ <Alert
							type="warning"
							showIcon
							message={ __( 'WooCommerce discount amounts, shipping charges, fees and taxes stay fixed when quantities change in Stripe Checkout. Coupon rules and other cart pricing rules are not recalculated. Enable only if these fixed charges are appropriate for your store.', 'sa-hosted-checkout-for-woocommerce' ) }
							description={ <>
								<p>{ __( 'Final purchased quantities and totals are synced to WooCommerce after checkout is submitted.', 'sa-hosted-checkout-for-woocommerce' ) }</p>
								<p>{ __( 'Requires a working Stripe webhook so orders sync even if the customer does not return to your store.', 'sa-hosted-checkout-for-woocommerce' ) }</p>
							</> }
						/> }
					/>
					{ quantityEnabled && <ProFormDigit
						label={ __( 'Maximum quantity per product', 'sa-hosted-checkout-for-woocommerce' ) }
						name="sahcfwc_stripe_quantity_maximum"
						width="md"
						tooltip={ __( 'Caps each eligible product at this quantity. A higher quantity already in the cart is preserved. Available stock may lower the limit. For stock-managed products, the allowed maximum is reserved while checkout is open.', 'sa-hosted-checkout-for-woocommerce' ) }
						fieldProps={ { min: 1, max: 999999, precision: 0, step: 1 } }
						rules={ [ { required: true, type: 'number', min: 1, max: 999999, message: __( 'Enter a whole number from 1 to 999999.', 'sa-hosted-checkout-for-woocommerce' ) } ] }
					/> }
					<ProFormSegmented
						label={ __(
							'Enable Stripe Terms & Services',
							'sa-hosted-checkout-for-woocommerce'
						) }
						name="sahcfwc_stripe_terms_condition_status"
						tooltip={ __(
							'This option allows the display of the terms and services checkbox on the Stripe checkout page.',
							'sa-hosted-checkout-for-woocommerce'
						) }
						request={ async () => [
							{
								label: __(
									'Enable',
									'sa-hosted-checkout-for-woocommerce'
								),
								value: 'yes',
							},
							{
								label: __(
									'Disable',
									'sa-hosted-checkout-for-woocommerce'
								),
								value: 'no',
							},
						] }
						onChange={ ( value ) => {} }
					/>
					<ProFormSegmented
						label={ __(
							'Enable Stripe Phone Number',
							'sa-hosted-checkout-for-woocommerce'
						) }
						name="sahcfwc_stripe_phone_num_status"
						tooltip={ __(
							'This option allows the display of  the phone number field on the Stripe checkout page.',
							'sa-hosted-checkout-for-woocommerce'
						) }
						request={ async () => [
							{
								label: __(
									'Enable',
									'sa-hosted-checkout-for-woocommerce'
								),
								value: 'yes',
							},
							{
								label: __(
									'Disable',
									'sa-hosted-checkout-for-woocommerce'
								),
								value: 'no',
							},
						] }
						onChange={ ( value ) => {} }
					/>

					<ProFormText
						width="md"
						label={ __(
							'Stripe Cancel URL',
							'sa-hosted-checkout-for-woocommerce'
						) }
						name="sahcfwc_stripe_cancel_url"
						tooltip={ __(
							'This option allows you to be redirected to a new specified URL after canceling an order on the Stripe checkout page.',
							'sa-hosted-checkout-for-woocommerce'
						) }
						placeholder={ window.location.hostname + '/page-name' }
						rules={ [
							{
								validator: async ( _, value ) => {
									if ( value ) {
										let url_validation = false;
										try {
											new URL( value );
											url_validation = true;
										} catch ( e ) {
											url_validation = false;
										}
										if ( url_validation === true ) {
											return Promise.resolve();
										} else {
											return Promise.reject(
												__(
											'Enter a valid URL.',
													'sa-hosted-checkout-for-woocommerce'
												)
											);
										}
									}
								},
							},
						] }
					/>

					{ showSuccess && (
						<Alert
							message={ __(
								'Text copied successfully!',
								'sa-hosted-checkout-for-woocommerce'
							) }
							type="success"
							showIcon
							className="show sahcfwc-copy-alert"
						/>
					) }

					<ProFormSegmented
						label={
							<span className="disabled-prem">
								{ __(
									'WooCommerce Custom ThankYou Page',
									'sa-hosted-checkout-for-woocommerce'
								) }{ ' ' }
								<Tooltip title="Premium" placement="top">
									<i className="sahcfwc-info-icon">i</i>
								</Tooltip>
							</span>
						}
						name="sahcfwc_woo_thankyou_custom_page_status"
						tooltip={ __(
							'This option allows the display of a custom thank-you page after placing an order on the Stripe checkout page.',
							'sa-hosted-checkout-for-woocommerce'
						) }
						disabled="true"
						request={ async () => [
							{
								label: __(
									'Default',
									'sa-hosted-checkout-for-woocommerce'
								),
								value: 'no',
							},
							{
								label: __(
									'Custom',
									'sa-hosted-checkout-for-woocommerce'
								),
								value: 'yes',
							},
						] }
						fieldProps={ {
							className: 'disabled-prem',
						} }
					/>
					<ProFormSegmented
						label={ __( 'Stripe Checkout Language', 'sa-hosted-checkout-for-woocommerce' ) }
						name="sahcfwc_stripe_locale_status"
						tooltip={ __( 'Use the customer\'s browser language or select a language for Stripe Checkout.', 'sa-hosted-checkout-for-woocommerce' ) }
						request={ async () => [
							{ label: __( 'Automatic (browser language)', 'sa-hosted-checkout-for-woocommerce' ), value: 'default' },
							{ label: __( 'Custom', 'sa-hosted-checkout-for-woocommerce' ), value: 'custom' },
						] }
						onChange={ ( value ) => setLocaleStatus( value === 'custom' ) }
					/>
					{ localeStatus && (
						<ProFormSelect
							width="md"
							name="sahcfwc_stripe_locale"
							label={ __( 'Language', 'sa-hosted-checkout-for-woocommerce' ) }
							options={ localeOptions }
							fieldProps={ { allowClear: false, showSearch: true, optionFilterProp: 'label' } }
							placeholder={ __( 'Select a language', 'sa-hosted-checkout-for-woocommerce' ) }
							rules={ [ { required: true, message: __( 'Select a language.', 'sa-hosted-checkout-for-woocommerce' ) } ] }
						/>
					) }

					<ProFormSegmented
						label={ __(
							'Delete Temporary Order',
							'sa-hosted-checkout-for-woocommerce'
						) }
						name="sahcfwc_stripe_delete_temp_order_status"
						tooltip={ __(
							'This option allows you to delete a temporary order when you return back to the Stripe checkout page.',
							'sa-hosted-checkout-for-woocommerce'
						) }
						request={ async () => [
							{
								label: __(
									'Enable',
									'sa-hosted-checkout-for-woocommerce'
								),
								value: 'yes',
							},
							{
								label: __(
									'Disable',
									'sa-hosted-checkout-for-woocommerce'
								),
								value: 'no',
							},
						] }
						onChange={ ( value ) => {
							setDeleteTempOrderStatus(
								value === 'yes' ? true : false
							);
						} }
					/>
					<Divider />
					<Title level={ 3 }>
						{ __(
							'Custom Text Messages',
							'sa-hosted-checkout-for-woocommerce'
						) }
					</Title>

					<ProFormTextArea
						disabled="true"
						width="md"
						label={
							<span className="disabled-prem">
								{ __(
									'Add message after Shipping address',
									'sa-hosted-checkout-for-woocommerce'
								) }{ ' ' }
								<Tooltip title="Premium" placement="top">
									<i className="sahcfwc-info-icon">i</i>
								</Tooltip>
							</span>
						}
						name="sahcfwc_stripe_after_shpping_address_text"
						tooltip={ __(
							'This option allows the display of customized text after the shipping address field on the Stripe checkout page.',
							'sa-hosted-checkout-for-woocommerce'
						) }
						placeholder={ __(
							'Enter text',
							'sa-hosted-checkout-for-woocommerce'
						) }
						fieldProps={ {
							className: 'disabled-prem',
						} }
					/>
					<ProFormTextArea
						disabled="true"
						width="md"
						label={
							<span className="disabled-prem">
								{ __(
									'Add message after submit',
									'sa-hosted-checkout-for-woocommerce'
								) }{ ' ' }
								<Tooltip title="Premium" placement="top">
									<i className="sahcfwc-info-icon">i</i>
								</Tooltip>
							</span>
						}
						name="sahcfwc_stripe_after_submit_text"
						tooltip={ __(
							'This option allows the display of customized text after the submit button on the Stripe checkout page.',
							'sa-hosted-checkout-for-woocommerce'
						) }
						placeholder={ __(
							'Enter text',
							'sa-hosted-checkout-for-woocommerce'
						) }
						fieldProps={ {
							className: 'disabled-prem',
						} }
					/>
					<ProFormTextArea
						disabled="true"
						width="md"
						label={
							<span className="disabled-prem">
								{ __(
									'Add message before submit',
									'sa-hosted-checkout-for-woocommerce'
								) }{ ' ' }
								<Tooltip title="Premium" placement="top">
									<i className="sahcfwc-info-icon">i</i>
								</Tooltip>
							</span>
						}
						name="sahcfwc_stripe_befour_text"
						tooltip={ __(
							'This option allows the display of customized text before the submit button on the Stripe checkout page.',
							'sa-hosted-checkout-for-woocommerce'
						) }
						placeholder={ __(
							'Enter text',
							'sa-hosted-checkout-for-woocommerce'
						) }
						fieldProps={ {
							className: 'disabled-prem',
						} }
					/>
					<ProFormTextArea
						disabled="true"
						width="md"
						label={
							<span className="disabled-prem">
								{ __(
									'Customize terms and service text',
									'sa-hosted-checkout-for-woocommerce'
								) }{ ' ' }
								<Tooltip title="Premium" placement="top">
									<i className="sahcfwc-info-icon">i</i>
								</Tooltip>
							</span>
						}
						name="sahcfwc_stripe_customize_terms_service_text"
						tooltip={ __(
							'This option allows the display of customized terms and service text on the Stripe checkout page.',
							'sa-hosted-checkout-for-woocommerce'
						) }
						placeholder={ __(
							'Enter text',
							'sa-hosted-checkout-for-woocommerce'
						) }
						fieldProps={ {
							className: 'disabled-prem',
						} }
					/>
					<Divider />
					<Title level={ 3 }>
						{ __(
							'Stripe Custom Field Options',
							'sa-hosted-checkout-for-woocommerce'
						) }
					</Title>

					<ProFormSegmented
						disabled="true"
						label={
							<span className="disabled-prem">
								{ __(
									'Stripe Custom field options',
									'sa-hosted-checkout-for-woocommerce'
								) }{ ' ' }
								<Tooltip title="Premium" placement="top">
									<i className="sahcfwc-info-icon">i</i>
								</Tooltip>
							</span>
						}
						name="sahcfwc_stripe_custom_field_status"
						tooltip={ __(
							'This option allows the display of the custom field on the Stripe checkout page.',
							'sa-hosted-checkout-for-woocommerce'
						) }
						request={ async () => [
							{
								label: __(
									'Enable',
									'sa-hosted-checkout-for-woocommerce'
								),
								value: 'yes',
							},
							{
								label: __(
									'Disable',
									'sa-hosted-checkout-for-woocommerce'
								),
								value: 'no',
							},
						] }
						fieldProps={ {
							className: 'disabled-prem',
						} }
					/>
				</ProCard>
			</ProForm>
		</div>
	);
};

export default CheckoutSetting;
