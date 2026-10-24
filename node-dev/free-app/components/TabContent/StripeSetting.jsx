import StripeSettings from '../../../shared/StripeSettings';
import '../../styles/stripeSetting.scss';

export default function StripeSetting( props ) {
	return <StripeSettings { ...props } premium={ false } />;
}
