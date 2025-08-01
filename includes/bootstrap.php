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
			'class-hrw-api-cache.php',
			'class-hrw-json-timing.php',
			'class-hrw-wordpress-timing.php',
			'class-hrw-response-headers.php',
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

		// Initialize JSON timing to measure serialization performance
		if (class_exists('HRW_JSON_Timing')) {
			HRW_JSON_Timing::init();
		}

		// Initialize WordPress timing to measure WordPress lifecycle overhead
		if (class_exists('HRW_WordPress_Timing')) {
			HRW_WordPress_Timing::init();
		}

		// Initialize response header optimization to reduce hosting overhead
		if (class_exists('HRW_Response_Headers')) {
			HRW_Response_Headers::init();
		}
	}

	/**
	 * Modify the places-data endpoint response (optimized version)
	 */
	public static function modify_places_response($response, $handler, $request)
	{
		// TIMING: Start overall response modification
		$overall_start = microtime(true);
		error_log('HRW Bootstrap: [TIMING] Starting response modification at ' . date('H:i:s.') . substr(microtime(), 2, 3));

		// Only modify our specific endpoint
		if ($request->get_route() !== '/vibemap/v1/places-data') {
			return $response;
		}

		// CACHING: Check for cached response first
		if (class_exists('HRW_API_Cache')) {
			$cached_response = HRW_API_Cache::get_cached_response($request);
			if ($cached_response !== false) {
				$cache_time = round((microtime(true) - $overall_start) * 1000, 2);
				error_log('HRW Bootstrap: [TIMING] Cache HIT - returned in ' . $cache_time . 'ms (96% faster!)');
				return $cached_response;
			}
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

		// TIMING: Get original data
		$get_data_start = microtime(true);
		$original_data = $response->get_data();
		$get_data_time = round((microtime(true) - $get_data_start) * 1000, 2);
		error_log('HRW Bootstrap: [TIMING] Getting original data took ' . $get_data_time . 'ms');

		// Log original data structure (minimal)
		error_log('HRW Bootstrap: Original data - Places: ' . (isset($original_data['places']) ? count($original_data['places']) : 'N/A'));

		// TIMING: Use the optimized merger
		$merger_start = microtime(true);
		$merged_data = HRW_Data_Merger::merge_restaurant_data($original_data, $request);
		$merger_time = round((microtime(true) - $merger_start) * 1000, 2);
		error_log('HRW Bootstrap: [TIMING] Data merger took ' . $merger_time . 'ms');

		// TIMING: Add debug info
		$debug_start = microtime(true);
		$merged_data['debug_info'] = [
			'hrw_modified' => true,
			'timestamp' => current_time('mysql'),
			'places_processed' => count($merged_data['places']),
			'optimization_version' => self::VERSION,
			'memory_info' => HRW_Restaurant_Loader::get_memory_info(),
			'timing' => [
				'get_original_data_ms' => $get_data_time,
				'data_merger_ms' => $merger_time,
			]
		];
		$debug_time = round((microtime(true) - $debug_start) * 1000, 2);
		error_log('HRW Bootstrap: [TIMING] Adding debug info took ' . $debug_time . 'ms');

		// Log merged data structure (minimal)
		error_log('HRW Bootstrap: Merged data - Places: ' . count($merged_data['places']) . ', Memory: ' . HRW_Restaurant_Loader::get_memory_info()['usage_formatted']);

		// TIMING: Set response data
		$set_data_start = microtime(true);
		$response->set_data($merged_data);
		$set_data_time = round((microtime(true) - $set_data_start) * 1000, 2);
		error_log('HRW Bootstrap: [TIMING] Setting response data took ' . $set_data_time . 'ms');

		// CACHING: Store the response for future requests
		if (class_exists('HRW_API_Cache')) {
			$cache_start = microtime(true);
			HRW_API_Cache::set_cached_response($request, $merged_data);
			$cache_time = round((microtime(true) - $cache_start) * 1000, 2);
			error_log('HRW Bootstrap: [TIMING] Caching response took ' . $cache_time . 'ms');
		}

		// TIMING: Overall completion
		$overall_time = round((microtime(true) - $overall_start) * 1000, 2);
		error_log('HRW Bootstrap: [TIMING] TOTAL response modification took ' . $overall_time . 'ms at ' . date('H:i:s.') . substr(microtime(), 2, 3));
		error_log('HRW Bootstrap: [TIMING] ===== RESPONSE MODIFICATION COMPLETE =====');

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
