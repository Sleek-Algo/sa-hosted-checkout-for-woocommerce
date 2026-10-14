import { useEffect, useState } from 'react';
import { Alert, Button, Checkbox, Col, Radio, Row, Space, Spin, Typography, message } from 'antd';
import { ProCard } from '@ant-design/pro-components';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

const { Paragraph, Text } = Typography;

const LocalPaymentMethods = () => {
	const [ loading, setLoading ] = useState( true );
	const [ saving, setSaving ] = useState( false );
	const [ loadError, setLoadError ] = useState( false );
	const [ methods, setMethods ] = useState( [] );
	const [ mode, setMode ] = useState( 'automatic' );
	const [ selected, setSelected ] = useState( [] );
	const [ removedMethods, setRemovedMethods ] = useState( false );
	const [ saveError, setSaveError ] = useState( '' );
	const [ acssTransactionType, setAcssTransactionType ] = useState( '' );

	useEffect( () => {
		let active = true;
		Promise.all( [
			apiFetch( { path: '/wp/v2/settings/', method: 'GET' } ),
			apiFetch( { path: '/sahcfwc/v1/stripe-local-payment-methods/', method: 'GET' } ),
		] ).then( ( [ settings, catalog ] ) => {
			if ( ! active ) {
				return;
			}
			if ( ! Array.isArray( catalog ) ) {
				throw new Error( 'Invalid payment method catalog.' );
			}
			const available = catalog.map( ( method ) => method.id );
			const saved = Array.isArray( settings.sahcfwc_stripe_payment_methodes )
				? settings.sahcfwc_stripe_payment_methodes
				: [];
			const ids = saved.map( ( method ) => typeof method === 'string' ? method : method?.id );
			setMethods( catalog );
			// The catalog resolves legacy card-brand entries to canonical IDs.
			setSelected( catalog.filter( ( method ) => method.status === 'enabled' ).map( ( method ) => method.id ) );
			setRemovedMethods( ids.some( ( id ) => ! available.includes( id ) ) );
			setMode( settings.sahcfwc_stripe_payment_method_status === 'manual' ? 'manual' : 'automatic' );
			setAcssTransactionType( [ 'personal', 'business' ].includes( settings.sahcfwc_stripe_acss_transaction_type ) ? settings.sahcfwc_stripe_acss_transaction_type : '' );
		} ).catch( () => {
			if ( active ) {
				setLoadError( true );
			}
		} ).finally( () => {
			if ( active ) {
				setLoading( false );
			}
		} );
		return () => {
			active = false;
		};
	}, [] );

	const save = async () => {
		if ( mode === 'manual' && selected.length === 0 ) {
			setSaveError( __( 'Select at least one payment method, or choose Cards only.', 'sa-hosted-checkout-for-woocommerce' ) );
			return;
		}
		if ( mode === 'manual' && selected.includes( 'link' ) && ! selected.includes( 'card' ) ) {
			setSaveError( __( 'Link requires Cards. Select Cards or turn off Link.', 'sa-hosted-checkout-for-woocommerce' ) );
			return;
		}
		setSaving( true );
		if ( mode === 'manual' && selected.includes( 'acss_debit' ) && ! [ 'personal', 'business' ].includes( acssTransactionType ) ) {
			setSaving( false );
			setSaveError( __( 'Select a Canadian debit mandate type before saving.', 'sa-hosted-checkout-for-woocommerce' ) );
			return;
		}
		setSaveError( '' );
		try {
			await apiFetch( {
				path: '/wp/v2/settings/',
				method: 'POST',
				data: {
					sahcfwc_stripe_payment_method_status: mode,
					...( mode === 'manual' && selected.includes( 'acss_debit' ) ? { sahcfwc_stripe_acss_transaction_type: acssTransactionType } : {} ),
					sahcfwc_stripe_payment_methodes: methods
						.filter( ( method ) => selected.includes( method.id ) )
						.map( ( method ) => ( { id: method.id, title: method.title } ) ),
				},
			} );
			setRemovedMethods( false );
			message.success( __( 'Payment method settings saved.', 'sa-hosted-checkout-for-woocommerce' ) );
		} catch ( error ) {
			setSaveError( __( 'Unable to save payment method settings. Please try again.', 'sa-hosted-checkout-for-woocommerce' ) );
		} finally {
			setSaving( false );
		}
	};

	if ( loading ) {
		return <Spin tip={ __( 'Loading payment methods...', 'sa-hosted-checkout-for-woocommerce' ) }><div style={ { minHeight: 120 } } /></Spin>;
	}
	if ( loadError ) {
		return <Alert type="error" showIcon message={ __( 'Unable to load payment methods. Refresh this page to try again.', 'sa-hosted-checkout-for-woocommerce' ) } />;
	}

	const delayedSelected = mode === 'manual' && methods.some( ( method ) => method.delayed && selected.includes( method.id ) );

	return (
		<ProCard title={ __( 'Payment Methods', 'sa-hosted-checkout-for-woocommerce' ) } headerBordered>
			<Space direction="vertical" size="large" style={ { width: '100%' } }>
				<Paragraph>
					{ __( 'Enable the payment methods in your Stripe Dashboard first. Availability at checkout depends on your Stripe account, order currency and amount, customer location, and Stripe eligibility rules.', 'sa-hosted-checkout-for-woocommerce' ) }{ ' ' }
					<a href="https://dashboard.stripe.com/settings/payment_methods" target="_blank" rel="noopener noreferrer">
						{ __( 'Open Stripe payment method settings', 'sa-hosted-checkout-for-woocommerce' ) }
					</a>
				</Paragraph>
				<Radio.Group
					value={ mode }
					disabled={ saving }
					onChange={ ( event ) => { setMode( event.target.value ); setSaveError( '' ); } }
					aria-label={ __( 'Payment method selection', 'sa-hosted-checkout-for-woocommerce' ) }
				>
					<Radio value="automatic">{ __( 'Cards only (default)', 'sa-hosted-checkout-for-woocommerce' ) }</Radio>
					<Radio value="manual">{ __( 'Choose payment methods', 'sa-hosted-checkout-for-woocommerce' ) }</Radio>
				</Radio.Group>
				{ removedMethods && (
					<Alert type="warning" showIcon message={ __( 'Your saved selection includes outdated payment method entries. Review and save to update it.', 'sa-hosted-checkout-for-woocommerce' ) } />
				) }
				{ mode === 'automatic' ? (
					<Paragraph>{ __( 'Checkout accepts eligible card networks. Apple Pay and Google Pay can also appear when Stripe supports them for the customer and account. Other payment methods are not enabled automatically.', 'sa-hosted-checkout-for-woocommerce' ) }</Paragraph>
				) : (
					<>
						<Paragraph>{ __( 'Select at least one method. Card brands such as Visa and Mastercard are included under Cards and cannot be enabled separately here. Methods that do not support the order currency are omitted at checkout.', 'sa-hosted-checkout-for-woocommerce' ) }</Paragraph>
						<Checkbox.Group
							value={ selected }
							disabled={ saving }
							onChange={ ( values ) => { setSelected( values ); setSaveError( '' ); } }
							style={ { width: '100%' } }
						>
							<Row gutter={ [ 16, 16 ] }>
								{ methods.map( ( method ) => (
									<Col xs={ 24 } md={ 12 } xl={ 8 } key={ method.id }>
										<ProCard bordered style={ { height: '100%' } }>
											<Checkbox value={ method.id }>{ method.title }</Checkbox>
											<Paragraph type="secondary" style={ { marginTop: 12, marginBottom: 0 } }>{ method.description }</Paragraph>
											{ method.delayed && <Text type="secondary">{ __( 'Delayed payment confirmation', 'sa-hosted-checkout-for-woocommerce' ) }</Text> }
										</ProCard>
									</Col>
								) ) }
							</Row>
						</Checkbox.Group>
					</>
				) }
				{ delayedSelected && (
					<Alert type="warning" showIcon message={ __( 'Delayed payment methods require a working Stripe webhook and a saved webhook signing secret in Stripe Settings. Enable checkout.session.completed, checkout.session.async_payment_succeeded, and checkout.session.async_payment_failed. Orders remain unpaid until Stripe confirms payment; do not fulfill them while payment is pending.', 'sa-hosted-checkout-for-woocommerce' ) } />
				) }
				{ mode === 'manual' && selected.includes( 'acss_debit' ) && (
					<ProCard title={ __( 'Canadian debit mandate type', 'sa-hosted-checkout-for-woocommerce' ) } bordered>
						<Paragraph>{ __( 'Stripe requires a mandate type for Canadian pre-authorized debits. Choose the type appropriate for your customers and purchases.', 'sa-hosted-checkout-for-woocommerce' ) }</Paragraph>
						<Radio.Group
							value={ acssTransactionType }
							disabled={ saving }
							onChange={ ( event ) => { setAcssTransactionType( event.target.value ); setSaveError( '' ); } }
							aria-label={ __( 'Canadian debit mandate type', 'sa-hosted-checkout-for-woocommerce' ) }
						>
							<Radio value="personal">{ __( 'Personal (purchases by individuals)', 'sa-hosted-checkout-for-woocommerce' ) }</Radio>
							<Radio value="business">{ __( 'Business (business purchases)', 'sa-hosted-checkout-for-woocommerce' ) }</Radio>
						</Radio.Group>
					</ProCard>
				) }
				{ saveError && <Alert type="error" showIcon message={ saveError } /> }
				<Button type="primary" loading={ saving } onClick={ save }>
					{ __( 'Save payment methods', 'sa-hosted-checkout-for-woocommerce' ) }
				</Button>
			</Space>
		</ProCard>
	);
};

export default LocalPaymentMethods;
