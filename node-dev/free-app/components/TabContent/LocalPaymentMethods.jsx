import { useState, useEffect } from 'react';
import {
	Row,
	Col,
	Typography,
	message,
	Tooltip,
	Divider,
	Result,
	Badge,
} from 'antd';
import { QuestionCircleOutlined, AuditOutlined } from '@ant-design/icons';
import {
	ProCard,
	ProFormGroup,
	ProFormSwitch,
	ProForm,
	ProFormSegmented,
} from '@ant-design/pro-components';

import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import '../../styles/localpayment.scss';
const { Title, Paragraph, Text } = Typography;
const LocalPaymentMethods = () => {
	/**
	 * States
	 */
	const [ isApiProcessing, setIsApiProcessing ] = useState( false );
	const [ paymentMethodStatus, setPaymentMethodStatus ] = useState( false );
	const [ availableLocalPaymentMethods, setAvailableLocalPaymentMethods ] =
		useState( [] );
	const [ selectedLocalPaymentMethods, setSelectedLocalPaymentMethods ] =
		useState( [] );

	// class
	const sa_tooltip = {
		display: 'flex',
		justifyContent: 'space-between',
		marginTop: '0px',
		color: '#000000e0',
		opacity: '0.7',
	};

	useEffect( () => {
		const saveSettings = async () => {
			const formData = {};
			const updated_val =
				paymentMethodStatus === true ? 'manual' : 'automatic';
			formData.sahcfwc_stripe_payment_method_status = updated_val;
			try {
				const response = await apiFetch( {
					path: '/wp/v2/settings/',
					method: 'POST',
					data: formData,
				} );
				message.config( {
					top: 100,
				} );
			} catch ( error ) {
				console.error( 'Error saving settings:', error );
				message.error( 'Failed to save settings. Please try again.' );
			}
		};

		saveSettings();
	}, [ paymentMethodStatus ] );

	// Copy text Code
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
	};

	useEffect( () => {
		apiFetch( {
			path: '/sahcfwc/v1/stripe-local-payment-methods/',
			method: 'GET',
		} ).then( ( response ) => {
			setAvailableLocalPaymentMethods( response );
		} );
	}, [] );

	const formInitialValues = {
		sahcfwc_stripe_payment_method_status: '',
	};

	useEffect( () => {
		apiFetch( {
			path: '/wp/v2/settings/',
			method: 'GET',
		} ).then( ( response ) => {
			setSelectedLocalPaymentMethods(
				response?.sahcfwc_stripe_payment_methodes
			);
		} );
	}, [] );

	return (
		// <div className="sahcfwc-app-tab-content-container disabled-prem-table">
		<ProForm
			className="sahcfwc-checkout-settings-form disabled-prem"
			loading={ isApiProcessing }
			grid={ true }
			initialValues={ formInitialValues }
			layout="horizontal"
			submitter={ {
				submitButtonProps: {
					style: {
						display: 'none',
					},
				},
				resetButtonProps: {
					style: {
						display: 'none',
					},
				},
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
					return {
						...formInitialValues,
						sahcfwc_stripe_payment_method_status:
							response?.sahcfwc_stripe_payment_method_status,
					};
				} );
			} }
		>
			<ProCard
				key="sahcfwc-local-payment-methods-wrapper"
				title={ __(
					'Payment Methods',
					'sa-hosted-checkout-for-woocommerce'
				) }
				headerBordered={ true }
				className="sa-procard-grids"
				extra={
					<div className="sahcfwc-card-header-btn">
						<ProFormSegmented
							// disabled='true'
							label={ __(
								'Custom Payment Method',
								'sa-hosted-checkout-for-woocommerce'
							) }
							name="sahcfwc_stripe_payment_method_status"
							tooltip={ __(
								'This option allows you to choose the payment method as either default or custom on the Stripe checkout page.',
								'sa-hosted-checkout-for-woocommerce'
							) }
							fieldProps={ {
								className: 'stripe_payment_toggle',
								style: {
									marginBottom: '0px',
								},
							} }
							request={ async () => [
								{
									label: __(
										'Default',
										'sa-hosted-checkout-for-woocommerce'
									),
									value: 'automatic',
								},
								{
									label: __(
										'Custom',
										'sa-hosted-checkout-for-woocommerce'
									),
									value: 'manual',
								},
							] }
							onChange={ ( value ) => {
								setPaymentMethodStatus(
									value === 'manual' ? true : false
								);
							} }
						/>
					</div>
				}
			>
				{ paymentMethodStatus === true && (
					<Result
						title={ __(
							'Stripe default payment method is set. To add a custom payment method, select the custom option.',
							'sa-hosted-checkout-for-woocommerce'
						) }
					/>
				) }

				{ paymentMethodStatus === false && (
					<Row
						gutter={ [ 16, 16 ] }
						type="flex"
						key="sahcfwc-local-payment-methods-row"
					>
						{ availableLocalPaymentMethods.map(
							( local_payment_method ) => {
								return (
									<Col
										xs={ { flex: '100%' } }
										sm={ { flex: '50%' } }
										md={ { flex: '50%' } }
										lg={ { flex: '33%' } }
										xl={ { flex: '33%' } }
										style={ { height: 'auto' } }
										key={
											'sahcfwc-local-payment-methods-col-' +
											local_payment_method?.title
										}
									>
										<ProCard
											key={ local_payment_method?.title }
											className="hover-effect disabled-prem"
											// title={}
											bordered
											extra={
												<ProFormGroup>
													<ProFormSwitch
														disabled="true"
														noStyle
														checkedChildren={ __(
															'enable',
															'sa-hosted-checkout-for-woocommerce'
														) }
														unCheckedChildren={ __(
															'disable',
															'sa-hosted-checkout-for-woocommerce'
														) }
														data-parent_id={
															local_payment_method?.title
														}
														fieldProps={ {
															defaultChecked:
																local_payment_method?.status ===
																'enabled'
																	? true
																	: false,
														} }
														onChange={ async (
															checked,
															event
														) => {
															let updated_selected_local_payment_methods =
																selectedLocalPaymentMethods;
															if ( checked ) {
																const new_payment_methods_data =
																	{
																		id: local_payment_method?.id,
																		title: local_payment_method?.title,
																	};
																updated_selected_local_payment_methods.push(
																	new_payment_methods_data
																);
															} else {
																const index =
																	updated_selected_local_payment_methods.findIndex(
																		(
																			method
																		) =>
																			method.title ===
																			local_payment_method.title
																	);
																if (
																	index > -1
																) {
																	updated_selected_local_payment_methods.splice(
																		index,
																		1
																	);
																}
															}

															setSelectedLocalPaymentMethods(
																updated_selected_local_payment_methods
															);

															/**
															 * Set API Processing State
															 */
															setIsApiProcessing(
																true
															);

															apiFetch( {
																path: '/wp/v2/settings/',
																method: 'POST',
																data: {
																	sahcfwc_stripe_payment_methodes:
																		selectedLocalPaymentMethods,
																},
															} ).then(
																(
																	response
																) => {
																	message.success(
																		__(
																			'Congratulations! Your settings have been successfully saved.',
																			'sa-hosted-checkout-for-woocommerce'
																		)
																	);

																	/**
																	 * Set API Processing State
																	 */
																	setIsApiProcessing(
																		false
																	);
																}
															);
														} }
													/>
												</ProFormGroup>
											}
											style={ { height: '100%' } }
										>
											<div
												style={ {
													display: 'flex',
													flexDirection: 'column',
													justifyContent:
														'space-evenly',
													height: '100%',
												} }
											>
												<div>
													<img
														src={
															local_payment_method?.icon_url
														}
														alt=""
														width="90%"
													/>
													<Divider />
													<h4
														style={ {
															margin: 0,
														} }
													>
														{
															local_payment_method?.title
														}
													</h4>
													<p
														style={ {
															flexGrow: '1',
														} }
													>
														{
															local_payment_method?.description
														}
													</p>
												</div>
												<div style={ sa_tooltip }>
													{ local_payment_method?.help && (
														<span>
															Help{ ' ' }
															<Tooltip
																title={
																	local_payment_method?.help
																}
																zIndex={ 9999 }
																placement="bottom"
																overlayInnerStyle={ {
																	backgroundColor:
																		'#fff',
																	color: 'black',
																} }
															>
																<QuestionCircleOutlined
																	style={ {
																		fontSize:
																			'16px',
																	} }
																/>
															</Tooltip>
														</span>
													) }

													{ local_payment_method?.legal && (
														<span>
															Legal*{ ' ' }
															<Tooltip
																title={
																	local_payment_method?.legal
																}
																color="#fff"
																zIndex={ 9999 }
																placement="top"
																overlayInnerStyle={ {
																	backgroundColor:
																		'#fff',
																	color: 'black',
																} }
															>
																<AuditOutlined
																	style={ {
																		color: '#00c4c4',
																		fontSize:
																			'16px',
																	} }
																/>
															</Tooltip>
														</span>
													) }
												</div>
											</div>
										</ProCard>
									</Col>
								);
							}
						) }
					</Row>
				) }
			</ProCard>
		</ProForm>
		// </div>
	);
};

export default LocalPaymentMethods;
