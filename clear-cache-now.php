<?php

/**
 * Manual Cache Clear Script
 * 
 * Quick script to clear HRW API cache and see alphabetical sorting changes immediately
 */

// Load WordPress
require_once('../../../wp-load.php');

// Check if user has permission
if (!current_user_can('manage_options')) {
	die('❌ Unauthorized - Admin access required');
}

// Load the HRW cache class
require_once('includes/class-hrw-api-cache.php');

echo '<h2>🧹 HRW Cache Clear</h2>';

// Clear the cache
if (class_exists('HRW_API_Cache')) {
	$success = HRW_API_Cache::clear_cache();

	if ($success) {
		echo '<p style="color: green; font-size: 18px;">✅ <strong>Cache cleared successfully!</strong></p>';
		echo '<p>The alphabetical sorting should now be visible in the filter options.</p>';
		echo '<p><strong>Next steps:</strong></p>';
		echo '<ul>';
		echo '<li>Visit the restaurant search page</li>';
		echo '<li>Check that filter options are now in alphabetical order</li>';
		echo '<li>Clear browser cache if needed</li>';
		echo '</ul>';
	} else {
		echo '<p style="color: red;">❌ <strong>Cache clear failed</strong></p>';
	}
} else {
	echo '<p style="color: red;">❌ <strong>HRW_API_Cache class not found</strong></p>';
}

echo '<hr>';
echo '<p><strong>Cache Info:</strong></p>';
echo '<ul>';
echo '<li>Cache Version: ' . get_option('hrw_cache_version', 'default') . '</li>';
echo '<li>Timestamp: ' . current_time('mysql') . '</li>';
echo '<li>Cache Method: WordPress Transients</li>';
echo '</ul>';

echo '<p><a href="' . admin_url() . '">← Back to Admin</a></p>';
