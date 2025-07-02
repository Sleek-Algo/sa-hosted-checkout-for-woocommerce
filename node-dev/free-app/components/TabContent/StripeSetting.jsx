import { useState, useEffect } from 'react';
import { message, Alert, Space, Tooltip, Typography } from 'antd';
import {
	ProCard,
	ProForm,
	ProFormText,
	ProFormSegmented,
} from '@ant-design/pro-components';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import { generatShortkey } from '../Helpers';
import '../../styles/stripeSetting.scss';

const StripeSetting = () => {
	/**
	 * States
	 */
	const formRef = ProForm.useForm();
	const [ stripeTestModeStatus, setStripeTestModeStatus ] = useState( true );
	const [ stripeLiveModeStatus, setStripeLiveModeStatus ] = useState( false );
	const [ isApiProcessing, setIsApiProcessing ] = useState( false );
	const [ shortTestSecretKey, setShortTestSecretKey ] = useState( '' );
	const [ shortLiveSecretKey, setShortLiveSecretKey ] = useState( '' );
	const [ liveSecretKey, setLiveSecretKey ] = useState( '' );
	const [ testSecretKey, setTestSecretKey ] = useState( '' );

	const [ webhookKey, setWebhookKey ] = useState( '' );
	const [ testSceretRequired, setTestSceretRequired ] = useState( null );
	const [ liveSceretRequired, setLiveSceretRequired ] = useState( null );
	const [ webBookSceretRequired, setWebBookSceretRequired ] =
		useState( null );
	const [ stripeCheckoutStatus, setStripeCheckoutStatus ] = useState( false );
	const [ isProPlan, setIsProPlan ] = useState(
		sahcfwc_customizations_localized_objects.is_free_plan
	);

	// Change this state to track key type
    const [apiKeyType, setApiKeyType] = useState('standard'); // 'standard' or 'restricted'
	const [useRestrictedKey, setUseRestrictedKey] = useState(false);
	const [restrictedTestKey, setRestrictedTestKey] = useState('');
	const [restrictedLiveKey, setRestrictedLiveKey] = useState('');
	const [shortRestrictedTestKey, setShortRestrictedTestKey] = useState('');
	const [shortRestrictedLiveKey, setShortRestrictedLiveKey] = useState('');

	const handleClick = ( formData ) => {
		/**
		 * Set API Processing State
		 */
		setIsApiProcessing( true );
		let update_val =
			stripeCheckoutStatus === false
				? __( 'yes', 'sa-hosted-checkout-for-woocommerce' )
				: __( 'no', 'sa-hosted-checkout-for-woocommerce' );
		formData.sahcfwc_stripe_checkout_status = update_val;

		// Only include the keys for the selected type
        formData.sahcfwc_api_key_type = apiKeyType;
        if (apiKeyType === 'standard') {
            formData.sahcfwc_restricted_test_key = '';
            formData.sahcfwc_restricted_live_key = '';
        } else {
            formData.sahcfwc_stripe_test_secret_key = '';
            formData.sahcfwc_stripe_live_secret_key = '';
        }

		apiFetch( {
			path: '/wp/v2/settings/',
			method: 'POST',
			data: formData,
		} ).then( ( response ) => {
			message.config( {
				top: 100,
			} );
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

		// }
	};

	const formInitialValues = {
		sahcfwc_stripe_checkout_status: '',
		sahcfwc_stripe_integration_mode: __(
			'test-mode',
			'sa-hosted-checkout-for-woocommerce'
		),
		sahcfwc_stripe_test_secret_key: '',
		sahcfwc_stripe_live_secret_key: '',
		sahcfwc_stripe_webhook_url:
			sahcfwc_customizations_localized_objects.webhook_URL,
		sahcfwc_stripe_webhook_key: '',
		// New
		sahcfwc_api_key_type: 'standard',
		sahcfwc_restricted_test_key: '',
        sahcfwc_restricted_live_key: '',
	};

	return (
		<div className="sahcfwc-app-tab-content-container">
			{ stripeCheckoutStatus === true && (
				<Space direction="vertical" style={ { width: '100%' } }>
					<Alert
						type="info"
						message={ __(
							'Please note: The Stripe checkout functionality will only work if the Enable Stripe Checkout option is enabled.',
							'sa-hosted-checkout-for-woocommerce'
						) }
						banner
					/>
				</Space>
			) }
			<ProForm
				formRef={ formRef }
				className="sahcfwc-stripe-settings-form"
				loading={ isApiProcessing }
				initialValues={ formInitialValues }
				grid={ true }
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
						setStripeCheckoutStatus(
							response?.sahcfwc_stripe_checkout_status === 'yes'
								? false
								: true
						);
						setStripeLiveModeStatus(
							response?.sahcfwc_stripe_integration_mode ===
								'live-mode'
								? false
								: true
						);
						setLiveSceretRequired(
							response?.sahcfwc_stripe_integration_mode ===
								'live-mode'
								? true
								: false
						);
						setStripeTestModeStatus(
							response?.sahcfwc_stripe_integration_mode ===
								'test-mode'
								? false
								: true
						);
						setTestSceretRequired(
							response?.sahcfwc_stripe_integration_mode ===
								'test-mode'
								? true
								: false
						);
						setShortTestSecretKey(
							generatShortkey(
								response?.sahcfwc_stripe_test_secret_key
							)
						);
						setShortLiveSecretKey(
							generatShortkey(
								response?.sahcfwc_stripe_live_secret_key
							)
						);
						setLiveSecretKey(
							response?.sahcfwc_stripe_live_secret_key
						);
						setTestSecretKey(
							response?.sahcfwc_stripe_test_secret_key
						);
						setWebhookKey(
							generatShortkey(
								response?.sahcfwc_stripe_webhook_key
							)
						);

						// Add these lines for API key type
						setApiKeyType(response?.sahcfwc_api_key_type || 'standard');
						// Update restricted key states
						setRestrictedTestKey(response?.sahcfwc_restricted_test_key || '');
						setRestrictedLiveKey(response?.sahcfwc_restricted_live_key || '');
						setShortRestrictedTestKey(
							generatShortkey(response?.sahcfwc_restricted_test_key)
						);
						setShortRestrictedLiveKey(
							generatShortkey(response?.sahcfwc_restricted_live_key)
						);

						return {
							...formInitialValues,
							sahcfwc_stripe_checkout_status:
								response?.sahcfwc_stripe_checkout_status,
							sahcfwc_stripe_integration_mode:
								response?.sahcfwc_stripe_integration_mode,
							sahcfwc_stripe_test_secret_key:
								response?.sahcfwc_stripe_test_secret_key,
							sahcfwc_stripe_live_secret_key:
								response?.sahcfwc_stripe_live_secret_key,
							sahcfwc_stripe_webhook_key:
								response?.sahcfwc_stripe_webhook_key,
							sahcfwc_api_key_type: 
								response?.sahcfwc_api_key_type || 'standard',
							sahcfwc_restricted_test_key: 
								response?.sahcfwc_restricted_test_key || '',
            				sahcfwc_restricted_live_key: 
								response?.sahcfwc_restricted_live_key || ''
						};
					} );
				} }
			>
				<ProCard
					title={ __(
						'Stripe Settings',
						'sa-hosted-checkout-for-woocommerce'
					) }
					headerBordered={ true }
					extra={
						<div className="sahcfwc-card-header-btn ">
							<ProFormSegmented
								label={ __(
									'Enable Stripe Checkout',
									'sa-hosted-checkout-for-woocommerce'
								) }
								name="sahcfwc_stripe_checkout_status"
								tooltip={ __(
									'This option allows you to activate  Stripe Checkout functionality on your website.',
									'sa-hosted-checkout-for-woocommerce'
								) }
								fieldProps={ {
									style: {
										marginBottom: '0px',
									},
								} }
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
									setStripeCheckoutStatus(
										value === 'yes' ? false : true
									);
								} }
								// rules={ [
								// 	{
								// 		validator: async ( _, value ) => {
								// 			if ( value === 'yes' ) {
								// 				if (
								// 					liveSceretRequired ===
								// 						true &&
								// 					testSceretRequired === false
								// 				) {
								// 					if ( ! liveSecretKey ) {
								// 						return Promise.reject(
								// 							__(
								// 								'Please Enter the Live Secret Key',
								// 								'sa-hosted-checkout-for-woocommerce'
								// 							)
								// 						);
								// 					}
								// 				} else if (
								// 					liveSceretRequired ===
								// 						false &&
								// 					testSceretRequired === true
								// 				) {
								// 					if ( ! testSecretKey ) {
								// 						return Promise.reject(
								// 							__(
								// 								'Please Enter the Test Secret Key',
								// 								'sa-hosted-checkout-for-woocommerce'
								// 							)
								// 						);
								// 					}
								// 				}
								// 				return Promise.resolve();
								// 			} else {
								// 				return Promise.resolve();
								// 			}
								// 		},
								// 	},
								// ] }
								rules={[
									{
										validator: async (_, value) => {
											if (value === 'yes') {
												if (liveSceretRequired === true && testSceretRequired === false) {
													if (apiKeyType === 'standard' && !liveSecretKey) {
														return Promise.reject(
															__('Please Enter the Live Secret Key', 'sa-hosted-checkout-for-woocommerce')
														);
													}
													if (apiKeyType === 'restricted' && !restrictedLiveKey) {
														return Promise.reject(
															__('Please Enter the Restricted Live Key', 'sa-hosted-checkout-for-woocommerce')
														);
													}
												} else if (liveSceretRequired === false && testSceretRequired === true) {
													if (apiKeyType === 'standard' && !testSecretKey) {
														return Promise.reject(
															__('Please Enter the Test Secret Key', 'sa-hosted-checkout-for-woocommerce')
														);
													}
													if (apiKeyType === 'restricted' && !restrictedTestKey) {
														return Promise.reject(
															__('Please Enter the Restricted Test Key', 'sa-hosted-checkout-for-woocommerce')
														);
													}
												}
												return Promise.resolve();
											} else {
												return Promise.resolve();
											}
										},
									},
								]}
							/>
						</div>
					}
				>
					<ProForm.Group>

						<ProFormSegmented
							label={ __(
								'Stripe Integration Mode',
								'sa-hosted-checkout-for-woocommerce'
							) }
							name="sahcfwc_stripe_integration_mode"
							tooltip={ __(
								'This option allows you to choose Stripe Integration Mode,  either live or test, on the Stripe checkout page.',
								'sa-hosted-checkout-for-woocommerce'
							) }
							request={ async () => [
								{
									label: __(
										'Test Mode',
										'sa-hosted-checkout-for-woocommerce'
									),
									value: 'test-mode',
								},
								{
									label: __(
										'Live Mode',
										'sa-hosted-checkout-for-woocommerce'
									),
									value: 'live-mode',
								},
							] }
							onChange={ ( value ) => {
								setStripeLiveModeStatus(
									value === 'live-mode' ? false : true
								);
								setStripeTestModeStatus(
									value === 'test-mode' ? false : true
								);
								if ( value === 'live-mode' ) {
									setLiveSceretRequired( true );
									setTestSceretRequired( false );
								}
								if ( value === 'test-mode' ) {
									setTestSceretRequired( true );
									setLiveSceretRequired( false );
								}
							} }
						/>

						{/* In your form JSX, replace the restricted keys toggle with: */}
						<ProFormSegmented
							label={__('API Key Type', 'sa-hosted-checkout-for-woocommerce')}
							name="sahcfwc_api_key_type"
							tooltip={__('Choose between Standard or Restricted API Keys', 'sa-hosted-checkout-for-woocommerce')}
							request={async () => [
								{
									label: __('Standard API Key', 'sa-hosted-checkout-for-woocommerce'),
									value: 'standard',
								},
								{
									label: __('Restricted API Key', 'sa-hosted-checkout-for-woocommerce'),
									value: 'restricted',
								},
							]}
							onChange={(value) => {
								setApiKeyType(value);
							}}
						/>

						<ProFormSegmented
							label={
								<span className="disabled-prem">
									{ __(
										'Admin Test Mode',
										'sa-hosted-checkout-for-woocommerce'
									) }{ ' ' }
									<Tooltip title="Premium" placement="top">
										<i className="sahcfwc-info-icon">i</i>
									</Tooltip>
								</span>
							}
							name="sahcfwc_stripe_admin_test_mode_status"
							tooltip={ __(
								'This option allows you to  activate admin test mode on the Stripe checkout page.',
								'sa-hosted-checkout-for-woocommerce'
							) }
							fieldProps={ {
								className: 'disabled-prem',
							} }
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
						/>

						{/* // Modify the key fields sections to be conditional: */}
    					{apiKeyType === 'standard' && (
							<>
								<ProFormText.Password
									name="sahcfwc_stripe_test_secret_key"
									label={ __(
										'Test Secret Key',
										'sa-hosted-checkout-for-woocommerce'
									) }
									tooltip={ __(
										'This option allows you to add a test secret key for the Stripe Test Mode.',
										'sa-hosted-checkout-for-woocommerce'
									) }
									placeholder={ __(
										'Test Secret Key',
										'sa-hosted-checkout-for-woocommerce'
									) }
									type="password"
									colProps={ {
										xs: 24,
										sm: 24,
										md: 24,
										lg: 24,
										xl: 24,
									} }
									onChange={ async ( event ) => {
										setShortTestSecretKey(
											generatShortkey( event.target.value )
										);
										setTestSecretKey( event.target.value );
									} }
									extra={
										<div>
											{ __(
												'Test Secret key ending *****',
												'sa-hosted-checkout-for-woocommerce'
											) }
											{ shortTestSecretKey }
										</div>
									}
									rules={ [
										{
											required: testSceretRequired,
											message: ' ',
										},
										{
											validator: async ( _, value ) => {
												if ( ! value && testSceretRequired ) {
													return Promise.reject(
														__(
															'Please Enter the Test Secret Key',
															'sa-hosted-checkout-for-woocommerce'
														)
													);
												}
												if (
													value &&
													! value.includes( 'test' )
												) {
													return Promise.reject(
														__(
															"The secret key should begin with the country code followed by 'test'.",
															'sa-hosted-checkout-for-woocommerce'
														)
													);
												}
												return Promise.resolve();
											},
										},
									] }
								/>

								<ProFormText.Password
									name="sahcfwc_stripe_live_secret_key"
									label={ __(
										'Live Secret Key',
										'sa-hosted-checkout-for-woocommerce'
									) }
									tooltip={ __(
										'This option allows you to add a secret key for the Stripe Live Mode.',
										'sa-hosted-checkout-for-woocommerce'
									) }
									placeholder={ __(
										'Live Secret Key',
										'sa-hosted-checkout-for-woocommerce'
									) }
									colProps={ {
										xs: 24,
										sm: 24,
										md: 24,
										lg: 24,
										xl: 24,
									} }
									onChange={ async ( event ) => {
										setShortLiveSecretKey(
											generatShortkey( event.target.value )
										);
										setLiveSecretKey( event.target.value );
									} }
									extra={
										<div>
											{ __(
												'Live Secret key ending *****',
												'sa-hosted-checkout-for-woocommerce'
											) }
											{ shortLiveSecretKey }
										</div>
									}
									rules={ [
										{
											required: liveSceretRequired,
											message: ' ',
										},
										{
											validator: async ( _, value ) => {
												if ( ! value && liveSceretRequired ) {
													return Promise.reject(
														__(
															'Please Enter the Live Secret Key',
															'sa-hosted-checkout-for-woocommerce'
														)
													);
												}
												if (
													value &&
													! value.includes( 'live' )
												) {
													return Promise.reject(
														__(
															"The secret key should begin with the country code followed by 'live'.",
															'sa-hosted-checkout-for-woocommerce'
														)
													);
												}
												return Promise.resolve();
											},
										},
									] }
								/>
							</>
    					)}

						{apiKeyType === 'restricted' && (
							<>
								<ProFormText.Password
									name="sahcfwc_restricted_test_key"
									label={__(
										'Restricted Test Key',
										'sa-hosted-checkout-for-woocommerce'
									)}
									tooltip={__(
										'Enter your restricted test key with limited permissions',
										'sa-hosted-checkout-for-woocommerce'
									)}
									placeholder={__(
										'Restricted Test Key',
										'sa-hosted-checkout-for-woocommerce'
									)}
									onChange={async (event) => {
										setShortRestrictedTestKey(generatShortkey(event.target.value));
										setRestrictedTestKey(event.target.value);
									}}
									extra={
										<div>
											{__('Restricted Test key ending *****', 'sa-hosted-checkout-for-woocommerce')}
											{shortRestrictedTestKey}
										</div>
									}
									rules={[
										{
											required: testSceretRequired && apiKeyType === 'restricted',
											message: __('Please enter the restricted test key', 'sa-hosted-checkout-for-woocommerce'),
										},
										{
											validator: async (_, value) => {
												if (!value && testSceretRequired && apiKeyType === 'restricted') {
													return Promise.reject(
														__('Please enter the restricted test key', 'sa-hosted-checkout-for-woocommerce')
													);
												}
												if (value && !value.includes('test')) {
													return Promise.reject(
														__('The restricted key should begin with "rk_test_"', 'sa-hosted-checkout-for-woocommerce')
													);
												}
												return Promise.resolve();
											},
										},
									]}
								/>

								<ProFormText.Password
									name="sahcfwc_restricted_live_key"
									label={__(
										'Restricted Live Key',
										'sa-hosted-checkout-for-woocommerce'
									)}
									tooltip={__(
										'Enter your restricted live key with limited permissions',
										'sa-hosted-checkout-for-woocommerce'
									)}
									placeholder={__(
										'Restricted Live Key',
										'sa-hosted-checkout-for-woocommerce'
									)}
									onChange={async (event) => {
										setShortRestrictedLiveKey(generatShortkey(event.target.value));
										setRestrictedLiveKey(event.target.value);
									}}
									extra={
										<div>
											{__('Restricted Live key ending *****', 'sa-hosted-checkout-for-woocommerce')}
											{shortRestrictedLiveKey}
										</div>
									}
									rules={[
										{
											required: liveSceretRequired && apiKeyType === 'restricted',
											message: __('Please enter the restricted live key', 'sa-hosted-checkout-for-woocommerce'),
										},
										{
											validator: async (_, value) => {
												if (!value && liveSceretRequired && apiKeyType === 'restricted') {
													return Promise.reject(
														__('Please enter the restricted live key', 'sa-hosted-checkout-for-woocommerce')
													);
												}
												if (value && !value.includes('live')) {
													return Promise.reject(
														__('The restricted key should begin with "rk_live_"', 'sa-hosted-checkout-for-woocommerce')
													);
												}
												return Promise.resolve();
											},
										},
									]}
								/>
							</>
    					)}
						<ProFormText
							name="sahcfwc_stripe_webhook_url"
							label={ __(
								'Webhook URL',
								'sa-hosted-checkout-for-woocommerce'
							) }
							fieldProps={ {
								disabled: true,
								addonAfter: (
									<Typography.Text
										copyable={ {
											text: sahcfwc_customizations_localized_objects.webhook_URL,
											tooltips: [
												'Click here to copy the webhook URL',
												'You have copied the webhook URL!',
											],
										} }
									/>
								),
							} }
							colProps={ {
								xs: 24,
								sm: 24,
								md: 24,
								lg: 24,
								xl: 24,
							} }
							extra={
								<div>
									{ __(
										<span>
											To set up the webhook, copy the
											provided URL by clicking on the copy
											icon. Then, go to your
											<a
												href="https://dashboard.stripe.com/test/webhooks"
												target="_blank"
												rel="noopener noreferrer"
											>
												Stripe dashboard webhooks screen
											</a>
											and paste the copied URL there.
											After adding it, Stripe will
											generate a webhook signing secret
											key for secure verification.
										</span>,
										'sa-hosted-checkout-for-woocommerce'
									) }
								</div>
							}
						/>
						<ProFormText.Password
							name="sahcfwc_stripe_webhook_key"
							label={ __(
								'Webhook Signing Secret Key',
								'sa-hosted-checkout-for-woocommerce'
							) }
							placeholder={ __(
								'Webhook Key',
								'sa-hosted-checkout-for-woocommerce'
							) }
							colProps={ {
								xs: 24,
								sm: 24,
								md: 24,
								lg: 24,
								xl: 24,
							} }
							onChange={ async ( event ) => {
								setWebhookKey(
									generatShortkey( event.target.value )
								);
							} }
							extra={
								<div>
									{ __(
										'Webhook Signing Secret key ending *****',
										'sa-hosted-checkout-for-woocommerce'
									) }
									{ webhookKey }
								</div>
							}
							rules={ [
								{
									required: true,
									message: __(
										'Please enter the Live Secret Key',
										'sa-hosted-checkout-for-woocommerce'
									),
								},
							] }
						/>
					</ProForm.Group>
				</ProCard>
			</ProForm>
		</div>
	);
};

export default StripeSetting;
