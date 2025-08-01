<?php

/**
 * HRW Restaurant Loader Class
 * 
 * Handles batched loading of HRW restaurants with memory optimization
 * 
 * @package HRW_Plugin
 * @since 1.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

class HRW_Restaurant_Loader
{

	/**
	 * Default batch size for restaurant loading
	 */
	const DEFAULT_BATCH_SIZE = 50;

	/**
	 * Maximum number of restaurants to process (safety limit)
	 */
	const MAX_RESTAURANTS = 500;

	/**
	 * Memory usage threshold (80% of PHP memory limit)
	 */
	const MEMORY_THRESHOLD = 0.8;

	/**
	 * Get HRW restaurants with batched loading and memory optimization
	 * 
	 * @param array $filters Optional filters to apply
	 * @return array Array of restaurant post objects
	 */
	public static function get_restaurants($filters = [])
	{
		error_log('HRW Loader: Starting batched restaurant loading');

		// Check if hrw_restaurants post type exists
		if (!post_type_exists('hrw_restaurants')) {
			error_log('HRW Loader: ERROR - hrw_restaurants post type does not exist!');
			return [];
		}

		// Get restaurant IDs first (memory efficient)
		$restaurant_ids = self::get_restaurant_ids($filters);

		if (empty($restaurant_ids)) {
			error_log('HRW Loader: No restaurants found matching filters');
			return [];
		}

		// Apply safety limit
		if (count($restaurant_ids) > self::MAX_RESTAURANTS) {
			error_log('HRW Loader: Limiting to ' . self::MAX_RESTAURANTS . ' restaurants to prevent memory exhaustion');
			$restaurant_ids = array_slice($restaurant_ids, 0, self::MAX_RESTAURANTS);
		}

		// Load restaurants in batches
		return self::load_restaurants_in_batches($restaurant_ids);
	}

	/**
	 * Get restaurant IDs with optional filtering
	 * 
	 * @param array $filters Optional filters to apply
	 * @return array Array of restaurant IDs
	 */
	private static function get_restaurant_ids($filters = [])
	{
		$args = [
			'post_type'      => 'hrw_restaurants',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'cache_results'  => false,
		];

		// Apply filters if provided
		if (!empty($filters['meta_query'])) {
			$args['meta_query'] = $filters['meta_query'];
		}

		$restaurant_ids = get_posts($args);
		error_log('HRW Loader: Found ' . count($restaurant_ids) . ' total restaurant IDs');

		// Apply additional filtering if needed
		if (!empty($filters['year']) || !empty($filters['status'])) {
			$restaurant_ids = self::filter_by_meta($restaurant_ids, $filters);
		}

		return $restaurant_ids;
	}

	/**
	 * Filter restaurant IDs by meta values
	 * 
	 * @param array $restaurant_ids Array of restaurant IDs
	 * @param array $filters Filters to apply
	 * @return array Filtered array of restaurant IDs
	 */
	private static function filter_by_meta($restaurant_ids, $filters)
	{
		$filtered_ids = [];

		foreach ($restaurant_ids as $id) {
			$include = true;

			// Check year filter
			if (!empty($filters['year'])) {
				$menu_year = get_post_meta($id, '_menu_year', true);
				if ($menu_year !== $filters['year']) {
					$include = false;
				}
			}

			// Check status filter
			if (!empty($filters['status'])) {
				$menu_status = get_post_meta($id, '_menu_status', true);
				if ($menu_status !== $filters['status']) {
					$include = false;
				}
			}

			if ($include) {
				$filtered_ids[] = $id;
			}
		}

		error_log('HRW Loader: Filtered to ' . count($filtered_ids) . ' restaurants after meta filtering');
		return $filtered_ids;
	}

	/**
	 * Load restaurants in batches with memory monitoring
	 * 
	 * @param array $restaurant_ids Array of restaurant IDs to load
	 * @return array Array of restaurant post objects
	 */
	private static function load_restaurants_in_batches($restaurant_ids)
	{
		$batch_size = self::DEFAULT_BATCH_SIZE;
		$restaurants = [];
		$total_ids = count($restaurant_ids);

		error_log('HRW Loader: Loading ' . $total_ids . ' restaurants in batches of ' . $batch_size);

		// Process in batches
		for ($i = 0; $i < $total_ids; $i += $batch_size) {
			$batch_ids = array_slice($restaurant_ids, $i, $batch_size);

			// Check memory usage before each batch
			$memory_usage = memory_get_usage(true);
			$memory_limit = ini_get('memory_limit');
			$memory_limit_bytes = wp_convert_hr_to_bytes($memory_limit);

			if ($memory_usage > ($memory_limit_bytes * self::MEMORY_THRESHOLD)) {
				error_log('HRW Loader: Memory usage too high (' . size_format($memory_usage) . ' of ' . $memory_limit . '), stopping at batch ' . ($i / $batch_size + 1));
				break;
			}

			// Load batch of restaurants
			$batch_restaurants = get_posts([
				'post_type' => 'hrw_restaurants',
				'post__in' => $batch_ids,
				'posts_per_page' => $batch_size,
				'post_status' => 'publish',
				'orderby' => 'title',
				'order' => 'ASC',
			]);

			$restaurants = array_merge($restaurants, $batch_restaurants);

			// Force garbage collection after each batch
			if (function_exists('gc_collect_cycles')) {
				gc_collect_cycles();
			}

			// Log progress for first and last batches
			if ($i === 0 || ($i + $batch_size) >= $total_ids) {
				error_log('HRW Loader: Loaded batch ' . ($i / $batch_size + 1) . ' (' . count($batch_restaurants) . ' restaurants, Memory: ' . size_format($memory_usage) . ')');
			}
		}

		error_log('HRW Loader: Successfully loaded ' . count($restaurants) . ' restaurants');
		return $restaurants;
	}

	/**
	 * Get current memory usage information
	 * 
	 * @return array Memory usage information
	 */
	public static function get_memory_info()
	{
		$memory_usage = memory_get_usage(true);
		$memory_limit = ini_get('memory_limit');
		$memory_limit_bytes = wp_convert_hr_to_bytes($memory_limit);
		$memory_percent = ($memory_usage / $memory_limit_bytes) * 100;

		return [
			'usage' => $memory_usage,
			'usage_formatted' => size_format($memory_usage),
			'limit' => $memory_limit,
			'limit_bytes' => $memory_limit_bytes,
			'percent' => round($memory_percent, 2),
			'is_high' => $memory_percent > (self::MEMORY_THRESHOLD * 100)
		];
	}
}
