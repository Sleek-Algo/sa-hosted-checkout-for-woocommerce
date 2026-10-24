<?php
/** Isolated frontend-shaped sanitizer regression: no wp-admin helpers loaded. */
if ( PHP_SAPI !== 'cli' || function_exists( 'add_settings_error' ) ) { throw new RuntimeException( 'Run as a standalone CLI test without WordPress admin helpers.' ); }
function esc_url_raw( $value, $protocols ) { return $value; }
function wp_parse_url( $value, $component ) { return parse_url( $value, $component ); }
require dirname( __DIR__ ) . '/includes/classes/class-sahcfwc-checkout-experience.php';
foreach ( array( 'https://example.com/legacy.webp', 'https://example.com/legacy.gif', 'https://example.com/image' ) as $value ) {
	if ( array() !== \SAHCFWC\Classes\SAHCFWC_Checkout_Experience::sanitize_branding( array( 'logo_url' => $value ) ) ) { throw new RuntimeException( 'Unsupported legacy image was not safely omitted.' ); }
}
echo json_encode( array( 'suite' => 'free-branding-frontend-safety', 'pass' => true, 'cases' => 3, 'admin_helpers_loaded' => false ) ) . PHP_EOL;
