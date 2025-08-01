<?php

/**
 * HRW Plugin Bootstrap
 * 
 * Loads all the necessary classes and initializes the optimized system
 * 
 * @package HRW_Plugin
 * @since 1.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

/**
 * HRW Plugin Bootstrap Class
 */
class HRW_Plugin_Bootstrap
{

	/**
	 * Plugin version
	 */
	const VERSION = '1.1.0';

	/**
	 * Plugin directory path
	 */
	private static $plugin_dir = '';

	/**
	 * Initialize the plugin
	 */
	public static function init()
	{
		self::$plugin_dir = plugin_dir_path(__FILE__);

		// Load required classes
		self::load_classes();

		// Initialize hooks
		self::init_hooks();

		error_log('HRW Bootstrap: Plugin initialized with optimized classes');
	}

	/**
	 * Load required classes
	 */
	private static function load_classes()
	{
		$classes = [
			'class-hrw-restaurant-loader.php',
			'class-hrw-data-merger.php',
		];

		foreach ($classes as $class_file) {
			$file_path = self::$plugin_dir . $class_file;
			if (file_exists($file_path)) {
				require_once $file_path;
				error_log('HRW Bootstrap: Loaded ' . $class_file);
			} else {
				error_log('HRW Bootstrap: ERROR - Could not load ' . $class_file);
			}
		}
	}

	/**
	 * Initialize WordPress hooks
	 */
	private static function init_hooks()
	{
		// Hook into the REST API response to modify the places data
		add_filter('rest_request_after_callbacks', [__CLASS__, 'modify_places_response'], 20, 3);

		// Add admin notice if classes are missing
		add_action('admin_notices', [__CLASS__, 'check_class_dependencies']);
	}

	/**
	 * Modify the places-data endpoint response (optimized version)
	 */
	public static function modify_places_response($response, $handler, $request)
	{
		// Only modify our specific endpoint
		if ($request->get_route() !== '/vibemap/v1/places-data') {
			return $response;
		}

		error_log('HRW Bootstrap: Starting optimized places-data response modification');

		// Check if we got a valid response
		if (is_wp_error($response)) {
			error_log('HRW Bootstrap: Response is WP_Error: ' . $response->get_error_message());
			return $response;
		}

		// Check if required classes are loaded
		if (!class_exists('HRW_Restaurant_Loader') || !class_exists('HRW_Data_Merger')) {
			error_log('HRW Bootstrap: ERROR - Required classes not loaded, falling back to original response');
			return $response;
		}

		// Get the original data
		$original_data = $response->get_data();

		// Log original data structure (minimal)
		error_log('HRW Bootstrap: Original data - Places: ' . (isset($original_data['places']) ? count($original_data['places']) : 'N/A'));

		// Use the optimized merger
		$merged_data = HRW_Data_Merger::merge_restaurant_data($original_data, $request);

		// Add debug info
		$merged_data['debug_info'] = [
			'hrw_modified' => true,
			'timestamp' => current_time('mysql'),
			'places_processed' => count($merged_data['places']),
			'optimization_version' => self::VERSION,
			'memory_info' => HRW_Restaurant_Loader::get_memory_info()
		];

		// Log merged data structure (minimal)
		error_log('HRW Bootstrap: Merged data - Places: ' . count($merged_data['places']) . ', Memory: ' . HRW_Restaurant_Loader::get_memory_info()['usage_formatted']);

		// Return modified response
		$response->set_data($merged_data);
		return $response;
	}

	/**
	 * Check if required classes are loaded
	 */
	public static function check_class_dependencies()
	{
		$missing_classes = [];

		if (!class_exists('HRW_Restaurant_Loader')) {
			$missing_classes[] = 'HRW_Restaurant_Loader';
		}

		if (!class_exists('HRW_Data_Merger')) {
			$missing_classes[] = 'HRW_Data_Merger';
		}

		if (!empty($missing_classes)) {
?>
			<div class="notice notice-error">
				<p>
					<strong>HRW Plugin Error:</strong> Missing required classes: <?php echo implode(', ', $missing_classes); ?>
					<br>This may cause memory issues. Please check the plugin installation.
				</p>
			</div>
<?php
		}
	}

	/**
	 * Get plugin information
	 */
	public static function get_plugin_info()
	{
		return [
			'version' => self::VERSION,
			'classes_loaded' => [
				'HRW_Restaurant_Loader' => class_exists('HRW_Restaurant_Loader'),
				'HRW_Data_Merger' => class_exists('HRW_Data_Merger'),
			],
			'memory_info' => class_exists('HRW_Restaurant_Loader') ? HRW_Restaurant_Loader::get_memory_info() : null
		];
	}
}

// Auto-initialize if called directly
if (defined('ABSPATH')) {
	HRW_Plugin_Bootstrap::init();
}
