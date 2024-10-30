import { __ } from '@wordpress/i18n';
import '../styles/footer.scss';

const Footer = () => {
	return (
		<div className="sahcfwc-app-Footer">
			<div className="sahcfwc-app-container">
				<div>
					<a
						href="https://www.sleekalgo.com/"
						target="_blank"
						rel="noopener noreferrer"
					>
						{ __( 'Powered By Sleekalgo' ) }
					</a>
				</div>
			</div>
		</div>
	);
};

export default Footer;
