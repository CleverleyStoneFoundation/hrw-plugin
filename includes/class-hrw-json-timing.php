<?php

/**
 * HRW JSON Timing Class
 * 
 * Intercepts WordPress JSON encoding to measure serialization time
 * This helps identify if JSON encoding is the 5-second bottleneck
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
	 * Track if we've already hooked into json encoding
	 */
	private static $hooked = false;

	/**
	 * Store timing data
	 */
	private static $timing_data = [];

	/**
	 * Initialize JSON timing hooks
	 */
	public static function init()
	{
		if (self::$hooked) {
			return;
		}

		// Hook into REST API response before it gets JSON encoded
		add_filter('rest_post_dispatch', [__CLASS__, 'time_json_encoding'], 10, 3);

		self::$hooked = true;
		error_log('HRW JSON Timing: Initialized JSON timing hooks');
	}

	/**
	 * Time the JSON encoding process
	 * 
	 * @param WP_HTTP_Response $result  Result to send to the client.
	 * @param WP_REST_Server   $server  Server instance.
	 * @param WP_REST_Request  $request Request used to generate the response.
	 * @return WP_HTTP_Response
	 */
	public static function time_json_encoding($result, $server, $request)
	{
		// Only time our specific endpoint
		if ($request->get_route() !== '/vibemap/v1/places-data') {
			return $result;
		}

		error_log('HRW JSON Timing: [TIMING] Starting JSON encoding measurement at ' . date('H:i:s.') . substr(microtime(), 2, 3));

		// Get the data that will be JSON encoded
		$response_data = $result->get_data();

		// Time JSON encoding
		$json_start = microtime(true);
		$json_string = json_encode($response_data);
		$json_time = round((microtime(true) - $json_start) * 1000, 2);

		// Calculate payload size
		$payload_size = strlen($json_string);
		$payload_mb = round($payload_size / 1024 / 1024, 2);

		// Log detailed timing information
		error_log('HRW JSON Timing: [TIMING] JSON encoding took ' . $json_time . 'ms');
		error_log('HRW JSON Timing: [TIMING] Payload size: ' . $payload_mb . 'MB (' . number_format($payload_size) . ' bytes)');
		error_log('HRW JSON Timing: [TIMING] Average per restaurant: ' . round($json_time / 252, 2) . 'ms');

		// Store timing data for potential optimization
		self::$timing_data[] = [
			'timestamp' => time(),
			'json_time_ms' => $json_time,
			'payload_size_bytes' => $payload_size,
			'payload_size_mb' => $payload_mb,
			'restaurant_count' => count($response_data['places'] ?? []),
		];

		// Add timing info to response data for debugging
		if (isset($response_data['debug_info'])) {
			$response_data['debug_info']['json_encoding_ms'] = $json_time;
			$response_data['debug_info']['payload_size_mb'] = $payload_mb;
			$response_data['debug_info']['total_backend_time_ms'] =
				($response_data['debug_info']['timing']['data_merger_ms'] ?? 0) + $json_time;

			// Update the result with timing info
			$result->set_data($response_data);
		}

		error_log('HRW JSON Timing: [TIMING] JSON encoding measurement complete at ' . date('H:i:s.') . substr(microtime(), 2, 3));
		error_log('HRW JSON Timing: [TIMING] ===== JSON TIMING COMPLETE =====');

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
			return [
				'message' => 'No timing data collected yet',
				'suggestion' => 'Make an API call to /vibemap/v1/places-data to collect timing data'
			];
		}

		$latest = end(self::$timing_data);
		$all_times = array_column(self::$timing_data, 'json_time_ms');

		return [
			'latest_call' => $latest,
			'average_json_time_ms' => round(array_sum($all_times) / count($all_times), 2),
			'min_json_time_ms' => min($all_times),
			'max_json_time_ms' => max($all_times),
			'total_calls_measured' => count(self::$timing_data),
		];
	}

	/**
	 * Clear timing data
	 */
	public static function clear_timing_data()
	{
		self::$timing_data = [];
		error_log('HRW JSON Timing: Cleared all timing data');
	}
}
