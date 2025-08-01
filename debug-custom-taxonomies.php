<?php

/**
 * Debug script for HRW Custom Taxonomies Issue
 * 
 * Run this to see exactly what's happening with custom taxonomies
 */

// Prevent direct access
if (!defined('ABSPATH')) {
	// For command line testing
	require_once('../../../wp-config.php');
}

echo "<h2>HRW Custom Taxonomies Debug Report</h2>";

// 1. Check the option value
$taxonomy_fields = get_option('vibemap_hrw_custom_taxonomies', '');
echo "<h3>1. Raw Option Value:</h3>";
echo "<pre>vibemap_hrw_custom_taxonomies: '" . $taxonomy_fields . "'</pre>";

// 2. Parse the fields
$fields_array = array_map('trim', explode(',', $taxonomy_fields));
echo "<h3>2. Parsed Fields Array:</h3>";
echo "<pre>" . print_r($fields_array, true) . "</pre>";

// 3. Get a test restaurant
$test_restaurants = get_posts([
	'post_type' => 'hrw_restaurants',
	'posts_per_page' => 1,
	'post_status' => 'publish'
]);

if (empty($test_restaurants)) {
	echo "<h3>3. ERROR: No HRW restaurants found!</h3>";
	exit;
}

$test_restaurant = $test_restaurants[0];
echo "<h3>3. Test Restaurant:</h3>";
echo "<pre>ID: " . $test_restaurant->ID . "\nTitle: " . $test_restaurant->post_title . "</pre>";

// 4. Test each field
echo "<h3>4. Field Testing:</h3>";
foreach ($fields_array as $field_name) {
	if (empty($field_name)) {
		echo "<p><strong>SKIPPING:</strong> Empty field name</p>";
		continue;
	}

	echo "<h4>Field: '" . $field_name . "'</h4>";

	// Get field value
	$field_value = get_field($field_name, $test_restaurant->ID);

	echo "<p><strong>Raw Value:</strong> " . (is_null($field_value) ? 'NULL' : json_encode($field_value)) . "</p>";
	echo "<p><strong>Type:</strong> " . gettype($field_value) . "</p>";
	echo "<p><strong>Is Array:</strong> " . (is_array($field_value) ? 'YES' : 'NO') . "</p>";
	echo "<p><strong>Empty Check:</strong> " . (empty($field_value) ? 'EMPTY' : 'HAS DATA') . "</p>";

	if (is_array($field_value)) {
		echo "<p><strong>Array Count:</strong> " . count($field_value) . "</p>";
		echo "<p><strong>Array Contents:</strong></p>";
		echo "<pre>" . print_r($field_value, true) . "</pre>";
	}

	echo "<hr>";
}

// 5. Test the actual function
echo "<h3>5. Function Test:</h3>";
if (function_exists('vibemap_hrw_get_place_custom_taxonomies')) {
	$result = vibemap_hrw_get_place_custom_taxonomies($test_restaurant->ID);
	echo "<p><strong>Function Result:</strong></p>";
	echo "<pre>" . print_r($result, true) . "</pre>";
} else {
	echo "<p><strong>ERROR:</strong> vibemap_hrw_get_place_custom_taxonomies function not found!</p>";
}

// 6. Check if ACF is active
echo "<h3>6. ACF Status:</h3>";
echo "<p><strong>ACF Plugin Active:</strong> " . (function_exists('get_field') ? 'YES' : 'NO') . "</p>";

if (function_exists('acf_get_field_groups')) {
	$field_groups = acf_get_field_groups();
	echo "<p><strong>ACF Field Groups Found:</strong> " . count($field_groups) . "</p>";
}

echo "<h3>Done!</h3>";
echo "<p>Check the logs for additional debug information from the API call.</p>";
