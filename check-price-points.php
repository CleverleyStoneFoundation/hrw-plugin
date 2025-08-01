<?php

/**
 * Check which restaurants have _menu_price_points data
 */

// Load WordPress
require_once('../../../wp-config.php');

echo "<h2>Menu Price Points Data Check</h2>";

// Get all HRW restaurants
$restaurants = get_posts([
	'post_type' => 'hrw_restaurants',
	'posts_per_page' => 20, // Check first 20
	'post_status' => 'publish',
	'meta_query' => [
		[
			'key' => '_menu_status',
			'value' => '4',
			'compare' => '='
		],
		[
			'key' => '_menu_year',
			'value' => '2025',
			'compare' => '='
		]
	]
]);

echo "<p>Checking " . count($restaurants) . " restaurants for _menu_price_points data...</p>";

$with_data = 0;
$without_data = 0;

echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>Restaurant</th><th>Price Points</th><th>Status</th></tr>";

foreach ($restaurants as $restaurant) {
	$price_points = get_field('_menu_price_points', $restaurant->ID);

	echo "<tr>";
	echo "<td>" . $restaurant->post_title . "</td>";

	if (!empty($price_points) && is_array($price_points)) {
		echo "<td>" . implode(', ', $price_points) . "</td>";
		echo "<td style='color: green;'>✅ HAS DATA</td>";
		$with_data++;
	} else {
		echo "<td>NULL/Empty</td>";
		echo "<td style='color: red;'>❌ NO DATA</td>";
		$without_data++;
	}
	echo "</tr>";
}

echo "</table>";

echo "<h3>Summary:</h3>";
echo "<p><strong>With Price Point Data:</strong> $with_data restaurants</p>";
echo "<p><strong>Without Price Point Data:</strong> $without_data restaurants</p>";

if ($with_data == 0) {
	echo "<h3>❌ Problem Found:</h3>";
	echo "<p><strong>No restaurants have _menu_price_points data!</strong></p>";
	echo "<p>The field won't appear in the UI because there are no terms to show.</p>";

	echo "<h3>🛠️ Solutions:</h3>";
	echo "<ol>";
	echo "<li><strong>Check ACF field name:</strong> Is it actually '_menu_price_points'?</li>";
	echo "<li><strong>Populate the field:</strong> Add price point data to some restaurants</li>";
	echo "<li><strong>Check field configuration:</strong> Make sure it's set up as a multi-select</li>";
	echo "</ol>";
} else {
	echo "<h3>✅ Data Found!</h3>";
	echo "<p>Some restaurants have price point data. The field should appear once cache is cleared.</p>";
}

// Check if the ACF field exists
echo "<h3>ACF Field Check:</h3>";
if (function_exists('acf_get_field')) {
	$field = acf_get_field('_menu_price_points');
	if ($field) {
		echo "<p>✅ ACF field '_menu_price_points' exists</p>";
		echo "<p><strong>Field Type:</strong> " . $field['type'] . "</p>";
		if (isset($field['choices'])) {
			echo "<p><strong>Choices:</strong> " . implode(', ', array_keys($field['choices'])) . "</p>";
		}
	} else {
		echo "<p>❌ ACF field '_menu_price_points' not found!</p>";
		echo "<p>Check the exact field name in ACF admin.</p>";
	}
} else {
	echo "<p>⚠️ ACF functions not available</p>";
}
