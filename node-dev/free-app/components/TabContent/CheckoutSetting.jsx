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
	ProFormTextArea,
	ProFormRadio,
	ProFormList,
} from '@ant-design/pro-components';
const { Title, Paragraph, Text } = Typography;

const CheckoutSetting = () => {
	const [ isApiProcessing, setIsApiProcessing ] = useState( false );
	const [ paymentMethodStatus, setPaymentMethodStatus ] = useState( false );
	const [ deleteTempOrderStatus, setDeleteTempOrderStatus ] =
		useState( false );
	const [ showSuccess, setShowSuccess ] = useState( false );

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

	const handleClick = ( formData ) => {
		/**
		 * Set API Processing State
		 */
		setIsApiProcessing( true );
		apiFetch( {
			path: '/wp/v2/settings/',
			method: 'POST',
			data: formData,
		} ).then( ( response ) => {
			message.config( { top: 100 } );
			message.success(
				__(
					'Congratulations! Your settings have been successfully saved.',
					'sa-hosted-checkout-for-woocommerce'
				)
			);

			/**
			 * Set API Processing State
			 */
			setIsApiProcessing( false );
		} );
	};

	const formInitialValues = {
		sahcfwc_stripe_shipping_address_status: '',
		sahcfwc_stripe_terms_condition_status: '',
		sahcfwc_stripe_phone_num_status: '',
		sahcfwc_stripe_payment_method_status: '',
		sahcfwc_stripe_delete_temp_order_status: '',
		sahcfwc_stripe_cancel_url: '',
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
					handleClick( values );
				} }
				request={ async ( params = {} ) => {
					return await apiFetch( {
						path: '/wp/v2/settings/',
						method: 'GET',
					} ).then( ( response ) => {
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
							sahcfwc_stripe_payment_method_status:
								response?.sahcfwc_stripe_payment_method_status,
							sahcfwc_stripe_delete_temp_order_status:
								response?.sahcfwc_stripe_delete_temp_order_status,
							sahcfwc_stripe_cancel_url:
								response?.sahcfwc_stripe_cancel_url,
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
						// label={ __( 'Adjustable Quantity Status', 'sa-hosted-checkout-for-woocommerce' ) }
						label={
							<span className="disabled-prem">
								{ __(
									'Adjustable Quantity Status',
									'sa-hosted-checkout-for-woocommerce'
								) }{ ' ' }
								<Tooltip title="Premium" placement="top">
									<i className="sahcfwc-info-icon">i</i>
								</Tooltip>
							</span>
						}
						name="sahcfwc_stripe_adjustment_quantity"
						tooltip={ __(
							'This option allows to adjust the quantity of products on the Stripe checkout page.',
							'sa-hosted-checkout-for-woocommerce'
						) }
						disabled="true"
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
						fieldProps={ {
							className: 'disabled-prem',
						} }
					/>
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
								required: true,
							},
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
													'Please enter a valid URL ',
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
						label={
							<span className="disabled-prem">
								{ __(
									'Stripe Checkout Language',
									'sa-hosted-checkout-for-woocommerce'
								) }{ ' ' }
								<Tooltip title="Premium" placement="top">
									<i className="sahcfwc-info-icon">i</i>
								</Tooltip>
							</span>
						}
						name="sahcfwc_stripe_locale_status"
						tooltip={ __(
							'This option allows you to choose the language as either custom or default on the Stripe checkout page.',
							'sa-hosted-checkout-for-woocommerce'
						) }
						disabled="true"
						request={ async () => [
							{
								label: __(
									'Default',
									'sa-hosted-checkout-for-woocommerce'
								),
								value: 'default',
							},
							{
								label: __(
									'Custom',
									'sa-hosted-checkout-for-woocommerce'
								),
								value: 'customo',
							},
						] }
						fieldProps={ {
							className: 'disabled-prem',
						} }
					/>

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
