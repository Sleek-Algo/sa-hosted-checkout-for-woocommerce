import { __ } from '@wordpress/i18n';
import Header from './components/Header';
import Footer from './components/Footer';
import Content from './components/Content';
import { createRoot, StrictMode } from '@wordpress/element';
import './styles/app.scss';
import { ConfigProvider } from 'antd';

import ThemeSettings from './config/themeSettings';

const BackendApp = () => {
	return (
		<ConfigProvider
			direction={ sahcfwc_customizations_localized_objects?.language_dir }
			locale={ sahcfwc_customizations_localized_objects?.language }
			prefixCls="sahcfwc"
			theme={ ThemeSettings }
		>
			<div id="sahcfwc-app-main">
				<Header />
				<Content />
				<Footer />
			</div>
		</ConfigProvider>
	);
};

createRoot( document.getElementById( 'sahcfwc-app' ) ).render( <BackendApp /> );
