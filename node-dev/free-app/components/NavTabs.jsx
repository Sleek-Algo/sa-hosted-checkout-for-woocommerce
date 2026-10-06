import React, { useState, useEffect, useRef } from 'react';
import {
	ShoppingCartOutlined,
	SketchOutlined,
	CreditCardOutlined,
	DollarOutlined,
	ClusterOutlined,
} from '@ant-design/icons';
import { Tabs, Alert, Tooltip, Badge } from 'antd';
import StripeSetting from './TabContent/StripeSetting';
import CheckoutSetting from './TabContent/CheckoutSetting';
import LocalPaymentMethods from './TabContent/LocalPaymentMethods';
import StripeSessions from './TabContent/StripeSessions';
import Features from './TabContent/Features';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

const NavTabs = () => {
	/**
	 * States
	 */
	const [ disabledTab, setDisabledTab ] = useState( false );
	const [ warning, setWarning ] = useState( false );
	const [ missingKeyWarning, setMissingKeyWarning ] = useState( false );
	const [ connectionWarning, setConnectionWarning ] = useState( false );
	const [ activeTab, setActiveTab ] = useState( '1' );
	const [ stripeSettingSave, setStripeSettingSave ] = useState( '' );
	const effectRan = useRef( false ); // Track if useEffect has run
	const getTabStatus = () => {
		apiFetch( {
			path: '/sahcfwc/v1/stripe-check-data-exist/',
			method: 'Get',
		} )
			.then( ( response ) => {
				const hasStripeKey = response?.stripe_key === true;
				const countryMismatch =
					hasStripeKey &&
					response?.is_stripe_wc_country_match === false;

				setStripeSettingSave( hasStripeKey );
				setDisabledTab( countryMismatch );
				setWarning( countryMismatch );
				setMissingKeyWarning( response?.checkout_enabled === true && ! hasStripeKey );
				setConnectionWarning( response?.checkout_enabled === true && response?.stripe_connection_error === true );
			} )
			.catch( ( error ) => {
				console.error( 'Error fetching tab status:', error );
			} );
	};

	useEffect( () => {
		if ( effectRan.current ) {
			return; // Prevent the effect from running again
		}

		getTabStatus();
		// Get active tab from local storage
		const savedTab = localStorage.getItem( 'activeTab' );
		if ( savedTab ) {
			setActiveTab( savedTab );
		}
		effectRan.current = true;
	}, [] );

	useEffect( () => {
		// Save active tab to local storage

		localStorage.setItem( 'activeTab', activeTab );
	}, [ activeTab ] );

	return (
		<>
			{ missingKeyWarning && (
				<Alert
					description={ __(
						'Stripe API key is required. Enter a key in Stripe Settings to enable checkout.',
						'sa-hosted-checkout-for-woocommerce'
					) }
					type="warning"
					showIcon={ true }
				/>
			) }
			{ connectionWarning && (
				<Alert
					description={ __(
						'Unable to verify the Stripe connection. Check the API key for the selected mode and try again.',
						'sa-hosted-checkout-for-woocommerce'
					) }
					type="warning"
					showIcon={ true }
				/>
			) }
			{ stripeSettingSave && warning && (
				<Alert
					description={ __(
						'The WooCommerce store country must match the country associated with your Stripe account.',
						'sa-hosted-checkout-for-woocommerce'
					) }
					type="error"
					showIcon={ true }
				/>
			) }
			<Tabs
				activeKey={ activeTab }
				onChange={ ( key ) => {
					setActiveTab( key );
				} }
				items={ [
					{
						key: '1',
						label: __(
							'Stripe Settings',
							'sa-hosted-checkout-for-woocommerce'
						),
						icon: <CreditCardOutlined />,
						children: <StripeSetting />,
					},
					{
						key: '2',
						label: __(
							'Checkout Settings',
							'sa-hosted-checkout-for-woocommerce'
						),
						icon: <ShoppingCartOutlined />,
						children: <CheckoutSetting />,
					},
					{
						key: '3',
						label: (
							<span className="disabled-prem">
								{ __(
									'Payment Methods',
									'sa-hosted-checkout-for-woocommerce'
								) }{ ' ' }
								<Tooltip title="Premium" placement="top">
									<i className="sahcfwc-info-icon">i</i>
								</Tooltip>
							</span>
						),

						icon: <DollarOutlined className="disabled-prem" />,
						children: <LocalPaymentMethods />,
					},
					{
						key: '4',
						label: (
							<span className="disabled-prem">
								{ __(
									'Stripe Sessions',
									'sa-hosted-checkout-for-woocommerce'
								) }{ ' ' }
								<Tooltip title="Premium" placement="top">
									<i className="sahcfwc-info-icon">i</i>
								</Tooltip>
							</span>
						),
						icon: <ClusterOutlined className="disabled-prem" />,
						children: <StripeSessions />,
					},
					{
						key: '5',
						label: (
							<span className="glitter">
								{ __(
									'Features',
									'sa-hosted-checkout-for-woocommerce'
								) }{ ' ' }
							</span>
						),
						icon: <SketchOutlined className="glitter" />,
						children: <Features />,
						className: 'glitter',
					},
				] }
			/>
		</>
	);
};

export default NavTabs;
