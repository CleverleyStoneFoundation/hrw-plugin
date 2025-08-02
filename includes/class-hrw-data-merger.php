<?php

/**
 * HRW Data Merger Class
 * 
 * Handles merging of HRW restaurant data with VibeMap places data
 * 
 * @package HRW_Plugin
 * @since 1.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

// Include restaurant card functions
require_once(plugin_dir_path(dirname(__FILE__)) . 'restaurant_card.php');

class HRW_Data_Merger
{

	/**
	 * Memory threshold for processing warnings
	 */
	const MEMORY_THRESHOLD = 0.8;

	/**
	 * Merge HRW restaurant data with VibeMap places data
	 * 
	 * @param array $original_data Original VibeMap data
	 * @param WP_REST_Request $request The REST request object
	 * @return array Merged data
	 */
	public static function merge_restaurant_data($original_data, $request)
	{
		$merge_start = microtime(true);

		// Get HRW restaurants using the optimized loader
		$filters = [
			'year' => '2025',
			'status' => '4'
		];
		$hrw_restaurants = HRW_Restaurant_Loader::get_restaurants($filters);

		if (empty($hrw_restaurants)) {
			error_log('HRW Merger: No HRW restaurants found, returning empty results');
			return self::get_empty_response();
		}

		// Build data directly from HRW restaurants
		$merged_data = self::process_restaurants_directly($hrw_restaurants, $request);

		// Log final results
		self::log_final_results($merged_data, count($hrw_restaurants));

		// Log performance summary
		$merge_time = round((microtime(true) - $merge_start) * 1000, 2);

		// Only log detailed timing in debug mode
		if (defined('WP_DEBUG') && WP_DEBUG) {
			error_log('HRW Merger: Processed ' . count($hrw_restaurants) . ' restaurants in ' . $merge_time . 'ms');
		}

		// Log performance issues even in production
		if ($merge_time > 1000) { // More than 1 second
			error_log('HRW Merger: SLOW merge detected: ' . $merge_time . 'ms for ' . count($hrw_restaurants) . ' restaurants');
		}

		return $merged_data;
	}

	/**
	 * Get empty response structure
	 * 
	 * @return array Empty response structure
	 */
	private static function get_empty_response()
	{
		return [
			'places'     => [],
			'categories' => [],
			'vibes'      => [],
			'tags'       => [],
			'taxonomies' => []
		];
	}


	/**
	 * Process restaurants directly with optimized bulk meta loading
	 * 
	 * @param array $hrw_restaurants Array of HRW restaurant posts with vibemap_id
	 * @param WP_REST_Request $request The REST request object
	 * @return array Processed data
	 */
	private static function process_restaurants_directly($hrw_restaurants, $request)
	{
		if (empty($hrw_restaurants)) {
			error_log('HRW Error: No restaurants provided for processing');
			return self::build_final_response([], [], [], [], [], $request);
		}

		$transformed_places = [];
		$used_categories = [];
		$used_vibes = [];
		$used_tags = [];
		$used_custom_taxonomies = [];

		// Start query profiling for performance monitoring
		$query_start_time = microtime(true);
		$query_count_start = self::get_query_count();

		// Bulk load meta data for all restaurants (eliminates N+1 queries)
		$restaurant_ids = array_map(function ($restaurant) {
			return $restaurant->ID;
		}, $hrw_restaurants);
		$meta_keys = HRW_Restaurant_Loader::get_transformation_meta_keys();

		$bulk_meta = HRW_Restaurant_Loader::get_bulk_restaurant_meta($restaurant_ids, $meta_keys);

		// Profile bulk meta loading performance
		$bulk_meta_time = microtime(true) - $query_start_time;
		$bulk_meta_queries = self::get_query_count() - $query_count_start;

		foreach ($hrw_restaurants as $index => $hrw_restaurant) {
			// Monitor memory usage and break if getting too high
			$memory_info = HRW_Restaurant_Loader::get_memory_info();

			if ($memory_info['is_high']) {
				error_log('HRW Memory: High usage (' . $memory_info['usage_formatted'] . '), stopping at restaurant ' . ($index + 1));
				break;
			}

			// Get pre-loaded meta for this restaurant
			$restaurant_meta = isset($bulk_meta[$hrw_restaurant->ID]) ? $bulk_meta[$hrw_restaurant->ID] : [];

			// Use optimized transformation with bulk meta data
			$transformed_place = self::transform_hrw_restaurant_to_place_safe($hrw_restaurant, $restaurant_meta, $used_custom_taxonomies);

			if ($transformed_place) {
				$transformed_places[] = $transformed_place;

				// Track used taxonomies
				self::track_used_taxonomies($transformed_place, $used_categories, $used_vibes, $used_tags);

				// Track custom taxonomies
				foreach ($transformed_place as $key => $value) {
					// Check if this is a custom taxonomy (not one of the standard fields)
					if (!in_array($key, ['id', 'title', 'slug', 'content', 'permalink', 'featured_image', 'categories', 'vibes', 'tags', 'meta']) && is_array($value)) {
						foreach ($value as $term) {
							if (!isset($used_custom_taxonomies[$key])) {
								$used_custom_taxonomies[$key] = [];
							}
							if (isset($term['id'])) {
								$used_custom_taxonomies[$key][$term['id']] = $term;
							}
						}
					}
				}
			}
		}

		// Performance monitoring (only log if slow or memory issues)
		$total_time = microtime(true) - $query_start_time;

		if ($total_time > 5.0 || count($transformed_places) < count($hrw_restaurants) * 0.9) {
			$total_queries = self::get_query_count() - $query_count_start;
			$transformation_time = $total_time - $bulk_meta_time;

			error_log('HRW Performance: Total ' . round($total_time * 1000, 2) . 'ms, ' . $total_queries . ' queries, ' . count($transformed_places) . '/' . count($hrw_restaurants) . ' restaurants');
		}

		// Build final response
		return self::build_final_response($transformed_places, $used_categories, $used_vibes, $used_tags, $used_custom_taxonomies, $request);
	}

	/**
	 * EMERGENCY: Legacy processing method using original working transformation
	 * This bypasses all bulk meta optimization and uses individual ACF calls
	 * 
	 * @param array $hrw_restaurants Array of HRW restaurant posts
	 * @param WP_REST_Request $request The REST request object
	 * @return array Processed data using legacy method
	 */
	private static function process_restaurants_legacy_method($hrw_restaurants, $request)
	{
		error_log('HRW Emergency: Using legacy processing method for ' . count($hrw_restaurants) . ' restaurants');

		$transformed_places = [];
		$used_categories = [];
		$used_vibes = [];
		$used_tags = [];
		$used_custom_taxonomies = [];

		foreach ($hrw_restaurants as $index => $hrw_restaurant) {
			// Monitor memory usage and break if getting too high
			$memory_info = HRW_Restaurant_Loader::get_memory_info();

			if ($memory_info['is_high']) {
				error_log('HRW Legacy: Memory usage too high (' . $memory_info['usage_formatted'] . ' of ' . $memory_info['limit'] . '), stopping at restaurant ' . ($index + 1));
				break;
			}

			// Use legacy transformation (individual ACF calls)
			$transformed_place = self::transform_hrw_restaurant_to_place($hrw_restaurant, $used_custom_taxonomies);

			if ($transformed_place) {
				$transformed_places[] = $transformed_place;

				// Track used taxonomies
				self::track_used_taxonomies($transformed_place, $used_categories, $used_vibes, $used_tags);
			}
		}

		error_log('HRW Legacy: Successfully processed ' . count($transformed_places) . '/' . count($hrw_restaurants) . ' restaurants');

		// Build final response
		return self::build_final_response($transformed_places, $used_categories, $used_vibes, $used_tags, $used_custom_taxonomies, $request);
	}

	/**
	 * Get current database query count for performance profiling
	 * 
	 * @return int Current query count
	 */
	private static function get_query_count()
	{
		global $wpdb;
		return $wpdb->num_queries;
	}

	/**
	 * Process a single restaurant
	 * 
	 * @param WP_Post $hrw_restaurant The HRW restaurant post
	 * @param array $vibemap_places_map Map of VibeMap places
	 * @param array &$used_custom_taxonomies Reference to used custom taxonomies
	 * @return array|null Merged place data or null if no match
	 */
	private static function process_single_restaurant($hrw_restaurant, $vibemap_places_map, &$used_custom_taxonomies)
	{
		// Get the vibemap_id from ACF field or post meta
		$vibemap_id = self::get_vibemap_id($hrw_restaurant);

		// Only process restaurants that have a matching VibeMap place
		if (empty($vibemap_id) || !isset($vibemap_places_map[$vibemap_id])) {
			// Minimal logging for skipped restaurants
			if (empty($vibemap_id)) {
				// error_log('HRW Merger: Skipping "' . $hrw_restaurant->post_title . '" - no vibemap_id');
			}
			return null;
		}

		$vibemap_place = $vibemap_places_map[$vibemap_id];

		// Start with the COMPLETE VibeMap place structure
		$merged_place = $vibemap_place;

		// Override specific fields from HRW (minimal overrides)
		$merged_place['title'] = $hrw_restaurant->post_title;
		$merged_place['slug'] = $hrw_restaurant->post_name;
		$merged_place['permalink'] = get_permalink($hrw_restaurant->ID);

		// Use HRW content if not empty
		if (!empty($hrw_restaurant->post_content)) {
			$merged_place['content'] = $hrw_restaurant->post_content;
		}

		// Use HRW featured image if available
		$hrw_featured_image = get_the_post_thumbnail_url($hrw_restaurant->ID, 'full');
		if ($hrw_featured_image) {
			$merged_place['featured_image'] = $hrw_featured_image;
		}

		// Keep the original VibeMap ID - critical for the map
		$merged_place['meta']['hrw_post_id'] = $hrw_restaurant->ID;
		$merged_place['meta']['is_hrw_restaurant'] = true;

		// Update address and coordinates from HRW data
		self::update_location_data($merged_place, $hrw_restaurant);

		// Add HRW custom taxonomies
		self::add_custom_taxonomies($merged_place, $hrw_restaurant, $used_custom_taxonomies);

		// TIMING: Generate and add custom card HTML
		$card_start = microtime(true);
		$card_data = get_hrw_card_data($hrw_restaurant->ID);
		$card_data_time = round((microtime(true) - $card_start) * 1000, 2);

		if ($card_data) {
			$html_start = microtime(true);
			$custom_html = generate_hrw_card_html($card_data);
			$html_time = round((microtime(true) - $html_start) * 1000, 2);

			// Add to meta for generic access
			$merged_place['meta']['custom_card_html'] = $custom_html;

			$total_card_time = round((microtime(true) - $card_start) * 1000, 2);

			// Only log for first few restaurants to avoid spam
			static $card_timing_logged = 0;
			if ($card_timing_logged < 3) {
				error_log('HRW Merger: [TIMING] Card generation for "' . $hrw_restaurant->post_title . '" - Data: ' . $card_data_time . 'ms, HTML: ' . $html_time . 'ms, Total: ' . $total_card_time . 'ms');
				$card_timing_logged++;
			}
		}

		// Minimal logging for merged place (no JSON encoding)
		// error_log('HRW Merger: Merged "' . $merged_place['title'] . '" (ID: ' . ($merged_place['id'] ?? 'N/A') . ')');

		return $merged_place;
	}

	/**
	 * Get vibemap_id from restaurant post
	 * 
	 * @param WP_Post $hrw_restaurant The HRW restaurant post
	 * @return string|null The vibemap_id or null if not found
	 */
	private static function get_vibemap_id($hrw_restaurant)
	{
		$vibemap_id = null;

		// Try ACF field first
		if (function_exists('get_field')) {
			$vibemap_id = get_field('vibemap_id', $hrw_restaurant->ID);
		}

		// Fallback to direct post meta
		if (empty($vibemap_id)) {
			$vibemap_id = get_post_meta($hrw_restaurant->ID, 'vibemap_id', true);
		}

		// Check alternative field names
		if (empty($vibemap_id)) {
			$possible_fields = ['vibemap_place_id'];
			foreach ($possible_fields as $field) {
				$vibemap_id = get_post_meta($hrw_restaurant->ID, $field, true);
				if (!empty($vibemap_id)) {
					break;
				}
			}
		}

		return $vibemap_id;
	}


	/**
	 * Add custom taxonomies to merged place
	 * 
	 * @param array &$merged_place Reference to merged place data
	 * @param WP_Post $hrw_restaurant The HRW restaurant post
	 * @param array &$used_custom_taxonomies Reference to used custom taxonomies
	 */
	private static function add_custom_taxonomies(&$merged_place, $hrw_restaurant, &$used_custom_taxonomies)
	{
		// Get HRW custom taxonomies for this restaurant
		$hrw_custom_taxonomies = vibemap_hrw_get_place_custom_taxonomies($hrw_restaurant->ID);

		// Add HRW taxonomies to the merged place
		foreach ($hrw_custom_taxonomies as $tax_slug => $terms) {
			// Store in meta with proper prefix for the map to access
			$merged_place['meta']['vibemap_place_' . $tax_slug] = $terms;

			// Also add as top-level properties for filtering with original slug
			$merged_place[$tax_slug] = $terms;

			// Track used terms
			foreach ($terms as $term) {
				if (!isset($used_custom_taxonomies[$tax_slug])) {
					$used_custom_taxonomies[$tax_slug] = [];
				}
				$used_custom_taxonomies[$tax_slug][$term['id']] = $term;
			}
		}
	}

	/**
	 * Track used taxonomies from merged place
	 * 
	 * @param array $merged_place The merged place data
	 * @param array &$used_categories Reference to used categories
	 * @param array &$used_vibes Reference to used vibes
	 * @param array &$used_tags Reference to used tags
	 */
	private static function track_used_taxonomies($merged_place, &$used_categories, &$used_vibes, &$used_tags)
	{
		// Check in properties for the new structure
		if (isset($merged_place['properties'])) {
			if (isset($merged_place['properties']['categories']) && is_array($merged_place['properties']['categories'])) {
				foreach ($merged_place['properties']['categories'] as $cat) {
					if (is_array($cat) && isset($cat['id'])) {
						$used_categories[$cat['id']] = true;
					}
				}
			}
			if (isset($merged_place['properties']['vibes']) && is_array($merged_place['properties']['vibes'])) {
				foreach ($merged_place['properties']['vibes'] as $vibe) {
					if (is_array($vibe) && isset($vibe['id'])) {
						$used_vibes[$vibe['id']] = true;
					}
				}
			}
			if (isset($merged_place['properties']['tags']) && is_array($merged_place['properties']['tags'])) {
				foreach ($merged_place['properties']['tags'] as $tag) {
					if (is_array($tag) && isset($tag['id'])) {
						$used_tags[$tag['id']] = true;
					}
				}
			}
		}

		// Fallback to old structure if needed
		if (isset($merged_place['categories']) && is_array($merged_place['categories'])) {
			foreach ($merged_place['categories'] as $cat) {
				if (is_array($cat) && isset($cat['id'])) {
					$used_categories[$cat['id']] = true;
				}
			}
		}
		if (isset($merged_place['vibes']) && is_array($merged_place['vibes'])) {
			foreach ($merged_place['vibes'] as $vibe) {
				if (is_array($vibe) && isset($vibe['id'])) {
					$used_vibes[$vibe['id']] = true;
				}
			}
		}
		if (isset($merged_place['tags']) && is_array($merged_place['tags'])) {
			foreach ($merged_place['tags'] as $tag) {
				if (is_array($tag) && isset($tag['id'])) {
					$used_tags[$tag['id']] = true;
				}
			}
		}
	}

	/**
	 * Build final response structure
	 * 
	 * @param array $merged_places Array of merged places
	 * @param array $used_categories Used categories
	 * @param array $used_vibes Used vibes
	 * @param array $used_tags Used tags
	 * @param array $used_custom_taxonomies Used custom taxonomies
	 * @param WP_REST_Request $request The REST request object
	 * @return array Final response structure
	 */
	private static function build_final_response($merged_places, $used_categories, $used_vibes, $used_tags, $used_custom_taxonomies, $request)
	{
		// TIMING: Start final response building
		$final_start = microtime(true);
		error_log('HRW Merger: [TIMING] Starting final response building at ' . date('H:i:s.') . substr(microtime(), 2, 3));

		// TIMING: Apply preview limit if requested
		$preview_start = microtime(true);
		$preview_limit = $request->get_param('preview_limit');
		if ($preview_limit && $preview_limit > 0 && count($merged_places) > $preview_limit) {
			error_log('HRW Merger: Applying preview limit of ' . $preview_limit);
			$merged_places = array_slice($merged_places, 0, $preview_limit);
		}
		$preview_time = round((microtime(true) - $preview_start) * 1000, 2);
		error_log('HRW Merger: [TIMING] Preview limit processing took ' . $preview_time . 'ms');

		// TIMING: Build HRW custom taxonomies for the response
		$taxonomy_start = microtime(true);
		$hrw_taxonomies = [];
		foreach ($used_custom_taxonomies as $tax_slug => $terms) {
			$all_terms = array_values($terms);

			// Sort terms alphabetically by name for better UX
			if (!empty($all_terms)) {
				usort($all_terms, function ($a, $b) {
					return strcasecmp($a['name'], $b['name']);
				});
			}

			if (!empty($all_terms)) {
				// Get taxonomy configuration
				$config = vibemap_hrw_get_taxonomy_config($tax_slug);
				// Use custom display name if set, otherwise generate from slug
				$display_name = !empty($config['display_name']) ? $config['display_name'] : ucwords(str_replace('_', ' ', $tax_slug));

				// Keep the original slug as the key, but include display name in the data
				$hrw_taxonomies[$tax_slug] = [
					'name' => $display_name,  // This is what should be displayed
					'slug' => $tax_slug,      // This is the actual taxonomy slug
					'description' => $display_name . ' for HRW restaurants',
					'icon' => $config['icon'] ?? 'tag',
					'terms' => $all_terms
				];

				// Debug logging
				error_log('HRW Merger: Added taxonomy "' . $tax_slug . '" with display name "' . $display_name . '" and ' . count($all_terms) . ' terms');
			}
		}
		$taxonomy_time = round((microtime(true) - $taxonomy_start) * 1000, 2);
		error_log('HRW Merger: [TIMING] Taxonomy building took ' . $taxonomy_time . 'ms');

		// TIMING: Build response structure
		$structure_start = microtime(true);

		// Map vibes_from_vibemap to top-level vibes array for frontend compatibility
		$vibes_array = [];
		if (isset($hrw_taxonomies['vibes_from_vibemap'])) {
			$vibes_array = $hrw_taxonomies['vibes_from_vibemap']['terms'];
			error_log('HRW Merger: Mapped ' . count($vibes_array) . ' vibes from vibes_from_vibemap to top-level vibes array');
		}

		$response = [
			'places'     => $merged_places,
			'categories' => [], // Empty for HRW-only response
			'vibes'      => $vibes_array, // Map from vibes_from_vibemap for frontend compatibility
			'tags'       => [], // Empty for HRW-only response
			'taxonomies' => $hrw_taxonomies // HRW custom taxonomies
		];
		$structure_time = round((microtime(true) - $structure_start) * 1000, 2);
		error_log('HRW Merger: [TIMING] Response structure building took ' . $structure_time . 'ms');

		// TIMING: Add total count if requested
		$count_start = microtime(true);
		if ($request->get_param('total_count')) {
			$response['total_count'] = count($response['places']);
		}
		$count_time = round((microtime(true) - $count_start) * 1000, 2);
		error_log('HRW Merger: [TIMING] Total count calculation took ' . $count_time . 'ms');

		// TIMING: Final response completion
		$final_time = round((microtime(true) - $final_start) * 1000, 2);
		error_log('HRW Merger: [TIMING] TOTAL final response building took ' . $final_time . 'ms at ' . date('H:i:s.') . substr(microtime(), 2, 3));

		return $response;
	}

	/**
	 * Log final results
	 * 
	 * @param array $merged_data The merged data
	 * @param int $total_restaurants Total restaurants processed
	 */
	private static function log_final_results($merged_data, $total_restaurants)
	{
		error_log('HRW Merger: Final results:');
		error_log('HRW Merger: - Total HRW restaurants processed: ' . $total_restaurants);
		error_log('HRW Merger: - HRW restaurants successfully transformed: ' . count($merged_data['places']));
		error_log('HRW Merger: - Memory usage: ' . HRW_Restaurant_Loader::get_memory_info()['usage_formatted']);
	}

	/**
	 * Optimized transformation using bulk-loaded meta data (eliminates individual DB calls)
	 * 
	 * @param WP_Post $hrw_restaurant HRW restaurant post
	 * @param array $restaurant_meta Pre-loaded meta data for this restaurant
	 * @param array &$used_custom_taxonomies Reference to used custom taxonomies
	 * @return array|null Transformed place data or null if invalid
	 */
	private static function transform_hrw_restaurant_to_place_optimized($hrw_restaurant, $restaurant_meta, &$used_custom_taxonomies)
	{
		// Get the vibemap_id from pre-loaded meta (no DB call)
		$vibemap_id = isset($restaurant_meta['vibemap_id']) ? $restaurant_meta['vibemap_id'] : '';

		// If no vibemap_id, use the WordPress post ID as the identifier
		if (empty($vibemap_id)) {
			$vibemap_id = 'hrw_' . $hrw_restaurant->ID;
		}

		// Get coordinates from pre-loaded meta (no DB calls)
		$latitude = null;
		$longitude = null;

		// Check bulk-loaded coordinates first
		if (isset($restaurant_meta['latitude']) && isset($restaurant_meta['longitude'])) {
			$latitude = floatval($restaurant_meta['latitude']);
			$longitude = floatval($restaurant_meta['longitude']);
		}

		// Try to parse from full_address field if available
		if (($latitude === null || $longitude === null) && isset($restaurant_meta['full_address'])) {
			$address_data = maybe_unserialize($restaurant_meta['full_address']);
			if (is_array($address_data)) {
				if (isset($address_data['lat']) && isset($address_data['lng'])) {
					$latitude = floatval($address_data['lat']);
					$longitude = floatval($address_data['lng']);
				}
			}
		}

		// If no valid coordinates, skip this restaurant
		if ($latitude === null || $longitude === null || $latitude === 0 || $longitude === 0) {
			error_log('HRW Merger: Skipping "' . $hrw_restaurant->post_title . '" - no valid coordinates');
			return null;
		}

		// Get featured image from pre-loaded meta
		$featured_image_url = '';

		// Try photos_of_hrw_menu_items first
		if (isset($restaurant_meta['photos_of_hrw_menu_items']) && !empty($restaurant_meta['photos_of_hrw_menu_items'])) {
			$gallery_images = $restaurant_meta['photos_of_hrw_menu_items'];

			// Handle JSON string or direct value
			if (is_string($gallery_images)) {
				$decoded = json_decode($gallery_images, true);
				if (is_array($decoded) && !empty($decoded[0])) {
					$featured_image_url = is_array($decoded[0]) && isset($decoded[0]['url']) ? $decoded[0]['url'] : $decoded[0];
				}
			}
		}

		// Fallback to restaurant_photo
		if (empty($featured_image_url) && isset($restaurant_meta['restaurant_photo']) && !empty($restaurant_meta['restaurant_photo'])) {
			$featured_image_url = $restaurant_meta['restaurant_photo'];
		}

		// Fallback to WordPress featured image (still need DB call for this)
		if (empty($featured_image_url)) {
			$featured_image_url = get_the_post_thumbnail_url($hrw_restaurant->ID, 'full');
			if (!$featured_image_url) {
				$featured_image_url = get_the_post_thumbnail_url($hrw_restaurant->ID, 'large') ?:
					get_the_post_thumbnail_url($hrw_restaurant->ID, 'medium') ?: '';
			}
		}

		// Use fallback image if still no image
		if (empty($featured_image_url)) {
			$fallback_image = get_option('vibemap_hrw_fallback_image', '');
			if (!empty($fallback_image)) {
				$featured_image_url = $fallback_image;
			}
		}

		// Build the place structure
		$place = [
			'id' => $hrw_restaurant->ID,
			'title' => $hrw_restaurant->post_title,
			'slug' => $hrw_restaurant->post_name,
			'content' => $hrw_restaurant->post_content,
			'permalink' => get_permalink($hrw_restaurant->ID),
			'featured_image' => $featured_image_url,
			'meta' => [
				'vibemap_place_id' => $vibemap_id,
				'vibemap_place_latitude' => strval($latitude),
				'vibemap_place_longitude' => strval($longitude),
				'vibemap_place_address' => '',
				'vibemap_place_full_address' => '',
				'vibemap_place_city' => 'Houston',
				'vibemap_place_state' => 'TX',
				'vibemap_place_neighborhood' => '',
				'vibemap_place_images' => '[]',
				'hrw_post_id' => $hrw_restaurant->ID,
				'is_hrw_restaurant' => true
			],
			'categories' => [],
			'vibes' => [],
			'tags' => []
		];

		// Process address from pre-loaded meta
		if (isset($restaurant_meta['full_address']) && !empty($restaurant_meta['full_address'])) {
			$address_data = maybe_unserialize($restaurant_meta['full_address']);
			if (is_array($address_data) && isset($address_data['address'])) {
				$place['meta']['vibemap_place_address'] = $address_data['address'];
				$place['meta']['vibemap_place_full_address'] = $address_data['address'];
			}
		}

		// Process neighborhood from pre-loaded meta
		if (isset($restaurant_meta['neighborhood']) && !empty($restaurant_meta['neighborhood'])) {
			$neighborhood = $restaurant_meta['neighborhood'];
			if (is_array($neighborhood)) {
				$place['meta']['vibemap_place_neighborhood'] = implode(', ', $neighborhood);
			} else {
				$place['meta']['vibemap_place_neighborhood'] = strval($neighborhood);
			}
		}

		// Process vibes from pre-loaded meta
		if (isset($restaurant_meta['vibes_from_vibemap']) && !empty($restaurant_meta['vibes_from_vibemap'])) {
			$vibes_data = $restaurant_meta['vibes_from_vibemap'];

			if (is_string($vibes_data)) {
				// Handle pipe-separated string
				$vibes_array = array_map('trim', explode('|', $vibes_data));
				$place['vibes'] = [];
				foreach ($vibes_array as $index => $vibe_name) {
					if (!empty($vibe_name)) {
						$place['vibes'][] = [
							'id' => $index + 1,
							'name' => $vibe_name,
							'slug' => sanitize_title($vibe_name)
						];
					}
				}
			} elseif (is_array($vibes_data)) {
				$place['vibes'] = $vibes_data;
			}
		}

		// Add custom taxonomies (minimal ACF calls only if needed)
		$place = self::add_custom_taxonomies_optimized($place, $hrw_restaurant, $used_custom_taxonomies);

		return $place;
	}

	/**
	 * Add custom taxonomies with minimal DB calls
	 * 
	 * @param array $place Place data being built
	 * @param WP_Post $hrw_restaurant Restaurant post
	 * @param array &$used_custom_taxonomies Reference to used taxonomies
	 * @return array Updated place data
	 */
	private static function add_custom_taxonomies_optimized($place, $hrw_restaurant, &$used_custom_taxonomies)
	{
		// Only make ACF calls if absolutely necessary for custom taxonomies
		// Most meta data should already be loaded via bulk query

		// For now, keeping minimal functionality - can be expanded as needed
		// The bulk meta loading has already eliminated most DB calls

		return $place;
	}

	/**
	 * PHASE 2A: Safe transformation using bulk meta with fallback to working methods
	 * This function prioritizes bulk meta data but falls back to individual calls to prevent data loss
	 * 
	 * @param WP_Post $hrw_restaurant Restaurant post object
	 * @param array $restaurant_meta Pre-loaded meta data from bulk query
	 * @param array $used_custom_taxonomies Reference to track custom taxonomies
	 * @return array|null Transformed place data or null if invalid
	 */
	private static function transform_hrw_restaurant_to_place_safe($hrw_restaurant, $restaurant_meta, &$used_custom_taxonomies)
	{
		// Start with basic place structure matching working legacy format
		$place = [
			'id' => $hrw_restaurant->ID, // Integer ID like legacy
			'title' => $hrw_restaurant->post_title,
			'slug' => $hrw_restaurant->post_name,
			'content' => $hrw_restaurant->post_content,
			'permalink' => get_permalink($hrw_restaurant->ID),
			'featured_image' => '',
			'meta' => [
				// VibeMap required meta structure
				'vibemap_place_id' => '',
				'vibemap_place_latitude' => '',
				'vibemap_place_longitude' => '',
				'vibemap_place_address' => '',
				'vibemap_place_full_address' => '',
				'vibemap_place_city' => 'Houston',
				'vibemap_place_state' => 'TX',
				'vibemap_place_neighborhood' => '',
				'vibemap_place_images' => '[]',
				'hrw_post_id' => $hrw_restaurant->ID,
				'is_hrw_restaurant' => true
			],
			'categories' => [],
			'vibes' => [],
			'tags' => []
		];

		// Get vibemap_id - prefer bulk meta, fallback to ACF
		$vibemap_id = isset($restaurant_meta['vibemap_id']) && !empty($restaurant_meta['vibemap_id'])
			? $restaurant_meta['vibemap_id']
			: get_field('vibemap_id', $hrw_restaurant->ID);

		if (empty($vibemap_id)) {
			// Use post ID as fallback like the working version
			$vibemap_id = 'hrw_' . $hrw_restaurant->ID;
		}

		// Set vibemap_place_id in meta
		$place['meta']['vibemap_place_id'] = $vibemap_id;

		// Get coordinates - prefer bulk meta, fallback to ACF
		$latitude = isset($restaurant_meta['latitude']) && !empty($restaurant_meta['latitude'])
			? (float) $restaurant_meta['latitude']
			: (float) get_field('latitude', $hrw_restaurant->ID);

		$longitude = isset($restaurant_meta['longitude']) && !empty($restaurant_meta['longitude'])
			? (float) $restaurant_meta['longitude']
			: (float) get_field('longitude', $hrw_restaurant->ID);

		// Validate coordinates
		if ($latitude === null || $longitude === null || $latitude === 0 || $longitude === 0) {
			return null;
		}

		// Add coordinates to meta in VibeMap format
		$place['meta']['vibemap_place_latitude'] = strval($latitude);
		$place['meta']['vibemap_place_longitude'] = strval($longitude);

		// Get address - prefer bulk meta, fallback to ACF
		$full_address = isset($restaurant_meta['full_address']) && !empty($restaurant_meta['full_address'])
			? $restaurant_meta['full_address']
			: get_field('full_address', $hrw_restaurant->ID);

		if (!empty($full_address)) {
			// Process address data for VibeMap format
			if (is_string($full_address) && strpos($full_address, 'a:') === 0) {
				// Serialized data - unserialize it
				$address_data = maybe_unserialize($full_address);
				if (is_array($address_data) && isset($address_data['address'])) {
					$place['meta']['vibemap_place_full_address'] = $address_data['address'];
					$place['meta']['vibemap_place_address'] = $address_data['address'];
				}
			} else {
				// Direct string or array
				$address_string = is_array($full_address) && isset($full_address['address'])
					? $full_address['address']
					: (string) $full_address;
				$place['meta']['vibemap_place_full_address'] = $address_string;
				$place['meta']['vibemap_place_address'] = $address_string;
			}
		}

		// Get neighborhood - prefer bulk meta, fallback to ACF
		$neighborhood = isset($restaurant_meta['neighborhood']) && !empty($restaurant_meta['neighborhood'])
			? $restaurant_meta['neighborhood']
			: get_field('neighborhood', $hrw_restaurant->ID);

		if (!empty($neighborhood)) {
			// Handle array neighborhood data
			$neighborhood_string = is_array($neighborhood) ? $neighborhood[0] : $neighborhood;

			// Set neighborhood in meta and as taxonomy
			$place['meta']['vibemap_place_neighborhood'] = $neighborhood_string;

			// Safe sanitize_title with fallback
			if (function_exists('sanitize_title')) {
				$neighborhood_slug = sanitize_title($neighborhood_string);
			} else {
				// Fallback: Manual sanitization if WordPress function not available
				$neighborhood_slug = strtolower(preg_replace('/[^a-zA-Z0-9\-_]/', '-', $neighborhood_string));
				$neighborhood_slug = preg_replace('/-+/', '-', $neighborhood_slug);
				$neighborhood_slug = trim($neighborhood_slug, '-');
			}

			$place['neighborhood'] = [[
				'id' => $neighborhood_slug,
				'name' => $neighborhood_string,
				'slug' => $neighborhood_slug
			]];
		}

		// Get vibes - prefer bulk meta, fallback to ACF
		$vibes_from_vibemap = isset($restaurant_meta['vibes_from_vibemap']) && !empty($restaurant_meta['vibes_from_vibemap'])
			? $restaurant_meta['vibes_from_vibemap']
			: get_field('vibes_from_vibemap', $hrw_restaurant->ID);

		if (!empty($vibes_from_vibemap)) {
			// Process vibes into proper format for VibeMap
			$vibes_data = maybe_unserialize($vibes_from_vibemap);
			if (is_array($vibes_data)) {
				$place['vibes'] = [];
				foreach ($vibes_data as $index => $vibe_name) {
					if (!empty($vibe_name)) {
						$place['vibes'][] = [
							'id' => $index + 1,
							'name' => $vibe_name,
							'slug' => sanitize_title($vibe_name)
						];
					}
				}
			} elseif (is_string($vibes_data)) {
				// Handle pipe-separated string
				$vibes_array = array_map('trim', explode('|', $vibes_data));
				$place['vibes'] = [];
				foreach ($vibes_array as $index => $vibe_name) {
					if (!empty($vibe_name)) {
						$place['vibes'][] = [
							'id' => $index + 1,
							'name' => $vibe_name,
							'slug' => sanitize_title($vibe_name)
						];
					}
				}
			}
		}

		// Get featured image using safe method
		$featured_image_url = self::get_featured_image_safe($hrw_restaurant, $restaurant_meta);

		// Simplified image debugging (only for first 3 with images)
		static $simple_debug_count = 0;
		if ($simple_debug_count < 3 && !empty($featured_image_url)) {
			error_log('HRW Image: Restaurant "' . $hrw_restaurant->post_title . '" has image: ' . substr($featured_image_url, -50));
			$simple_debug_count++;
		}

		if (!empty($featured_image_url)) {
			$place['featured_image'] = $featured_image_url;
		} else {
			// Use fallback image if available
			$fallback_image = get_option('vibemap_hrw_fallback_image', '');
			if (empty($fallback_image)) {
				// Try common HRW placeholder locations
				$theme_url = get_template_directory_uri();
				$child_theme_url = get_stylesheet_directory_uri();

				$possible_placeholders = [
					$child_theme_url . '/assets/images/hrw-placeholder.jpg',
					$child_theme_url . '/assets/images/restaurant-placeholder.jpg',
					$theme_url . '/assets/images/hrw-placeholder.jpg',
					$theme_url . '/assets/images/restaurant-placeholder.jpg',
					'https://via.placeholder.com/400x300?text=Restaurant+Image'
				];

				foreach ($possible_placeholders as $placeholder) {
					if (filter_var($placeholder, FILTER_VALIDATE_URL)) {
						$fallback_image = $placeholder;
						break;
					}
				}
			}

			if (!empty($fallback_image)) {
				$place['featured_image'] = $fallback_image;
			}
		}

		// Get additional meta fields
		$additional_meta_keys = ['restaurant_title', 'cuisine_types', 'neighborhood', 'vibes_from_vibemap'];
		foreach ($additional_meta_keys as $meta_key) {
			$meta_value = isset($restaurant_meta[$meta_key]) && !empty($restaurant_meta[$meta_key])
				? $restaurant_meta[$meta_key]
				: get_post_meta($hrw_restaurant->ID, $meta_key, true);

			if (!empty($meta_value)) {
				$place['meta'][$meta_key] = $meta_value;
			}
		}

		// Apply custom taxonomies using the working method (modifies by reference)
		self::add_custom_taxonomies($place, $hrw_restaurant, $used_custom_taxonomies);

		// Generate and add custom card HTML
		try {
			// First attempt: Use optimized path with bulk meta
			$card_data = get_hrw_card_data($hrw_restaurant->ID, $restaurant_meta);

			// Emergency diagnostic: Check if bulk meta path worked
			if (!$card_data || empty($card_data['raw_data']['title'])) {
				// Fallback: Use original method without bulk meta
				$card_data = get_hrw_card_data($hrw_restaurant->ID, null);
			}

			if ($card_data) {
				$custom_html = generate_hrw_card_html($card_data);
				$place['meta']['custom_card_html'] = $custom_html;
			}
		} catch (Exception $e) {
			error_log('HRW Emergency: Exception in card generation for ' . $hrw_restaurant->post_title . ': ' . $e->getMessage());
			// Emergency fallback: Use original method
			$card_data = get_hrw_card_data($hrw_restaurant->ID, null);
			if ($card_data) {
				$custom_html = generate_hrw_card_html($card_data);
				$place['meta']['custom_card_html'] = $custom_html;
			}
		}

		// Get custom taxonomies for top-level inclusion
		$custom_taxonomies = vibemap_hrw_get_place_custom_taxonomies($hrw_restaurant->ID);

		// Build final transformed structure like legacy (with custom taxonomies at top level)
		$transformed_place = [
			'id' => $place['id'],
			'title' => $place['title'],
			'slug' => $place['slug'],
			'content' => $place['content'],
			'permalink' => $place['permalink'],
			'featured_image' => $place['featured_image'],
			'categories' => $place['categories'],
			'vibes' => $place['vibes'],
			'tags' => $place['tags'],
			'meta' => $place['meta']
		];

		// Add custom taxonomies at top level with original slugs as keys (CRITICAL FOR FILTERING)
		foreach ($custom_taxonomies as $tax_slug => $terms) {
			$transformed_place[$tax_slug] = $terms;
		}

		return $transformed_place;
	}

	/**
	 * Safe featured image retrieval with comprehensive fallback system
	 * 
	 * @param WP_Post $hrw_restaurant Restaurant post object
	 * @param array $restaurant_meta Pre-loaded meta data
	 * @return string Featured image URL or empty string
	 */
	private static function get_featured_image_safe($hrw_restaurant, $restaurant_meta)
	{
		// PRIORITY 1: Try HRW ACF photos_of_hrw_menu_items field (primary HRW image source)
		$gallery_images = get_field('photos_of_hrw_menu_items', $hrw_restaurant->ID);
		if (!empty($gallery_images) && is_array($gallery_images)) {
			$first_image = $gallery_images[0];
			if (is_array($first_image) && isset($first_image['url'])) {
				return $first_image['url'];
			} else if (is_string($first_image)) {
				return $first_image;
			}
		}

		// PRIORITY 2: Try HRW ACF restaurant_photo field
		$restaurant_photo = get_field('restaurant_photo', $hrw_restaurant->ID);
		if (!empty($restaurant_photo)) {
			if (is_array($restaurant_photo) && isset($restaurant_photo['url'])) {
				return $restaurant_photo['url'];
			} elseif (is_numeric($restaurant_photo)) {
				return wp_get_attachment_url($restaurant_photo);
			} else {
				return $restaurant_photo;
			}
		}

		// PRIORITY 3: WordPress featured image
		$featured_image_url = get_the_post_thumbnail_url($hrw_restaurant->ID, 'full');
		if (!$featured_image_url) {
			$featured_image_url = get_the_post_thumbnail_url($hrw_restaurant->ID, 'large') ?:
				get_the_post_thumbnail_url($hrw_restaurant->ID, 'medium') ?: '';
		}

		return $featured_image_url;
	}

	/**
	 * LEGACY: Transform HRW restaurant directly to place structure (keeping for compatibility)
	 * 
	 * @param WP_Post $hrw_restaurant The HRW restaurant post
	 * @param array &$used_custom_taxonomies Reference to used custom taxonomies
	 * @return array|null Transformed place data or null if invalid
	 */
	private static function transform_hrw_restaurant_to_place($hrw_restaurant, &$used_custom_taxonomies)
	{
		// Get the vibemap_id if available (optional now)
		$vibemap_id = self::get_vibemap_id($hrw_restaurant);
		// If no vibemap_id, use the WordPress post ID as the identifier
		if (empty($vibemap_id)) {
			$vibemap_id = 'hrw_' . $hrw_restaurant->ID;
			error_log('HRW Merger: No vibemap_id for "' . $hrw_restaurant->post_title . '", using hrw_' . $hrw_restaurant->ID);
		}

		// Get coordinates
		$latitude = null;
		$longitude = null;

		// Try to get coordinates from ACF full_address field first
		if (function_exists('get_field')) {
			$hrw_address = get_field('full_address', $hrw_restaurant->ID);
			if (!empty($hrw_address) && is_array($hrw_address)) {
				if (isset($hrw_address['lat']) && isset($hrw_address['lng'])) {
					$latitude = floatval($hrw_address['lat']);
					$longitude = floatval($hrw_address['lng']);
				}
			}
		}

		// Fallback to direct lat/lng fields
		if ($latitude === null || $longitude === null) {
			$hrw_lat = get_post_meta($hrw_restaurant->ID, 'latitude', true);
			$hrw_lng = get_post_meta($hrw_restaurant->ID, 'longitude', true);

			if (!empty($hrw_lat) && !empty($hrw_lng)) {
				// Extract from array if needed
				if (is_array($hrw_lat)) {
					$hrw_lat = reset($hrw_lat);
				}
				if (is_array($hrw_lng)) {
					$hrw_lng = reset($hrw_lng);
				}

				$latitude = floatval($hrw_lat);
				$longitude = floatval($hrw_lng);
			}
		}

		// If no valid coordinates, skip this restaurant
		if ($latitude === null || $longitude === null || $latitude === 0 || $longitude === 0) {
			error_log('HRW Merger: Skipping "' . $hrw_restaurant->post_title . '" - no valid coordinates');
			return null;
		}

		// First try to get image from ACF gallery field 'photos_of_hrw_menu_items'
		$featured_image_url = '';

		if (function_exists('get_field')) {
			$gallery_images = get_field('photos_of_hrw_menu_items', $hrw_restaurant->ID);

			// Get first image directly - handle both JSON string and array
			$first_image = null;
			if (is_string($gallery_images)) {
				error_log('HRW Merger: Gallery images is a string: ' . $gallery_images);
				$decoded = json_decode($gallery_images, true);
				$first_image = !empty($decoded[0]) ? $decoded[0] : null;
			} elseif (is_array($gallery_images) && !empty($gallery_images[0])) {
				// Check if first element is a JSON string
				if (is_string($gallery_images[0]) && json_decode($gallery_images[0], true) !== null) {
					$decoded = json_decode($gallery_images[0], true);
					$first_image = !empty($decoded[0]) ? $decoded[0] : null;
				} else {
					$first_image = $gallery_images[0];
				}
			}

			if ($first_image) {
				error_log('HRW Merger: Processing first_image: ' . print_r($first_image, true));
				error_log('HRW Merger: first_image type: ' . gettype($first_image));

				if (is_array($first_image) && isset($first_image['url'])) {
					// ACF returns image array with url, sizes, etc.
					$featured_image_url = $first_image['url'];
					error_log('HRW Merger: Using first image from photos_of_hrw_menu_items gallery for "' . $hrw_restaurant->post_title . '": ' . $featured_image_url);
				} elseif (is_numeric($first_image)) {
					// ACF might return attachment ID
					$featured_image_url = wp_get_attachment_url($first_image);
					if ($featured_image_url) {
						error_log('HRW Merger: Using attachment ID from photos_of_hrw_menu_items gallery for "' . $hrw_restaurant->post_title . '": ' . $featured_image_url);
					}
				} else {
					// Direct URL string - this should be our case
					$featured_image_url = $first_image;
					error_log('HRW Merger: Using direct URL from gallery for "' . $hrw_restaurant->post_title . '": ' . $featured_image_url);
				}
			} else {
				error_log('HRW Merger: No first_image found');
			}
		}

		// If no gallery image, try WordPress featured image
		if (empty($featured_image_url)) {
			$featured_image_url = get_the_post_thumbnail_url($hrw_restaurant->ID, 'full');
			if (!$featured_image_url) {
				// Try medium or thumbnail if full size doesn't exist
				$featured_image_url = get_the_post_thumbnail_url($hrw_restaurant->ID, 'large') ?:
					get_the_post_thumbnail_url($hrw_restaurant->ID, 'medium') ?: '';
			}

			if ($featured_image_url) {
				error_log('HRW Merger: Using WordPress featured image for "' . $hrw_restaurant->post_title . '": ' . $featured_image_url);
			}
		}

		// If still no image, use the fallback from settings
		if (empty($featured_image_url)) {
			$fallback_image = get_option('vibemap_hrw_fallback_image', '');
			if (!empty($fallback_image)) {
				$featured_image_url = $fallback_image;
				error_log('HRW Merger: Using fallback image for "' . $hrw_restaurant->post_title . '": ' . $featured_image_url);
			}
		}

		// Build the place structure matching transformData.js format
		$place = [
			'id' => $hrw_restaurant->ID, // Use HRW post ID as the main ID
			'title' => $hrw_restaurant->post_title,
			'slug' => $hrw_restaurant->post_name,
			'content' => $hrw_restaurant->post_content,
			'permalink' => get_permalink($hrw_restaurant->ID),
			'featured_image' => $featured_image_url,
			'meta' => [
				'vibemap_place_id' => $vibemap_id,
				'vibemap_place_latitude' => strval($latitude),
				'vibemap_place_longitude' => strval($longitude),
				'vibemap_place_address' => '',
				'vibemap_place_full_address' => '',
				'vibemap_place_city' => 'Houston', // Default for HRW
				'vibemap_place_state' => 'TX', // Default for HRW
				'vibemap_place_neighborhood' => '',
				'vibemap_place_images' => '[]', // Will be populated with restaurant_photo if available
				'hrw_post_id' => $hrw_restaurant->ID,
				'is_hrw_restaurant' => true
			],
			'categories' => [], // Will be populated if needed
			'vibes' => [], // Will be populated if needed
			'tags' => [] // Will be populated if needed
		];

		// Update fields from ACF
		if (function_exists('get_field')) {
			// Address
			$hrw_address = get_field('full_address', $hrw_restaurant->ID);
			if (!empty($hrw_address) && is_array($hrw_address) && isset($hrw_address['address'])) {
				$place['meta']['vibemap_place_address'] = $hrw_address['address'];
				$place['meta']['vibemap_place_full_address'] = $hrw_address['address'];
			}

			// Neighborhood
			$neighborhood = get_field('neighborhood', $hrw_restaurant->ID);
			if (!empty($neighborhood)) {
				if (is_array($neighborhood)) {
					$place['meta']['vibemap_place_neighborhood'] = implode(', ', $neighborhood);
				} else {
					$place['meta']['vibemap_place_neighborhood'] = strval($neighborhood);
				}
			}

			// Vibes - get from HRW's vibes_from_vibemap field
			$vibes_data = get_field('vibes_from_vibemap', $hrw_restaurant->ID);
			if (!empty($vibes_data)) {
				// Convert vibes to the expected format if needed
				if (is_string($vibes_data)) {
					// Handle pipe-separated string
					$vibes_array = array_map('trim', explode('|', $vibes_data));
					$place['vibes'] = [];
					foreach ($vibes_array as $index => $vibe_name) {
						if (!empty($vibe_name)) {
							$place['vibes'][] = [
								'id' => $index + 1, // Generate sequential IDs
								'name' => $vibe_name,
								'slug' => sanitize_title($vibe_name)
							];
						}
					}
				} elseif (is_array($vibes_data)) {
					$place['vibes'] = $vibes_data;
				}
			}

			// Restaurant photo from ACF
			$restaurant_photo = get_field('restaurant_photo', $hrw_restaurant->ID);
			if (!empty($restaurant_photo)) {
				error_log('HRW Merger: Found restaurant_photo field: ' . print_r($restaurant_photo, true));

				$photo_url = '';
				if (is_array($restaurant_photo)) {
					// ACF image field returns array with url, alt, sizes, etc.
					$photo_url = $restaurant_photo['url'] ?? ($restaurant_photo['sizes']['large'] ?? '');
				} elseif (is_numeric($restaurant_photo)) {
					// ACF might return attachment ID
					$photo_url = wp_get_attachment_url($restaurant_photo);
				} else {
					// Direct URL string
					$photo_url = $restaurant_photo;
				}

				if (!empty($photo_url)) {
					if (empty($place['featured_image'])) {
						// Use as featured image if no featured image exists
						$place['featured_image'] = $photo_url;
						error_log('HRW Merger: Using restaurant_photo as featured image: ' . $photo_url);
					} else {
						// Add to additional images array if we already have a featured image
						$place['meta']['vibemap_place_images'] = json_encode([$photo_url]);
						error_log('HRW Merger: Adding restaurant_photo to additional images: ' . $photo_url);
					}
				}
			}
		}

		// Add HRW custom taxonomies
		self::add_custom_taxonomies($place, $hrw_restaurant, $used_custom_taxonomies);

		// Generate and add custom card HTML
		$card_data = get_hrw_card_data($hrw_restaurant->ID);
		if ($card_data) {
			$custom_html = generate_hrw_card_html($card_data);
			$place['meta']['custom_card_html'] = $custom_html;
		}

		// Get custom taxonomies
		$custom_taxonomies = vibemap_hrw_get_place_custom_taxonomies($hrw_restaurant->ID);

		// Debug logging for taxonomies
		if (!empty($custom_taxonomies)) {
			error_log('HRW Merger: Restaurant "' . $hrw_restaurant->post_title . '" has custom taxonomies: ' . implode(', ', array_keys($custom_taxonomies)));
		}

		// Return in the WordPress API format that will be transformed by frontend
		// The frontend's transformData.js expects this structure
		$transformed_place = [
			'id' => $place['id'], // This will be ignored by transformData which uses meta.vibemap_place_id
			'title' => $place['title'],
			'slug' => $place['slug'],
			'content' => $place['content'],
			'permalink' => $place['permalink'],
			'featured_image' => $place['featured_image'],
			'categories' => $place['categories'],
			'vibes' => $place['vibes'],
			'tags' => $place['tags'],
			'meta' => $place['meta'] // Contains all the vibemap_place_* fields including vibemap_place_id
		];

		// Add custom taxonomies at top level with original slugs as keys
		foreach ($custom_taxonomies as $tax_slug => $terms) {
			// Keep the original taxonomy slug as the key
			$transformed_place[$tax_slug] = $terms;
		}

		// Debug logging for images
		error_log('HRW Merger: Transformed "' . $place['title'] . '" (vibemap_id: ' . $vibemap_id . ', featured_image: ' . ($transformed_place['featured_image'] ?: 'none') . ')');

		return $transformed_place;
	}
}
