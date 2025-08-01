<?php

/**
 * Manual cache clearing script
 * 
 * Visit this file in browser to clear HRW API cache
 */

// Load WordPress
require_once('../../../wp-config.php');

echo "<h2>HRW Cache Manual Clear</h2>";

// Clear the cache
if (class_exists('HRW_API_Cache')) {
	$result = HRW_API_Cache::clear_cache();
	if ($result) {
		echo "<p style='color: green;'><strong>✅ SUCCESS:</strong> HRW API cache cleared successfully!</p>";
		echo "<p>All cached API responses have been invalidated.</p>";
	} else {
		echo "<p style='color: red;'><strong>❌ ERROR:</strong> Failed to clear cache.</p>";
	}
} else {
	echo "<p style='color: red;'><strong>❌ ERROR:</strong> HRW_API_Cache class not found!</p>";
}

// Show cache stats
if (class_exists('HRW_API_Cache')) {
	$stats = HRW_API_Cache::get_cache_stats();
	echo "<h3>Cache Configuration:</h3>";
	echo "<pre>" . print_r($stats, true) . "</pre>";
}

echo "<p><strong>Next steps:</strong></p>";
echo "<ol>";
echo "<li>Go back to your map page</li>";
echo "<li>Refresh the page</li>";
echo "<li>Check if 'Vibes' filter appears</li>";
echo "</ol>";

echo "<p><a href='../../../wp-content/plugins/hrw-plugin/debug-custom-taxonomies.php'>→ Run Debug Script</a></p>";
