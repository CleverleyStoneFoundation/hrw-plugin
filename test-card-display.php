<?php

/**
 * HRW Card Data Visual Test Page
 * 
 * Put this file in your theme directory or access via plugin
 * Change the $restaurant_id to test different restaurants
 */

// Load WordPress environment
require_once('../../../wp-config.php');

// Include the restaurant card functions
require_once('restaurant_card.php');

// Set your test restaurant ID here
$restaurant_id = 12755; // CHANGE THIS TO A REAL RESTAURANT ID

// Test Restaurant IDs for different features:
// 11869 - Brennan's Houston (cuisine, vibes, reservations)
// 12755 - Coppa Osteria (menu types: dinner/lunch, cuisine, reservations)
// 12755 - Current test ID

// Get the card data
$card_data = get_hrw_card_data($restaurant_id);
?>

<!DOCTYPE html>
<html>

<head>
	<title>HRW Card Data Test</title>
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
	<style>
		body {
			font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
			padding: 20px;
			background: #f5f5f5;
			margin: 0;
		}

		.container {
			max-width: 800px;
			margin: 0 auto;
		}

		.card {
			background: white;
			border-radius: 12px;
			box-shadow: 0 2px 12px rgba(0, 0, 0, 0.1);
			overflow: hidden;
			margin-bottom: 20px;
			position: relative;
		}

		.card-header {
			padding: 16px 20px;
			border-bottom: 1px solid #eee;
		}

		.restaurant-name {
			font-size: 18px;
			font-weight: 600;
			color: #333;
			margin: 0 0 4px 0;
		}

		.restaurant-subtitle {
			color: #666;
			font-size: 14px;
			margin: 0;
		}

		.card-body {
			padding: 16px 20px;
		}

		.reservation-status {
			display: flex;
			align-items: center;
			gap: 12px;
			margin-bottom: 16px;
		}

		.status-item {
			display: flex;
			align-items: center;
			gap: 6px;
			font-size: 14px;
			color: #28a745;
			font-weight: 500;
		}

		.status-item.reservations-needed {
			color: #dc3545;
		}

		.status-item i {
			font-size: 14px;
		}

		.reservation-link {
			display: inline-flex;
			align-items: center;
			gap: 6px;
			padding: 6px 12px;
			background: #007cba;
			color: white;
			text-decoration: none;
			border-radius: 6px;
			font-size: 12px;
			font-weight: 500;
			transition: background-color 0.2s ease;
		}

		.reservation-link:hover {
			background: #005a8b;
			text-decoration: none;
		}

		.reservation-link.secondary {
			background: #6c757d;
		}

		.reservation-link.secondary:hover {
			background: #545b62;
		}

		.chips-container {
			display: flex;
			flex-wrap: wrap;
			gap: 6px;
			margin-top: 16px;
		}

		.chip {
			display: inline-flex;
			align-items: center;
			gap: 4px;
			padding: 4px 8px;
			border-radius: 12px;
			font-size: 12px;
			font-weight: 500;
			border: 1px solid #e1e5e9;
			background: white;
			color: #6c757d;
		}

		.chip i {
			font-size: 10px;
		}

		.chip.menu-type {
			background: #d4edda;
			color: #155724;
			border-color: #c3e6cb;
		}

		.chip.cuisine {
			background: #fff3cd;
			color: #856404;
			border-color: #ffeaa7;
		}

		.chip.neighborhood {
			background: #d1ecf1;
			color: #0c5460;
			border-color: #bee5eb;
		}

		.chip.vibe {
			background: #e2e3f1;
			color: #495057;
			border-color: #d1d4e0;
		}

		.debug-toggle {
			position: absolute;
			top: 16px;
			right: 16px;
			background: #f8f9fa;
			border: 1px solid #dee2e6;
			border-radius: 4px;
			padding: 4px 8px;
			font-size: 10px;
			cursor: pointer;
			color: #6c757d;
		}

		.debug-section {
			background: #f8f9fa;
			padding: 16px;
			border-top: 1px solid #dee2e6;
			font-size: 12px;
			color: #6c757d;
			display: none;
		}

		.debug-section.show {
			display: block;
		}

		.debug-section pre {
			background: white;
			padding: 8px;
			border-radius: 4px;
			margin: 8px 0 0 0;
			font-size: 11px;
			overflow-x: auto;
		}

		h1 {
			color: #333;
			margin-bottom: 24px;
			font-size: 24px;
			font-weight: 600;
		}
	</style>
</head>

<body>
	<div class="container">
		<h1>HRW Restaurant Card Preview</h1>

		<?php if ($card_data): ?>
			<div class="card">
				<button class="debug-toggle" onclick="toggleDebug()">Debug</button>

				<div class="card-header">
					<div class="restaurant-name">
						<?php echo !empty($card_data['raw_data']['title']) ? $card_data['raw_data']['title'] : 'Restaurant Name'; ?>
					</div>
					<?php if (!empty($card_data['meta']['subtitle'])): ?>
						<div class="restaurant-subtitle"><?php echo $card_data['meta']['subtitle']; ?></div>
					<?php endif; ?>
				</div>

				<div class="card-body">
					<!-- Reservation Status -->
					<?php if (!empty($card_data['reservations'])): ?>
						<div class="reservation-status">
							<div class="status-item <?php echo $card_data['reservations']['status'] === 'walk-ins' ? '' : 'reservations-needed'; ?>">
								<i class="fas <?php echo $card_data['reservations']['icon']; ?>"></i>
								<?php echo $card_data['reservations']['status_text']; ?>
							</div>

							<?php if (!empty($card_data['reservations']['options'])): ?>
								<?php foreach ($card_data['reservations']['options'] as $i => $option): ?>
									<a href="<?php echo $option['link']; ?>" class="reservation-link <?php echo $i > 0 ? 'secondary' : ''; ?>">
										<i class="fas <?php echo $option['icon']; ?>"></i>
										<?php echo $option['label']; ?>
									</a>
								<?php endforeach; ?>
							<?php endif; ?>
						</div>
					<?php endif; ?>

					<!-- All Chips in One Row -->
					<div class="chips-container">
						<?php if (!empty($card_data['chips']['menu_types'])): ?>
							<?php foreach ($card_data['chips']['menu_types'] as $chip): ?>
								<span class="chip menu-type">
									<i class="fas <?php echo $chip['icon']; ?>"></i>
									<?php echo $chip['label']; ?>
								</span>
							<?php endforeach; ?>
						<?php endif; ?>

						<?php if (!empty($card_data['chips']['cuisine'])): ?>
							<?php foreach ($card_data['chips']['cuisine'] as $chip): ?>
								<span class="chip cuisine">
									<i class="fas <?php echo $chip['icon']; ?>"></i>
									<?php echo $chip['label']; ?>
								</span>
							<?php endforeach; ?>
						<?php endif; ?>

						<?php if (!empty($card_data['chips']['neighborhood'])): ?>
							<?php foreach ($card_data['chips']['neighborhood'] as $chip): ?>
								<span class="chip neighborhood">
									<i class="fas <?php echo $chip['icon']; ?>"></i>
									<?php echo $chip['label']; ?>
								</span>
							<?php endforeach; ?>
						<?php endif; ?>

						<?php if (!empty($card_data['chips']['vibes'])): ?>
							<?php foreach ($card_data['chips']['vibes'] as $chip): ?>
								<span class="chip vibe">
									<i class="fas <?php echo $chip['icon']; ?>"></i>
									<?php echo $chip['label']; ?>
								</span>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>
				</div>

				<!-- Debug Section (Hidden by default) -->
				<div class="debug-section" id="debug-section">
					<strong>Debug Info - Restaurant ID: <?php echo $restaurant_id; ?></strong>
					<pre><?php print_r($card_data); ?></pre>
				</div>
			</div>

		<?php else: ?>
			<div class="card">
				<div class="card-body">
					<p><strong>No data found for restaurant ID: <?php echo $restaurant_id; ?></strong></p>
					<p>Either the restaurant doesn't exist or the ID is wrong.</p>
				</div>
			</div>
		<?php endif; ?>
	</div>

	<script>
		function toggleDebug() {
			const debugSection = document.getElementById('debug-section');
			debugSection.classList.toggle('show');
		}
	</script>
</body>

</html>