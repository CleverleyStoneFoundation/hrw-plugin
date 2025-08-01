<?php

/**
 * HRW WordPress Timing Class
 * 
 * Measures WordPress-level timing to identify overhead after our backend completes
 * This helps find the gap between our 13ms backend and 5.57s browser timing
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
	 * Track if we've already hooked
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
		if (self::$hooked) {
			return;
		}

		// Hook early in WordPress request lifecycle
		add_action('init', [__CLASS__, 'track_request_start'], 1);

		// Hook late in WordPress response
		add_action('wp_loaded', [__CLASS__, 'track_wp_loaded'], 999);
		add_action('shutdown', [__CLASS__, 'track_request_end'], 999);

		// Hook into REST API specifically
		add_filter('rest_pre_serve_request', [__CLASS__, 'track_rest_serve'], 10, 4);

		self::$hooked = true;
		error_log('HRW WordPress Timing: Initialized WordPress timing hooks');
	}

	/**
	 * Track when WordPress request starts processing
	 */
	public static function track_request_start()
	{
		// Only track our API endpoint
		if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/wp-json/vibemap/v1/places-data') !== false) {
			self::$request_start_time = microtime(true);
			error_log('HRW WordPress Timing: [LIFECYCLE] WordPress init started at ' . date('H:i:s.') . substr(microtime(), 2, 3));
		}
	}

	/**
	 * Track when WordPress has loaded
	 */
	public static function track_wp_loaded()
	{
		if (self::$request_start_time && isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/wp-json/vibemap/v1/places-data') !== false) {
			$wp_loaded_time = round((microtime(true) - self::$request_start_time) * 1000, 2);
			error_log('HRW WordPress Timing: [LIFECYCLE] WordPress loaded in ' . $wp_loaded_time . 'ms');
		}
	}

	/**
	 * Track REST API response serving
	 */
	public static function track_rest_serve($served, $result, $request, $server)
	{
		// Only track our specific endpoint
		if ($request->get_route() !== '/vibemap/v1/places-data') {
			return $served;
		}

		if (self::$request_start_time) {
			$serve_time = round((microtime(true) - self::$request_start_time) * 1000, 2);
			error_log('HRW WordPress Timing: [LIFECYCLE] REST response ready to serve in ' . $serve_time . 'ms');

			// Get response size for debugging
			if (is_a($result, 'WP_REST_Response')) {
				$response_data = $result->get_data();
				$json_size = strlen(json_encode($response_data));
				$size_kb = round($json_size / 1024, 1);
				error_log('HRW WordPress Timing: [LIFECYCLE] Response size: ' . $size_kb . 'KB');
			}
		}

		return $served;
	}

	/**
	 * Track when WordPress request completely ends
	 */
	public static function track_request_end()
	{
		if (self::$request_start_time && isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/wp-json/vibemap/v1/places-data') !== false) {
			$total_time = round((microtime(true) - self::$request_start_time) * 1000, 2);
			error_log('HRW WordPress Timing: [LIFECYCLE] WordPress request completely finished in ' . $total_time . 'ms');
			error_log('HRW WordPress Timing: [LIFECYCLE] ===== WORDPRESS REQUEST COMPLETE =====');

			// Reset for next request
			self::$request_start_time = null;
		}
	}

	/**
	 * Get timing statistics
	 */
	public static function get_timing_summary()
	{
		return [
			'message' => 'WordPress lifecycle timing is active',
			'tracks' => [
				'init' => 'WordPress initialization',
				'wp_loaded' => 'WordPress fully loaded',
				'rest_serve' => 'REST response ready to serve',
				'shutdown' => 'WordPress request completely finished'
			]
		];
	}
}
