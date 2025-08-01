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
	 * Get restaurant IDs with optimized database-level filtering
	 * 
	 * @param array $filters Optional filters to apply
	 * @return array Array of restaurant IDs
	 */
	private static function get_restaurant_ids($filters = [])
	{
		$args = [
			'post_type'      => 'hrw_restaurants',
			'posts_per_page' => self::MAX_RESTAURANTS,
			'post_status'    => 'publish',
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'cache_results'  => false,
			'orderby'        => 'title',
			'order'          => 'ASC',
		];

		// OPTIMIZATION: Build meta_query for database-level filtering (eliminates N+1 queries)
		$meta_query = ['relation' => 'AND'];

		// Apply year filter at database level
		if (!empty($filters['year'])) {
			$meta_query[] = [
				'key'     => '_menu_year',
				'value'   => $filters['year'],
				'compare' => '='
			];
		}

		// Apply status filter at database level  
		if (!empty($filters['status'])) {
			$meta_query[] = [
				'key'     => '_menu_status',
				'value'   => $filters['status'],
				'compare' => '='
			];
		}

		// Apply custom meta_query if provided
		if (!empty($filters['meta_query'])) {
			$meta_query = array_merge($meta_query, $filters['meta_query']);
		}

		// Only add meta_query if we have filters
		if (count($meta_query) > 1) {
			$args['meta_query'] = $meta_query;
			error_log('HRW Loader: Using database-level meta filtering: ' . json_encode($meta_query));
		}

		$restaurant_ids = get_posts($args);
		error_log('HRW Loader: Found ' . count($restaurant_ids) . ' restaurant IDs with optimized database filtering');

		return $restaurant_ids;
	}

	/**
	 * REMOVED: filter_by_meta - replaced with database-level meta_query filtering
	 * This eliminates the N+1 query problem where we were making individual 
	 * get_post_meta() calls for each restaurant ID.
	 */

	/**
	 * Bulk load all meta data for multiple restaurants in a single query
	 * This eliminates N+1 queries during transformation
	 * 
	 * @param array $restaurant_ids Array of restaurant IDs
	 * @param array $meta_keys Array of meta keys to load
	 * @return array Organized meta data [post_id][meta_key] = meta_value
	 */
	public static function get_bulk_restaurant_meta($restaurant_ids, $meta_keys = [])
	{
		if (empty($restaurant_ids) || empty($meta_keys)) {
			return [];
		}

		global $wpdb;

		// Sanitize inputs
		$ids_placeholder = implode(',', array_fill(0, count($restaurant_ids), '%d'));
		$keys_placeholder = implode(',', array_fill(0, count($meta_keys), '%s'));

		// Prepare the query
		$query = "
			SELECT post_id, meta_key, meta_value 
			FROM {$wpdb->postmeta} 
			WHERE post_id IN ($ids_placeholder) 
			AND meta_key IN ($keys_placeholder)
		";

		// Combine parameters: IDs first, then keys
		$params = array_merge($restaurant_ids, $meta_keys);

		// Execute bulk query
		$results = $wpdb->get_results($wpdb->prepare($query, $params));

		// Organize results into [post_id][meta_key] = meta_value structure
		$organized_meta = [];
		foreach ($results as $row) {
			// CRITICAL FIX: Unserialize data just like ACF's get_field() does
			$value = $row->meta_value;

			// Check if this looks like serialized data and unserialize it
			if (is_string($value) && (strpos($value, 'a:') === 0 || strpos($value, 's:') === 0 || strpos($value, 'i:') === 0 || strpos($value, 'O:') === 0)) {
				$unserialized = maybe_unserialize($value);
				if ($unserialized !== false) {
					$value = $unserialized;
				}
			}

			$organized_meta[$row->post_id][$row->meta_key] = $value;
		}

		error_log('HRW Loader: Bulk loaded ' . count($results) . ' meta entries for ' . count($restaurant_ids) . ' restaurants in single query');

		return $organized_meta;
	}

	/**
	 * Get all meta keys needed for restaurant transformation
	 * 
	 * @return array Array of meta keys used during transformation
	 */
	public static function get_transformation_meta_keys()
	{
		return [
			// VibeMap integration
			'vibemap_id',

			// Coordinates
			'latitude',
			'longitude',
			'full_address',

			// Restaurant details
			'neighborhood',
			'vibes_from_vibemap',

			// HRW Card Data Fields (eliminates 10 queries per restaurant)
			'restaurant_title',
			'_hrw_menus',
			'reservations',
			'reservation_links',
			'reservation_phone_number',
			'reservation_notes',
			'cuisine_types',
			'restaurant_photo',
			'photos_of_hrw_menu_items',

			// Menu status (for filtering)
			'_menu_year',
			'_menu_status'
		];
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
