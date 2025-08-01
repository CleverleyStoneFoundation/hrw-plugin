<?php

/**
 * HRW Cache Invalidation System
 * 
 * Automatically clears API cache when restaurant data changes
 * Ensures new/updated restaurants appear immediately in API responses
 * 
 * @package HRW_Plugin
 * @since 1.2.1
 */

// Prevent direct access
if (!defined('ABSPATH')) {
	exit;
}

class HRW_Cache_Invalidation
{
	/**
	 * Initialize cache invalidation hooks
	 */
	public static function init()
	{
		// Hook into restaurant post saves
		add_action('save_post', [__CLASS__, 'on_restaurant_saved'], 10, 2);

		// Hook into ACF field saves (restaurant data)
		add_action('acf/save_post', [__CLASS__, 'on_acf_saved'], 25);

		// Hook into post status changes
		add_action('transition_post_status', [__CLASS__, 'on_status_changed'], 10, 3);

		// Hook into restaurant deletion
		add_action('before_delete_post', [__CLASS__, 'on_restaurant_deleted']);

		// Manual cache clear admin action
		add_action('wp_ajax_hrw_clear_cache', [__CLASS__, 'manual_cache_clear']);

		error_log('HRW Cache Invalidation: Initialized cache invalidation hooks');
	}

	/**
	 * Clear cache when restaurant post is saved
	 * 
	 * @param int $post_id Post ID
	 * @param WP_Post $post Post object
	 */
	public static function on_restaurant_saved($post_id, $post)
	{
		// Only process HRW restaurant posts
		if (!self::is_hrw_restaurant($post)) {
			return;
		}

		// Avoid infinite loops during autosave/revisions
		if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
			return;
		}

		self::clear_api_cache('restaurant_saved', [
			'post_id' => $post_id,
			'post_title' => $post->post_title,
			'post_status' => $post->post_status
		]);
	}

	/**
	 * Clear cache when ACF fields are saved
	 * 
	 * @param int $post_id Post ID
	 */
	public static function on_acf_saved($post_id)
	{
		// Check if this is a restaurant post
		$post = get_post($post_id);
		if (!self::is_hrw_restaurant($post)) {
			return;
		}

		// Check if important fields changed
		$important_fields = [
			'_menu_status',
			'_menu_year',
			'neighborhood',
			'cuisine_types',
			'latitude',
			'longitude',
			'vibemap_id'
		];

		// Clear cache for any ACF save on restaurant posts
		// (More efficient than checking each field individually)
		self::clear_api_cache('acf_saved', [
			'post_id' => $post_id,
			'trigger' => 'acf_field_update'
		]);
	}

	/**
	 * Clear cache when post status changes
	 * 
	 * @param string $new_status New post status
	 * @param string $old_status Old post status  
	 * @param WP_Post $post Post object
	 */
	public static function on_status_changed($new_status, $old_status, $post)
	{
		if (!self::is_hrw_restaurant($post)) {
			return;
		}

		// Clear cache for any status change (publish, draft, trash, etc.)
		self::clear_api_cache('status_changed', [
			'post_id' => $post->ID,
			'old_status' => $old_status,
			'new_status' => $new_status,
			'post_title' => $post->post_title
		]);
	}

	/**
	 * Clear cache when restaurant is deleted
	 * 
	 * @param int $post_id Post ID being deleted
	 */
	public static function on_restaurant_deleted($post_id)
	{
		$post = get_post($post_id);
		if (!self::is_hrw_restaurant($post)) {
			return;
		}

		self::clear_api_cache('restaurant_deleted', [
			'post_id' => $post_id,
			'post_title' => $post->post_title
		]);
	}

	/**
	 * Manual cache clear via AJAX
	 */
	public static function manual_cache_clear()
	{
		// Check nonce and permissions
		if (!wp_verify_nonce($_POST['nonce'], 'hrw_clear_cache') || !current_user_can('manage_options')) {
			wp_die('Unauthorized');
		}

		self::clear_api_cache('manual_clear', [
			'user_id' => get_current_user_id(),
			'timestamp' => current_time('mysql')
		]);

		wp_send_json_success([
			'message' => 'HRW API cache cleared successfully',
			'timestamp' => current_time('mysql')
		]);
	}

	/**
	 * Clear API cache with logging
	 * 
	 * @param string $trigger What triggered the cache clear
	 * @param array $context Additional context information
	 */
	private static function clear_api_cache($trigger, $context = [])
	{
		if (!class_exists('HRW_API_Cache')) {
			error_log('HRW Cache Invalidation: HRW_API_Cache class not found');
			return false;
		}

		// Clear the cache
		$success = HRW_API_Cache::clear_cache();

		// Log the cache clear with context
		$log_message = sprintf(
			'HRW Cache Invalidation: Cache cleared - Trigger: %s, Context: %s',
			$trigger,
			json_encode($context)
		);
		error_log($log_message);

		// Update cache clear statistics
		$stats = get_option('hrw_cache_clear_stats', []);
		$stats[] = [
			'trigger' => $trigger,
			'context' => $context,
			'timestamp' => current_time('mysql'),
			'success' => $success
		];

		// Keep only last 50 cache clears
		if (count($stats) > 50) {
			$stats = array_slice($stats, -50);
		}

		update_option('hrw_cache_clear_stats', $stats);

		return $success;
	}

	/**
	 * Check if post is an HRW restaurant
	 * 
	 * @param WP_Post|null $post Post object
	 * @return bool True if HRW restaurant
	 */
	private static function is_hrw_restaurant($post)
	{
		if (!$post || !is_object($post)) {
			return false;
		}

		return $post->post_type === 'hrw_restaurants';
	}

	/**
	 * Get cache invalidation statistics
	 * 
	 * @return array Cache clear statistics
	 */
	public static function get_cache_stats()
	{
		$stats = get_option('hrw_cache_clear_stats', []);

		return [
			'total_clears' => count($stats),
			'recent_clears' => array_slice($stats, -10), // Last 10
			'triggers' => array_count_values(array_column($stats, 'trigger'))
		];
	}

	/**
	 * Add admin notice about cache status
	 */
	public static function add_admin_notice()
	{
		add_action('admin_notices', function () {
			echo '<div class="notice notice-info"><p>';
			echo '<strong>HRW Cache:</strong> Automatic cache invalidation is active. ';
			echo 'Restaurant changes will appear immediately in API responses.';
			echo '</p></div>';
		});
	}
}
