import { useState, useEffect, useRef } from 'react';

export const generatShortkey = ( value ) => {
	return value.substr( value.length - 4 );
};

export const shortenKey = ( key, length ) => {
	if ( key.length <= length ) {
		return key;
	}
	const prefix = key.slice( 0, length - 3 ); // Extract characters up to length - 3
	return `${ prefix }...`; // Add ellipsis
};

export const browserScreenSize = () => {
	const [ screenSize, setScreenSize ] = useState( {
		width: window.innerWidth,
		height: window.innerHeight,
	} );

	useEffect( () => {
		const handleResize = () => {
			setScreenSize( {
				width: window.innerWidth,
				height: window.innerHeight,
			} );
		};

		window.addEventListener( 'resize', handleResize );

		// Clean up the event listener when the component unmounts
		return () => {
			window.removeEventListener( 'resize', handleResize );
		};
	}, [] );

	return screenSize;
};

export const useWindowResizeThreshold = ( threshold ) => {
	const [ isMobileSize, setIsMobileSize ] = useState(
		window.innerWidth <= threshold
	);
	const prevWidth = useRef( window.innerWidth );

	useEffect( () => {
		const handleResize = () => {
			const currWidth = window.innerWidth;
			if ( currWidth <= threshold && prevWidth.current > threshold ) {
				setIsMobileSize( true );
			} else if (
				currWidth > threshold &&
				prevWidth.current <= threshold
			) {
				setIsMobileSize( false );
			}
			prevWidth.current = currWidth;
		};

		window.addEventListener( 'resize', handleResize );
		return () => window.removeEventListener( 'resize', handleResize );
	}, [] );

	return isMobileSize;
};
