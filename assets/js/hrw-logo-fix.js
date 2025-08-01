/**
 * HRW Fallback Logo Fix
 * 
 * Lightweight script to detect and mark HRW fallback logos
 * 
 * @package HRW_Plugin
 * @since 1.0.0
 */

(function () {
	'use strict';

	// HRW Logo Detection Object
	const HRWLogoFix = {

		// Debouncing for performance
		logoDetectionTimeout: null,

		/**
		 * Initialize logo detection
		 */
		init: function () {
			// Run initial detection
			this.detectHRWFallbackLogos();

			// Re-run detection after delays for dynamic content
			const self = this;
			setTimeout(function () { self.detectHRWFallbackLogos(); }, 1000);
			setTimeout(function () { self.detectHRWFallbackLogos(); }, 3000);

			// Set up mutation observer for dynamic content
			this.setupMutationObserver();
		},

		/**
		 * Set up mutation observer for dynamic content changes
		 */
		setupMutationObserver: function () {
			const self = this;

			if (!window.MutationObserver) {
				return;
			}

			const observer = new MutationObserver(function (mutations) {
				let shouldRecheckLogos = false;

				mutations.forEach(function (mutation) {
					if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
						shouldRecheckLogos = true;
					}
					// Also watch for style changes that might affect background images
					if (mutation.type === 'attributes' && mutation.attributeName === 'style') {
						shouldRecheckLogos = true;
					}
				});

				// Re-run fallback logo detection with debouncing
				if (shouldRecheckLogos) {
					self.debouncedLogoDetection();
				}
			});

			// Start observing
			observer.observe(document.body, {
				childList: true,
				subtree: true,
				attributes: true,
				attributeFilter: ['style']
			});
		},

		/**
		 * Debounced logo detection to prevent performance issues
		 */
		debouncedLogoDetection: function () {
			const self = this;

			// Clear existing timeout
			if (this.logoDetectionTimeout) {
				clearTimeout(this.logoDetectionTimeout);
			}

			// Set new timeout with longer delay to batch multiple changes
			this.logoDetectionTimeout = setTimeout(function () {
				self.detectHRWFallbackLogos();
				self.logoDetectionTimeout = null;
			}, 500); // 500ms delay to batch rapid changes
		},

		/**
		 * Detect and mark HRW fallback logos
		 */
		detectHRWFallbackLogos: function () {
			const cardImages = document.querySelectorAll('.sing-card-image');
			const hrwLogoPattern = /HRW_2025-LOGO_1\.1\.svg/i;

			// Exit early if no card images found
			if (cardImages.length === 0) {
				return;
			}

			cardImages.forEach(function (img) {
				const backgroundImage = window.getComputedStyle(img).backgroundImage;

				// Check if background image contains the HRW logo SVG
				if (backgroundImage && hrwLogoPattern.test(backgroundImage)) {
					if (!img.classList.contains('hrw-fallback-logo')) {
						img.classList.add('hrw-fallback-logo');
					}
				} else {
					if (img.classList.contains('hrw-fallback-logo')) {
						img.classList.remove('hrw-fallback-logo');
					}
				}
			});
		}
	};

	// Initialize when DOM is ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () {
			HRWLogoFix.init();
		});
	} else {
		// DOM is already ready
		HRWLogoFix.init();
	}

	// Also initialize on window load for better compatibility
	window.addEventListener('load', function () {
		setTimeout(function () {
			HRWLogoFix.detectHRWFallbackLogos();
		}, 1000);
	});

	// Make available globally for testing
	window.HRWLogoFix = HRWLogoFix;
	window.testHRWLogoDetection = function () {
		HRWLogoFix.detectHRWFallbackLogos();
		console.log('HRW Logo Detection: Test complete');
	};

})(); 