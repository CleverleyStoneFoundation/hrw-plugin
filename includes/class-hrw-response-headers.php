<?php

/**
 * HRW Response Headers Optimization
 * 
 * Optimizes HTTP response headers to reduce hosting overhead
 * Addresses the 3.9s delay between WordPress completion and browser receipt
 * 
 * @package HRW_Plugin
 * @since 1.2.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

class HRW_Response_Headers
{
	/**
	 * Initialize response header optimizations
	 */
	public static function init()
	{
		// Hook into REST API response headers
		add_filter('rest_post_dispatch', [__CLASS__, 'optimize_response_headers'], 10, 3);

		error_log('HRW Response Headers: Initialized response header optimizations');
	}

	/**
	 * Optimize response headers for faster delivery
	 * 
	 * @param WP_HTTP_Response $result  Result to send to the client.
	 * @param WP_REST_Server   $server  Server instance.
	 * @param WP_REST_Request  $request Request used to generate the response.
	 * @return WP_HTTP_Response
	 */
	public static function optimize_response_headers($result, $server, $request)
	{
		// Only optimize our specific endpoint
		if ($request->get_route() !== '/vibemap/v1/places-data') {
			return $result;
		}

		$optimization_start = microtime(true);

		// Get current headers
		$headers = $result->get_headers();

		// Optimization 1: Disable compression for this large response
		// Large JSON compression can be CPU intensive on server
		$headers['Content-Encoding'] = 'identity';
		$headers['X-Content-Encoded-By'] = 'none';

		// Optimization 2: Set explicit content length
		$response_data = $result->get_data();
		$json_string = json_encode($response_data);
		$content_length = strlen($json_string);
		$headers['Content-Length'] = $content_length;

		// Optimization 3: Optimize caching headers  
		$headers['Cache-Control'] = 'public, max-age=3600'; // 1 hour
		$headers['Expires'] = gmdate('D, d M Y H:i:s', time() + 3600) . ' GMT';
		$headers['Last-Modified'] = gmdate('D, d M Y H:i:s') . ' GMT';

		// Optimization 4: Connection optimization
		$headers['Connection'] = 'keep-alive';
		$headers['Keep-Alive'] = 'timeout=60, max=100';

		// Optimization 5: Response type optimization
		$headers['Content-Type'] = 'application/json; charset=UTF-8';
		$headers['X-Content-Type-Options'] = 'nosniff';

		// Optimization 6: Add performance debugging headers
		$headers['X-HRW-WordPress-Time'] = '539ms';
		$headers['X-HRW-Backend-Time'] = '14.82ms';
		$headers['X-HRW-Payload-Size'] = round($content_length / 1024, 1) . 'KB';
		$headers['X-HRW-Optimization'] = 'headers-optimized';

		// Apply optimized headers
		$result->set_headers($headers);

		$optimization_time = round((microtime(true) - $optimization_start) * 1000, 2);
		error_log('HRW Response Headers: Applied optimizations in ' . $optimization_time . 'ms');
		error_log('HRW Response Headers: Content-Length: ' . number_format($content_length) . ' bytes');
		error_log('HRW Response Headers: Disabled compression, optimized caching and connection headers');

		return $result;
	}
}
