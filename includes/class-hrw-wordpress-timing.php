<?php

/**
 * HRW WordPress Lifecycle Timing
 * 
 * Measures WordPress request lifecycle to identify hosting infrastructure delays
 * Tracks init, wp_loaded, rest_serve, and shutdown phases
 * 
 * @package HRW_Plugin
 * @since 1.2.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

class HRW_WordPress_Timing
{
	/**
	 * Track if hooks are already initialized
	 */
	private static $hooked = false;

	/**
	 * Store request start time
	 */
	private static $request_start_time = null;

	/**
	 * Initialize WordPress timing hooks
	 */
	public static function init()
	{
		// Prevent duplicate initialization
		if (self::$hooked) {
			return;
		}

		// Track WordPress lifecycle phases
		add_action('init', [__CLASS__, 'track_request_start'], 1);
		add_action('wp_loaded', [__CLASS__, 'track_wp_loaded'], 999);
		add_action('shutdown', [__CLASS__, 'track_request_end'], 999);
		add_filter('rest_pre_serve_request', [__CLASS__, 'track_rest_serve'], 10, 4);

		self::$hooked = true;

		// Only log initialization in debug mode
		if (defined('WP_DEBUG') && WP_DEBUG) {
			error_log('HRW WordPress Timing: Initialized WordPress timing hooks');
		}
	}

	/**
	 * Track when WordPress init starts
	 */
	public static function track_request_start()
	{
		// Only track our API endpoint
		if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/wp-json/vibemap/v1/places-data') !== false) {
			self::$request_start_time = microtime(true);

			// Only log in debug mode
			if (defined('WP_DEBUG') && WP_DEBUG) {
				error_log('HRW WordPress Timing: WordPress init started for API request');
			}
		}
	}

	/**
	 * Track when WordPress is fully loaded
	 */
	public static function track_wp_loaded()
	{
		if (self::$request_start_time && isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/wp-json/vibemap/v1/places-data') !== false) {
			$wp_loaded_time = round((microtime(true) - self::$request_start_time) * 1000, 2);

			// Only log in debug mode
			if (defined('WP_DEBUG') && WP_DEBUG) {
				error_log('HRW WordPress Timing: WordPress loaded in ' . $wp_loaded_time . 'ms');
			}
		}
	}

	/**
	 * Track when REST response is ready to serve
	 * 
	 * @param bool $served Whether the request has already been served
	 * @param WP_REST_Response $result Result to send to the client
	 * @param WP_REST_Request $request Request used to generate the response
	 * @param WP_REST_Server $server Server instance
	 * @return bool
	 */
	public static function track_rest_serve($served, $result, $request, $server)
	{
		// Only track our specific endpoint
		if ($request->get_route() !== '/vibemap/v1/places-data') {
			return $served;
		}

		if (self::$request_start_time) {
			$serve_time = round((microtime(true) - self::$request_start_time) * 1000, 2);

			// Always log REST serve time as it's critical performance data
			error_log('HRW WordPress Timing: REST response ready in ' . $serve_time . 'ms');

			// Log response size in debug mode only
			if (defined('WP_DEBUG') && WP_DEBUG && is_a($result, 'WP_REST_Response')) {
				$response_data = $result->get_data();
				$json_size = strlen(json_encode($response_data));
				$size_kb = round($json_size / 1024, 1);
				error_log('HRW WordPress Timing: Response size: ' . $size_kb . 'KB');
			}
		}

		return $served;
	}

	/**
	 * Track when WordPress request ends
	 */
	public static function track_request_end()
	{
		if (self::$request_start_time && isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/wp-json/vibemap/v1/places-data') !== false) {
			$total_time = round((microtime(true) - self::$request_start_time) * 1000, 2);

			// Always log total WordPress time as it's critical for hosting delay analysis
			error_log('HRW WordPress Timing: WordPress request finished in ' . $total_time . 'ms');

			// Log performance issues
			if ($total_time > 1000) { // More than 1 second
				error_log('HRW WordPress Timing: SLOW WordPress processing detected: ' . $total_time . 'ms');
			}

			// Reset for next request
			self::$request_start_time = null;
		}
	}

	/**
	 * Get timing statistics
	 * 
	 * @return array Timing information
	 */
	public static function get_timing_info()
	{
		return [
			'status' => self::$request_start_time ? 'Tracking active request' : 'No active request',
			'start_time' => self::$request_start_time,
			'current_duration' => self::$request_start_time ? round((microtime(true) - self::$request_start_time) * 1000, 2) . 'ms' : 'N/A'
		];
	}
}
