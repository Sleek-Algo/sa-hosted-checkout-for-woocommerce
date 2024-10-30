import { useState, useEffect, useRef } from 'react';
import { Button, Form, message, Tooltip } from 'antd';

import { ProTable, ModalForm } from '@ant-design/pro-components';
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';
import { __ } from '@wordpress/i18n';
import { browserScreenSize, useWindowResizeThreshold } from '../Helpers';
import { ConfigProvider } from 'antd';
import en_US from 'antd/lib/locale/en_US';

const StripeSessions = () => {
	const [ openStripeSessionDetailsModal, setOpenStripeSessionDetailsModal ] =
		useState( false );
	const [ stripeSessionDetailsobject, setStripeSessionDetailsobject ] =
		useState( [] );
	const [
		stripeSessionsAPIStartingAfter,
		setStripeSessionsAPIStartingAfter,
	] = useState( '' );
	const [ tableColumns, setTableColumns ] = useState( [] );

	const sessionsTableRef = useRef();
	const screenSize = browserScreenSize();
	const MAX_MOBILE_WIDTH = 786;
	const isMobileSize = useWindowResizeThreshold( MAX_MOBILE_WIDTH );

	const columns = [
		{
			dataIndex: __(
				'customer_email',
				'sa-hosted-checkout-for-woocommerce'
			),
			key: 'filter-column-customer-name',
			tip: __(
				'The rule name is the unique key',
				'sa-hosted-checkout-for-woocommerce'
			),
			hideInTable: true,
			search: {
				transform: ( value ) => {
					return {
						search: value,
					};
				},
			},
			fieldProps: {
				placeholder: __(
					'Search by customer email',
					'sa-hosted-checkout-for-woocommerce'
				),
			},
		},
		{
			dataIndex: __( 'created_at', 'sa-hosted-checkout-for-woocommerce' ),
			valueType: 'dateRange',
			hideInTable: true,
			search: {
				transform: ( value ) => {
					return {
						startTime: value[ 0 ],
						endTime: value[ 1 ],
					};
				},
			},
			fieldProps: {
				placeholder: [
					__( 'Start date', 'sa-hosted-checkout-for-woocommerce' ),
					__( 'End date', 'sa-hosted-checkout-for-woocommerce' ),
				],
				size: 'large',
			},
		},
		{
			title: __( 'Customer Email', 'sa-hosted-checkout-for-woocommerce' ),
			dataIndex: 'customer_email',
			key: 'table-column-customer-email',
			hideInSearch: true,
		},
		{
			title: __( 'Total Amount', 'sa-hosted-checkout-for-woocommerce' ),
			dataIndex: 'amount_total',
			key: 'table-column-status',
			sorter: false,
			hideInForm: true,
			hideInSearch: true,
			render: ( dom, record ) => {
				let formattedValue =
					record?.meta_data?.amount_total != null
						? record?.meta_data?.amount_total
						: '-';
				formattedValue = ( formattedValue / 100 ).toFixed( 2 );
				return <p>{ formattedValue }</p>;
			},
		},
		{
			title: __( 'Payment Status', 'sa-hosted-checkout-for-woocommerce' ),
			key: 'table-column-status',
			sorter: false,
			hideInForm: true,
			hideInSearch: true,
			render: ( dom, record ) => {
				let payment_intent_val =
					record?.payment_intent != null
						? record?.payment_intent
						: '-';
				let strip_dashbord_link =
					'https://dashboard.stripe.com/test/payments/' +
					payment_intent_val;
				let status_payment =
					record?.payment_status === 'paid' ? (
						<b>
							<a target="_blank" href={ strip_dashbord_link }>
								{ record?.payment_status }
							</a>{ ' ' }
						</b>
					) : (
						record?.payment_status
					);
				return <p>{ status_payment }</p>;
			},
		},
		{
			title: __( 'Session URL', 'sa-hosted-checkout-for-woocommerce' ),
			dataIndex: 'session_url',
			key: 'table-column-session-url',
			sorter: false,
			hideInForm: true,
			hideInSearch: true,
			render: ( dom, record ) => {
				const url = record?.meta_data?.url;
				const truncatedUrl =
					url.length > 30 ? `${ url.slice( 0, 30 ) }...` : url;
				return (
					<a href={ url } target="_blank" rel="noopener noreferrer">
						<Tooltip title={ url }>{ truncatedUrl }</Tooltip>
					</a>
				);
			},
		},
		{
			title: __( 'Date Expired', 'sa-hosted-checkout-for-woocommerce' ),
			dataIndex: 'expires_at',
			key: 'table-column-status',
			sorter: false,
			hideInForm: true,
			hideInSearch: true,
			render: ( date ) => {
				let dates = new Date( date * 1000 );
				return <p>{ dates.toLocaleString() }</p>;
			},
		},
		{
			title: __( 'Date Created', 'sa-hosted-checkout-for-woocommerce' ),
			dataIndex: 'created_at',
			key: 'table-column-status',
			sorter: false,
			hideInForm: true,
			hideInSearch: true,
		},
	];

	const tableLocale = {
		search: {
			searchText: 'My Inquiry',
			resetText: 'My Reset',
		},
	};

	useEffect( () => {
		if ( isMobileSize ) {
			columns[ 1 ].fixed = 'none';
		} else {
			columns[ 1 ].fixed = 'left';
		}
		setTableColumns( columns );
	}, [ isMobileSize ] );

	const handleRowClick = ( record ) => {
		setOpenStripeSessionDetailsModal( true );
		setStripeSessionDetailsobject( record );
	};

	return (
		<div className="sahcfwc-app-tab-content-container">
			<div className="disabled-prem-table">
				<ConfigProvider locale={ en_US }>
					<ProTable
						actionRef={ sessionsTableRef }
						locale={ {
							emptyText: (
								<span>
									{ __(
										'No records found',
										'sa-hosted-checkout-for-woocommerce'
									) }
								</span>
							),
						} }
						tableToolBar={ {} }
						rowKey="id"
						options={ false }
						search={ {
							resetText: __(
								'Reset',
								'sa-hosted-checkout-for-woocommerce'
							),
							searchText: __(
								'Inquiry',
								'sa-hosted-checkout-for-woocommerce'
							),
						} }
						pagination={ {
							showQuickJumper: false,
							pageSize: 10,
							defaultPageSize: 10,
							pageSizeOptions: [ 10 ],
							showTotal: ( total, range ) => (
								<div>
									{ `showing ${ range[ 0 ] }-${ range[ 1 ] } of ${ total } total items` }
								</div>
							),
						} }
						request={ async (
							params = {},
							sort,
							filter,
							paginate
						) => {
							// Dummy Data
							const dummyData = {
								total: 5,
								data: [
									{
										id: 1,
										customer_email: 'dummy1@example.com',
										amount_total: 5000,
										payment_status: 'paid',
										meta_data: {
											url: 'https://example.com/session/1',
										},
										expires_at: Date.now() / 1000,
										created_at: Date.now() / 1000,
									},
									{
										id: 2,
										customer_email: 'dummy2@example.com',
										amount_total: 2000,
										payment_status: 'unpaid',
										meta_data: {
											url: 'https://example.com/session/2',
										},
										expires_at: Date.now() / 1000,
										created_at: Date.now() / 1000,
									},
									{
										id: 3,
										customer_email: 'dummy2@example.com',
										amount_total: 2000,
										payment_status: 'unpaid',
										meta_data: {
											url: 'https://example.com/session/2',
										},
										expires_at: Date.now() / 1000,
										created_at: Date.now() / 1000,
									},
									{
										id: 4,
										customer_email: 'dummy2@example.com',
										amount_total: 2000,
										payment_status: 'unpaid',
										meta_data: {
											url: 'https://example.com/session/2',
										},
										expires_at: Date.now() / 1000,
										created_at: Date.now() / 1000,
									},
									{
										id: 5,
										customer_email: 'dummy2@example.com',
										amount_total: 2000,
										payment_status: 'unpaid',
										meta_data: {
											url: 'https://example.com/session/2',
										},
										expires_at: Date.now() / 1000,
										created_at: Date.now() / 1000,
									},
									{
										id: 6,
										customer_email: 'dummy2@example.com',
										amount_total: 2000,
										payment_status: 'unpaid',
										meta_data: {
											url: 'https://example.com/session/2',
										},
										expires_at: Date.now() / 1000,
										created_at: Date.now() / 1000,
									},
								],
							};
							return dummyData;
						} }
						columns={ tableColumns }
						scroll={ {
							x: 1300,
						} }
						onRow={ ( record ) => {
							return {
								onClick: () => handleRowClick( record ),
							};
						} }
					/>
				</ConfigProvider>

				<ModalForm
					title={ __(
						'Session Detail',
						'sa-hosted-checkout-for-woocommerce'
					) }
					open={ openStripeSessionDetailsModal }
					autoFocusFirstInput
					modalProps={ {
						destroyOnClose: true,
						onCancel: () =>
							setOpenStripeSessionDetailsModal( false ),
					} }
					submitter={ false }
					submitTimeout={ 2000 }
				>
					<ProTable
						pagination={ false }
						options={ false }
						search={ false }
						rowKey="key"
						columns={ [
							{
								title: __(
									'Fields',
									'sa-hosted-checkout-for-woocommerce'
								),
								dataIndex: 'key',
							},
							{
								title: __(
									'Value',
									'sa-hosted-checkout-for-woocommerce'
								),
								dataIndex: 'value',
							},
						] }
						dataSource={ Object.keys(
							stripeSessionDetailsobject
						).map( ( key ) => ( {
							key,
							value: stripeSessionDetailsobject[ key ],
						} ) ) }
					/>
				</ModalForm>
			</div>

			<div className="sahcfwc-stripe-session-vissible-div">
				<p>
					Ability to view all the Stripe Checkout sessions created on
					the web store with their status of completion and customer
					information
				</p>
				<a
					href={
						sahcfwc_customizations_localized_objects.purchase_premium_url
					}
					target="_blank"
				>
					<Button className="sahcfwc-upg-pre-btn">
						{ __(
							'Upgrade to Premium ',
							'sa-hosted-checkout-for-woocommerce'
						) }
					</Button>
				</a>
			</div>
		</div>
	);
};

export default StripeSessions;
