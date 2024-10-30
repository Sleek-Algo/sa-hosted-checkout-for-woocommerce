import { __ } from '@wordpress/i18n';
import { Button } from 'antd';
import {
	CustomerServiceOutlined,
	CopyOutlined,
	SketchOutlined,
} from '@ant-design/icons';
import '../styles/header.scss';
const Header = () => {
	return (
		<div className="sahcfwc-app-header">
			<div className="sahcfwc-app-container">
				<div className="sahcfwc-app-logo">
					<h1>
						{ __(
							'SA Hosted Checkout for WooCommerce',
							'sa-hosted-checkout-for-woocommerce'
						) }{ ' ' }
						<span className="sahcfwc-version"> (v1.0.0)</span>
					</h1>
					<div className="sahcfwc-support">
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
						<a
							href="https://www.sleekalgo.com/contact-us/"
							target="_blank"
						>
							<Button icon={ <CustomerServiceOutlined /> }>
								{ __(
									'Support',
									'sa-hosted-checkout-for-woocommerce'
								) }
							</Button>
						</a>
						<a
							href="https://www.sleekalgo.com/sa-hosted-checkout-for-woocommerce/#installation-guide"
							target="_blank"
						>
							<Button icon={ <CopyOutlined /> }>
								{ __(
									'Documentation',
									'sa-hosted-checkout-for-woocommerce'
								) }
							</Button>
						</a>
					</div>
				</div>
			</div>
		</div>
	);
};

export default Header;
