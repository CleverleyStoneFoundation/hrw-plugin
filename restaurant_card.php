<?php
// Legacy variables for backward compatibility
// Note: These are only populated when used in WordPress context AND when wp_query is available
if (function_exists('get_field') && function_exists('get_queried_object') && !is_null($GLOBALS['wp_query'])) {
	$restaurant_title = get_field('restaurant_title'); // acf text field
	$restaurant_menu_types = get_field('_hrw_menus'); // acf text field with array of menu types ie {"0":"dinner","1":"lunch","2":"brunch","3":"dinner-togo","4":"togo","5":"lunch-togo","7":"brunch-togo"}
	$reservations = get_field('reservations'); // acf radio button, returns value of radio button
	$reservation_links = get_field('reservation_links'); // acf url field
	$reservation_phone = get_field('reservation_phone_number');
	$reservation_description = get_field('reservation_notes');
	$restaurant_cuisine = get_field('cuisine_types');
	$restaurant_neighborhood = get_field('neighborhood');
	$restaurant_vibes = get_field('vibes_from_vibemap');
	$restaurant_photo = get_field('restaurant_photo');
}

/**
 * Get HRW Restaurant Card Data for VibeMap Integration
 * 
 * This function provides clean, structured data for VibeMap team integration.
 * Call this function with a restaurant post ID to get formatted card data.
 * 
 * @param int $post_id Restaurant post ID
 * @return array Structured card data ready for VibeMap integration
 */
function get_hrw_card_data($post_id)
{
	// Ensure we have a valid post ID
	if (!$post_id) {
		return null;
	}

	// Get all restaurant data
	$data = [
		'title' => function_exists('get_field') ? get_field('restaurant_title', $post_id) : '',
		'menu_types' => function_exists('get_field') ? get_field('_hrw_menus', $post_id) : [],
		'reservations' => function_exists('get_field') ? get_field('reservations', $post_id) : '',
		'reservation_links' => function_exists('get_field') ? get_field('reservation_links', $post_id) : '',
		'reservation_phone' => function_exists('get_field') ? get_field('reservation_phone_number', $post_id) : '',
		'reservation_notes' => function_exists('get_field') ? get_field('reservation_notes', $post_id) : '',
		'cuisine' => function_exists('get_field') ? get_field('cuisine_types', $post_id) : [],
		'neighborhood' => function_exists('get_field') ? get_field('neighborhood', $post_id) : [],
		'vibes' => function_exists('get_field') ? get_field('vibes_from_vibemap', $post_id) : [],
		'photo' => function_exists('get_field') ? get_field('restaurant_photo', $post_id) : ''
	];

	// Build structured card data
	return [
		'chips' => _hrw_build_chips($data),
		'reservations' => _hrw_build_reservations($data),
		'meta' => _hrw_build_meta($data),
		'raw_data' => $data // Include raw data for flexibility
	];
}

/**
 * Build chips/bubbles data for card display
 */
function _hrw_build_chips($data)
{
	$chips = [];

	// Menu Types - Green chips with meal icons
	if (!empty($data['menu_types'])) {
		$menu_types = [];

		// Handle JSON string, array, or direct values
		if (is_string($data['menu_types'])) {
			$decoded = json_decode($data['menu_types'], true);
			if ($decoded && is_array($decoded)) {
				$menu_types = array_values($decoded); // Use array_values to get just the values
			}
		} elseif (is_array($data['menu_types'])) {
			$menu_types = $data['menu_types'];
		}

		if ($menu_types) {
			foreach ($menu_types as $menu_type) {
				if (!empty($menu_type)) {
					$chips['menu_types'][] = [
						'label' => ucfirst(str_replace('-', ' ', $menu_type)),
						'value' => $menu_type,
						'icon' => _hrw_get_menu_icon($menu_type),
						'color' => '#4CAF50', // Green
						'text_color' => '#ffffff'
					];
				}
			}
		}
	}

	// Cuisine Types - Orange chips
	if (!empty($data['cuisine'])) {
		$cuisine_count = count($data['cuisine']);
		$cuisines_to_show = array_slice($data['cuisine'], 0, 3);

		$cuisine_data = is_array($cuisines_to_show) ? $cuisines_to_show : [$cuisines_to_show];
		foreach ($cuisine_data as $cuisine) {
			if (!empty($cuisine) && is_string($cuisine)) {
				$chips['cuisine'][] = [
					'label' => $cuisine,
					'value' => function_exists('sanitize_title') ? sanitize_title($cuisine) : strtolower(str_replace(' ', '-', $cuisine)),
					'icon' => 'fa-utensils',
					'color' => '#FF9800', // Orange
					'text_color' => '#ffffff'
				];
			}
		}

		// Add ellipsis if there are more than 3 cuisines
		if ($cuisine_count > 3) {
			$chips['cuisine'][] = [
				'label' => '...',
				'value' => 'more',
				'icon' => 'fa-ellipsis-h',
				'color' => '#666666', // Gray
				'text_color' => '#ffffff',
				'is_ellipsis' => true
			];
		}
	}

	// Neighborhood - Blue chips  
	if (!empty($data['neighborhood']) && is_array($data['neighborhood'])) {
		$neighborhood_count = count($data['neighborhood']);
		$neighborhoods_to_show = array_slice($data['neighborhood'], 0, 3);

		foreach ($neighborhoods_to_show as $area) {
			$chips['neighborhood'][] = [
				'label' => $area,
				'value' => function_exists('sanitize_title') ? sanitize_title($area) : strtolower(str_replace(' ', '-', $area)),
				'icon' => 'fa-map-marker-alt',
				'color' => '#2196F3', // Blue
				'text_color' => '#ffffff'
			];
		}

		// Add ellipsis if there are more than 3 neighborhoods
		if ($neighborhood_count > 3) {
			$chips['neighborhood'][] = [
				'label' => '...',
				'value' => 'more',
				'icon' => 'fa-ellipsis-h',
				'color' => '#666666', // Gray
				'text_color' => '#ffffff',
				'is_ellipsis' => true
			];
		}
	}

	// Vibes - Purple chips
	if (!empty($data['vibes'])) {
		$vibes_data = [];

		// Handle pipe-separated string or array
		if (is_string($data['vibes'])) {
			$vibes_data = explode('|', $data['vibes']);
		} elseif (is_array($data['vibes'])) {
			$vibes_data = $data['vibes'];
		}

		// Limit to first 3 vibes to avoid overwhelming the card
		$vibes = array_slice($vibes_data, 0, 3);
		foreach ($vibes as $vibe) {
			$vibe = trim($vibe);
			if (!empty($vibe)) {
				$chips['vibes'][] = [
					'label' => ucfirst($vibe),
					'value' => function_exists('sanitize_title') ? sanitize_title($vibe) : strtolower(str_replace(' ', '-', $vibe)),
					'icon' => 'fa-bolt',
					'color' => '#9C27B0', // Purple
					'text_color' => '#ffffff'
				];
			}
		}
	}

	return $chips;
}

/**
 * Build reservation data for card display
 */
function _hrw_build_reservations($data)
{
	$reservations = $data['reservations'];
	$is_walk_ins = (strtolower($reservations) === 'walk-ins welcome');

	$reservation_data = [
		'status' => $is_walk_ins ? 'walk-ins' : 'reservations-needed',
		'status_text' => $is_walk_ins ? 'Walk-ins Welcome!' : 'Reservations Needed',
		'icon' => $is_walk_ins ? 'fa-walking' : 'fa-calendar-check',
		'color' => $is_walk_ins ? '#4CAF50' : '#FF5722', // Green for walk-ins, red for reservations
		'options' => []
	];

	// Add phone reservation option
	if (!empty($data['reservation_phone'])) {
		$reservation_data['options'][] = [
			'type' => 'phone',
			'label' => 'Reserve by Phone',
			'value' => $data['reservation_phone'],
			'link' => 'tel:' . preg_replace('/[^0-9+]/', '', $data['reservation_phone']),
			'icon' => 'fa-phone',
			'primary' => true
		];
	}

	// Add online reservation option
	if (!empty($data['reservation_links'])) {
		$reservation_data['options'][] = [
			'type' => 'online',
			'label' => 'Reserve Online',
			'value' => $data['reservation_links'],
			'link' => $data['reservation_links'],
			'icon' => 'fa-external-link',
			'primary' => false
		];
	}

	// Add notes if available
	if (!empty($data['reservation_notes'])) {
		$reservation_data['notes'] = $data['reservation_notes'];
	}

	return $reservation_data;
}

/**
 * Build meta data for card display
 */
function _hrw_build_meta($data)
{
	return [
		'subtitle' => _hrw_build_subtitle($data),
		'photo' => $data['photo'],
		'has_photo' => !empty($data['photo']),
		'enhanced' => true, // Flag to indicate this is HRW enhanced data
		'version' => '1.0' // For future compatibility
	];
}

/**
 * Build dynamic subtitle for card
 */
function _hrw_build_subtitle($data)
{
	$parts = [];

	// Add primary cuisine
	if (!empty($data['cuisine']) && is_array($data['cuisine'])) {
		$parts[] = $data['cuisine'][0];
	}

	// Add menu types (limit to 2)
	if (!empty($data['menu_types'])) {
		$menu_types = is_array($data['menu_types']) ? $data['menu_types'] : json_decode($data['menu_types'], true);
		if ($menu_types) {
			$display_menus = array_slice($menu_types, 0, 2);
			$menu_labels = array_map('ucfirst', $display_menus);
			$parts[] = implode(', ', $menu_labels);
		}
	}

	return implode(' • ', array_filter($parts));
}

/**
 * Get Font Awesome icon for menu types
 */
function _hrw_get_menu_icon($menu_type)
{
	$icons = [
		'dinner' => 'fa-moon',
		'lunch' => 'fa-sun',
		'brunch' => 'fa-coffee',
		'breakfast' => 'fa-coffee',
		'togo' => 'fa-shopping-bag',
		'dinner-togo' => 'fa-shopping-bag',
		'lunch-togo' => 'fa-shopping-bag',
		'brunch-togo' => 'fa-shopping-bag',
		'takeout' => 'fa-shopping-bag',
		'delivery' => 'fa-truck'
	];

	return isset($icons[$menu_type]) ? $icons[$menu_type] : 'fa-utensils';
}

/**
 * Generate HTML from HRW card data
 * 
 * This function takes the structured card data and converts it into
 * ready-to-render HTML that can be injected into VibeMap cards.
 * 
 * @param array $card_data The structured card data from get_hrw_card_data()
 * @return string HTML string ready for rendering
 */
function generate_hrw_card_html($card_data)
{
	if (!$card_data) {
		return '';
	}

	$html = '<div class="hrw-card-content">';

	// Add reservation status if available
	if (!empty($card_data['reservations'])) {
		$res = $card_data['reservations'];
		$status_class = $res['status'] === 'walk-ins' ? 'walk-ins-welcome' : 'reservations-needed';

		$html .= '<div class="hrw-reservation-section">';
		$html .= '<div class="hrw-reservation-status ' . esc_attr($status_class) . '">';
		$html .= '<i class="fas ' . esc_attr($res['icon']) . '"></i> ';
		$html .= esc_html($res['status_text']);
		$html .= '</div>';

		// Add reservation options (phone/online links)
		if (!empty($res['options'])) {
			$html .= '<div class="hrw-reservation-options">';
			foreach ($res['options'] as $option) {
				$link_class = $option['primary'] ? 'hrw-reservation-link primary' : 'hrw-reservation-link';
				$html .= '<a href="' . esc_url($option['link']) . '" class="' . esc_attr($link_class) . '" ';
				if ($option['type'] === 'online') {
					$html .= 'target="_blank" rel="noopener noreferrer"';
				}
				$html .= '>';
				$html .= '<i class="fas ' . esc_attr($option['icon']) . '"></i> ';
				$html .= esc_html($option['label']);
				$html .= '</a>';
			}
			$html .= '</div>';
		}

		$html .= '</div>';
	}

	// Add chips section
	if (!empty($card_data['chips'])) {
		$html .= '<div class="hrw-chips-container">';

		// Add menu type chips
		if (!empty($card_data['chips']['menu_types'])) {
			foreach ($card_data['chips']['menu_types'] as $chip) {
				$html .= '<span class="hrw-chip menu-type" style="background-color: ' . esc_attr($chip['color']) . '; color: ' . esc_attr($chip['text_color']) . ';">';
				$html .= '<i class="fas ' . esc_attr($chip['icon']) . '"></i> ';
				$html .= esc_html($chip['label']);
				$html .= '</span>';
			}
		}

		// Add cuisine chips
		if (!empty($card_data['chips']['cuisine'])) {
			foreach ($card_data['chips']['cuisine'] as $chip) {
				$html .= '<span class="hrw-chip cuisine" style="background-color: ' . esc_attr($chip['color']) . '; color: ' . esc_attr($chip['text_color']) . ';">';
				$html .= '<i class="fas ' . esc_attr($chip['icon']) . '"></i> ';
				$html .= esc_html($chip['label']);
				$html .= '</span>';
			}
		}

		// Add neighborhood chips
		if (!empty($card_data['chips']['neighborhood'])) {
			foreach ($card_data['chips']['neighborhood'] as $chip) {
				$html .= '<span class="hrw-chip neighborhood" style="background-color: ' . esc_attr($chip['color']) . '; color: ' . esc_attr($chip['text_color']) . ';">';
				$html .= '<i class="fas ' . esc_attr($chip['icon']) . '"></i> ';
				$html .= esc_html($chip['label']);
				$html .= '</span>';
			}
		}

		$html .= '</div>';
	}

	// Add inline styles for the HRW content
	$html .= '<style>
		.hrw-card-content { margin-top: 10px; }
		.hrw-reservation-section { margin-bottom: 12px; }
		.hrw-reservation-status { 
			font-size: 14px; 
			font-weight: 600; 
			margin-bottom: 8px;
		}
		.hrw-reservation-status.walk-ins-welcome { color: #28a745; }
		.hrw-reservation-status.reservations-needed { color: #dc3545; }
		.hrw-reservation-options { 
			display: flex; 
			gap: 8px; 
			flex-wrap: wrap;
		}
		.hrw-reservation-link {
			display: inline-flex;
			align-items: center;
			gap: 4px;
			padding: 4px 10px;
			font-size: 12px;
			text-decoration: none;
			border-radius: 4px;
			background: #6c757d;
			color: white;
			transition: opacity 0.2s;
		}
		.hrw-reservation-link:hover { 
			opacity: 0.9;
			text-decoration: none;
		}
		.hrw-reservation-link.primary { background: #007cba; }
		.hrw-chips-container {
			display: flex;
			flex-wrap: wrap;
			gap: 6px;
		}
		.hrw-chip {
			display: inline-flex;
			align-items: center;
			gap: 4px;
			padding: 4px 8px;
			border-radius: 12px;
			font-size: 11px;
			font-weight: 500;
		}
		.hrw-chip i { font-size: 10px; }
	</style>';

	$html .= '</div>';

	return $html;
}

/* 
 * INTEGRATION EXAMPLE FOR VIBEMAP TEAM:
 * 
 * To use this in your card rendering:
 * 
 * $card_data = get_hrw_card_data($restaurant_id);
 * 
 * if ($card_data) {
 *     // Render chips
 *     foreach ($card_data['chips']['menu_types'] as $chip) {
 *         echo '<span class="chip" style="background-color: ' . $chip['color'] . ';">';
 *         echo '<i class="fas ' . $chip['icon'] . '"></i> ' . $chip['label'];
 *         echo '</span>';
 *     }
 *     
 *     // Render reservation info
 *     $res = $card_data['reservations'];
 *     echo '<div class="reservation-status">';
 *     echo '<i class="fas ' . $res['icon'] . '"></i> ' . $res['status_text'];
 *     if (!empty($res['options'])) {
 *         foreach ($res['options'] as $option) {
 *             echo '<a href="' . $option['link'] . '">' . $option['label'] . '</a>';
 *         }
 *     }
 *     echo '</div>';
 *     
 *     // Use subtitle
 *     echo '<div class="subtitle">' . $card_data['meta']['subtitle'] . '</div>';
 * }
 */
