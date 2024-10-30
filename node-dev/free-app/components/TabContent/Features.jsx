import { useState, useEffect } from 'react';
import {
	Card,
	message,
	Alert,
	Space,
	Tooltip,
	Col,
	Row,
	Table,
	Button,
} from 'antd';
import {
	ProCard,
	ProForm,
	ProFormText,
	ProFormSegmented,
} from '@ant-design/pro-components';
import {
	CheckOutlined,
	CloseOutlined,
	SketchOutlined,
} from '@ant-design/icons';

import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import { generatShortkey } from '../Helpers';
import '../../styles/featurelist.scss';
const dataSource = [
	{
		key: '1',
		feature: __(
			'Stripe Integration mode: Provides two modes of integration "Live mode" & "Test mode".',
			'sa-hosted-checkout-for-woocommerce'
		),
		free: <CheckOutlined style={ { color: 'green' } } />,
		premium: <CheckOutlined style={ { color: 'green' } } />,
	},
	{
		key: '2',
		feature: __(
			'Shipping Address option: Add shipping address to be displayed on checkout page.',
			'sa-hosted-checkout-for-woocommerce'
		),
		free: <CheckOutlined style={ { color: 'green' } } />,
		premium: <CheckOutlined style={ { color: 'green' } } />,
	},
	{
		key: '3',
		feature: __(
			'Custom Phone number option: Add Phone number field to be displayed on checkout page.',
			'sa-hosted-checkout-for-woocommerce'
		),
		free: <CheckOutlined style={ { color: 'green' } } />,
		premium: <CheckOutlined style={ { color: 'green' } } />,
	},
	{
		key: '4',
		feature: __(
			'Custom Cancel URL: Let your customer redirect to a specified URL after cancelling an order.',
			'sa-hosted-checkout-for-woocommerce'
		),
		free: <CheckOutlined style={ { color: 'green' } } />,
		premium: <CheckOutlined style={ { color: 'green' } } />,
	},
	{
		key: '5',
		feature: __(
			'Managing Temporary order: Allows your customers to delete all temporary orders.',
			'sa-hosted-checkout-for-woocommerce'
		),
		free: <CheckOutlined style={ { color: 'green' } } />,
		premium: <CheckOutlined style={ { color: 'green' } } />,
	},
	{
		key: '6',
		feature: __(
			'Custom payment methods: Support any or all payment methods.',
			'sa-hosted-checkout-for-woocommerce'
		),
		free: <CloseOutlined style={ { color: 'red' } } />,
		premium: <CheckOutlined style={ { color: 'green' } } />,
	},
	{
		key: '7',
		feature: __(
			'Custom language option: Change the language of Stripe checkout displayed to your customers.',
			'sa-hosted-checkout-for-woocommerce'
		),
		free: <CloseOutlined style={ { color: 'red' } } />,
		premium: <CheckOutlined style={ { color: 'green' } } />,
	},
	{
		key: '8',
		feature: __(
			'Custom text before & after submit button: Add custom text to be displayed before and after submit button of Stripe Checkout.',
			'sa-hosted-checkout-for-woocommerce'
		),
		free: <CloseOutlined style={ { color: 'red' } } />,
		premium: <CheckOutlined style={ { color: 'green' } } />,
	},
	{
		key: '9',
		feature: __(
			'Adjustable quantity option: Let your customers adjust the quantity of products on Stripe Checkout.',
			'sa-hosted-checkout-for-woocommerce'
		),
		free: <CloseOutlined style={ { color: 'red' } } />,
		premium: <CheckOutlined style={ { color: 'green' } } />,
	},
	{
		key: '10',
		feature: __(
			'Stripe Checkout Sessions Information: Ability to view all the Stripe Checkout sessions created on the web store with their status of completion and customer information.',
			'sa-hosted-checkout-for-woocommerce'
		),
		free: <CloseOutlined style={ { color: 'red' } } />,
		premium: <CheckOutlined style={ { color: 'green' } } />,
	},
	{
		key: '11',
		feature: __(
			'Admin test mode: This option automatically enables test mode of payment for administrators of the website.',
			'sa-hosted-checkout-for-woocommerce'
		),
		free: <CloseOutlined style={ { color: 'red' } } />,
		premium: <CheckOutlined style={ { color: 'green' } } />,
	},
	{
		key: '12',
		feature: __(
			'Custom Thank You page: Redirect your customers to a tailored thankyou page as per your choice.',
			'sa-hosted-checkout-for-woocommerce'
		),
		free: <CloseOutlined style={ { color: 'red' } } />,
		premium: <CheckOutlined style={ { color: 'green' } } />,
	},
	{
		key: '13',
		feature: __(
			'Custom fields option: Ability to display custom fields on the Stripe checkout page.',
			'sa-hosted-checkout-for-woocommerce'
		),
		free: <CloseOutlined style={ { color: 'red' } } />,
		premium: <CheckOutlined style={ { color: 'green' } } />,
	},
	{
		key: '13',
		feature: __(
			'Coupon and Discount: The capability to apply coupons and discounts on the Stripe checkout page.',
			'sa-hosted-checkout-for-woocommerce'
		),
		free: <CloseOutlined style={ { color: 'red' } } />,
		premium: <CheckOutlined style={ { color: 'green' } } />,
	},
];
const columns = [
	{
		title: __( 'Feature List', 'sa-hosted-checkout-for-woocommerce' ),
		dataIndex: 'feature',
		key: 'feature',
		width: '66.67%', // 2/3 of the total width
	},
	{
		title: __( 'Free', 'sa-hosted-checkout-for-woocommerce' ),
		dataIndex: 'free',
		key: 'free',
		width: '16.67%', // 1/6 of the total width
	},
	{
		title: __( 'Premium', 'sa-hosted-checkout-for-woocommerce' ),
		dataIndex: 'premium',
		key: 'premium',
		width: '16.67%', // 1/6 of the total width
	},
];
const Features = () => {
	return (
		<div className="features-table">
			<Table
				dataSource={ dataSource }
				columns={ columns }
				pagination={ false }
				bordered
				rowKey="key"
				className="feature-list-table"
			/>
			<div className="features-table-upg-btn">
				<a
					href={
						sahcfwc_customizations_localized_objects.purchase_premium_url
					}
					target="_blank"
				>
					<Button
						icon={ <SketchOutlined /> }
						className="sahcfwc-upg-pre-btn"
					>
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

export default Features;
