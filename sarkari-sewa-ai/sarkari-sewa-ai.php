<?php
/**
 * Plugin Name:       Sarkari Sewa AI
 * Plugin URI:        https://sarkariseva.com
 * Description:       Production-ready AI-powered search assistant and chatbot answering strictly from published content on SarkariSewa.com.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Author:            Sarkari Sewa Team
 * Author URI:        https://sarkariseva.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       sarkari-sewa-ai
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin Constants
define( 'SARKARI_SEWA_AI_VERSION', '1.0.0' );
define( 'SARKARI_SEWA_AI_PLUGIN_FILE', __FILE__ );
define( 'SARKARI_SEWA_AI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SARKARI_SEWA_AI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SARKARI_SEWA_AI_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'SARKARI_SEWA_AI_REST_NAMESPACE', 'sarkari-sewa-ai/v1' );

/**
 * Autoload classes within the includes directory.
 *
 * @param string $class_name Class name.
 */
spl_autoload_register( function ( $class_name ) {
	if ( strpos( $class_name, 'Sarkari_Sewa_AI_' ) !== 0 ) {
		return;
	}

	// Explicit class mapping for multi-class files & factory classes
	$class_map = array(
		'Sarkari_Sewa_AI_Provider_Interface'  => 'class-ai-provider.php',
		'Sarkari_Sewa_AI_Provider_Factory'    => 'class-ai-provider.php',
		'Sarkari_Sewa_AI_Vector_Interface'    => 'class-vector-provider.php',
		'Sarkari_Sewa_AI_Vector_Factory'      => 'class-vector-provider.php',
		'Sarkari_Sewa_AI_Embedding_Interface' => 'class-embedding-provider.php',
		'Sarkari_Sewa_AI_Embedding_Factory'   => 'class-embedding-provider.php',
	);

	if ( isset( $class_map[ $class_name ] ) ) {
		$file_path = SARKARI_SEWA_AI_PLUGIN_DIR . 'includes/' . $class_map[ $class_name ];
		if ( file_exists( $file_path ) ) {
			require_once $file_path;
			return;
		}
	}

	$file_name = 'class-' . strtolower( str_replace( '_', '-', substr( $class_name, 16 ) ) ) . '.php';
	$file_path = SARKARI_SEWA_AI_PLUGIN_DIR . 'includes/' . $file_name;

	if ( file_exists( $file_path ) ) {
		require_once $file_path;
	}
} );

/**
 * Plugin Activation hook
 */
function activate_sarkari_sewa_ai() {
	require_once SARKARI_SEWA_AI_PLUGIN_DIR . 'includes/class-activator.php';
	Sarkari_Sewa_AI_Activator::activate();
}
register_activation_hook( __FILE__, 'activate_sarkari_sewa_ai' );

/**
 * Plugin Deactivation hook
 */
function deactivate_sarkari_sewa_ai() {
	require_once SARKARI_SEWA_AI_PLUGIN_DIR . 'includes/class-deactivator.php';
	Sarkari_Sewa_AI_Deactivator::deactivate();
}
register_deactivation_hook( __FILE__, 'deactivate_sarkari_sewa_ai' );

/**
 * Initialize and run the plugin.
 */
function run_sarkari_sewa_ai() {
	require_once SARKARI_SEWA_AI_PLUGIN_DIR . 'includes/class-plugin.php';
	$plugin = new Sarkari_Sewa_AI_Plugin();
	$plugin->run();
}
add_action( 'plugins_loaded', 'run_sarkari_sewa_ai' );
