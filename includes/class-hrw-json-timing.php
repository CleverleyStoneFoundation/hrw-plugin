<?php

/**
 * HRW JSON Timing Measurement
 * 
 * Measures the time taken for JSON encoding of API responses
 * Helps identify if JSON serialization is a performance bottleneck
 * 
 * @package HRW_Plugin
 * @since 1.2.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

class HRW_JSON_Timing
{
	/**
	 * Track if hooks are already initialized to prevent duplicate hooks
	 */
	private static $hooked = false;

	/**
	 * Store timing data for analysis
	 */
	private static $timing_data = [];

	/**
	 * Initialize JSON timing hooks
	 */
	public static function init()
	{
		// Prevent duplicate initialization
		if (self::$hooked) {
			return;
		}

		// Hook into REST response to measure JSON encoding
		add_filter('rest_post_dispatch', [__CLASS__, 'time_json_encoding'], 10, 3);

		self::$hooked = true;

		// Only log initialization in debug mode
		if (defined('WP_DEBUG') && WP_DEBUG) {
			error_log('HRW JSON Timing: Initialized JSON timing hooks');
		}
	}

	/**
	 * Measure JSON encoding time for VibeMap API responses
	 * 
	 * @param WP_REST_Response $result REST response object
	 * @param WP_REST_Server $server REST server instance
	 * @param WP_REST_Request $request REST request object
	 * @return WP_REST_Response Modified response with timing data
	 */
	public static function time_json_encoding($result, $server, $request)
	{
		// Only measure our specific endpoint
		if ($request->get_route() !== '/vibemap/v1/places-data') {
			return $result;
		}

		// Start timing measurement
		$json_start = microtime(true);
		$response_data = $result->get_data();
		$json_string = json_encode($response_data);
		$json_time = round((microtime(true) - $json_start) * 1000, 2);

		// Calculate payload information
		$payload_size = strlen($json_string);
		$payload_mb = round($payload_size / 1024 / 1024, 2);
		$restaurant_count = count($response_data['places'] ?? []);

		// Store timing data
		self::$timing_data[] = [
			'timestamp' => time(),
			'json_time_ms' => $json_time,
			'payload_size_bytes' => $payload_size,
			'payload_size_mb' => $payload_mb,
			'restaurant_count' => $restaurant_count,
		];

		// Add timing info to debug data if available
		if (isset($response_data['debug_info'])) {
			$response_data['debug_info']['json_encoding_ms'] = $json_time;
			$response_data['debug_info']['payload_size_mb'] = $payload_mb;
			$response_data['debug_info']['total_backend_time_ms'] =
				($response_data['debug_info']['timing']['data_merger_ms'] ?? 0) + $json_time;
			$result->set_data($response_data);
		}

		// Only log detailed timing in debug mode
		if (defined('WP_DEBUG') && WP_DEBUG) {
			error_log('HRW JSON Timing: JSON encoding took ' . $json_time . 'ms for ' . $payload_mb . 'MB payload');
		}

		// Log performance issues even in production
		if ($json_time > 100) { // More than 100ms is concerning
			error_log('HRW JSON Timing: SLOW JSON encoding detected: ' . $json_time . 'ms for ' . $payload_mb . 'MB');
		}

		return $result;
	}

	/**
	 * Get timing statistics
	 * 
	 * @return array Timing statistics
	 */
	public static function get_timing_stats()
	{
		if (empty(self::$timing_data)) {
			return [];
		}

		$recent_data = array_slice(self::$timing_data, -10); // Last 10 measurements
		$total_time = array_sum(array_column($recent_data, 'json_time_ms'));
		$avg_time = round($total_time / count($recent_data), 2);

		return [
			'recent_measurements' => count($recent_data),
			'average_json_time_ms' => $avg_time,
			'total_measurements' => count(self::$timing_data),
			'last_measurement' => end($recent_data)
		];
	}

	/**
	 * Clear timing data
	 */
	public static function clear_timing_data()
	{
		self::$timing_data = [];

		if (defined('WP_DEBUG') && WP_DEBUG) {
			error_log('HRW JSON Timing: Cleared all timing data');
		}
	}

	/**
	 * Get performance report for admin
	 * 
	 * @return array Performance report
	 */
	public static function get_performance_report()
	{
		$stats = self::get_timing_stats();

		if (empty($stats)) {
			return ['status' => 'No timing data available'];
		}

		$status = 'Good';
		if ($stats['average_json_time_ms'] > 50) {
			$status = 'Needs optimization';
		} elseif ($stats['average_json_time_ms'] > 20) {
			$status = 'Acceptable';
		}

		return [
			'status' => $status,
			'average_time' => $stats['average_json_time_ms'] . 'ms',
			'measurements' => $stats['recent_measurements'],
			'recommendation' => $stats['average_json_time_ms'] > 50
				? 'Consider payload size reduction'
				: 'JSON performance is optimal'
		];
	}
}
