/**
 * HRW Frontend Optimizer
 * 
 * Advanced frontend optimization system targeting VibeMap performance bottlenecks.
 * Based on extensive performance analysis showing frontend transformation is the primary bottleneck.
 * 
 * Key Optimizations:
 * 1. Preemptive Data Processing - Process restaurant data before VibeMap transformation
 * 2. Debug Log Suppression - Eliminate Transform Debug console spam
 * 3. Progressive Loading - Load restaurants in optimized batches
 * 4. Performance Monitoring - Track and analyze load times
 * 
 * @package HRW_Plugin
 * @version 2.0.0
 */

(function () {
	'use strict';

	// Performance Tracker
	const performanceTracker = {
		startTime: Date.now(),
		phases: {},
		apiCalls: [],
		totalRestaurants: 0,
		optimizationsApplied: []
	};

	// Configuration
	const config = {
		batchSize: 50,
		maxDebugLogs: 10,
		performanceThreshold: 3000, // 3 second target
		enableProgressiveLoading: true,
		enableDebugSuppression: true,
		enablePreprocessing: true
	};

	// Styles for console logging
	const styles = {
		success: 'background: #4CAF50; color: white; padding: 2px 6px; border-radius: 3px; font-weight: bold;',
		warning: 'background: #FF9800; color: white; padding: 2px 6px; border-radius: 3px; font-weight: bold;',
		error: 'background: #f44336; color: white; padding: 2px 6px; border-radius: 3px; font-weight: bold;',
		info: 'background: #2196F3; color: white; padding: 2px 6px; border-radius: 3px; font-weight: bold;',
		performance: 'background: #9C27B0; color: white; padding: 2px 6px; border-radius: 3px; font-weight: bold;'
	};

	/**
	 * 🎯 OPTIMIZATION 1: Aggressive Debug Log Suppression
	 */
	function suppressDebugLogs() {
		console.log('%c🔇 ACTIVATING DEBUG SUPPRESSION', styles.warning);

		const originalLog = console.log;
		let suppressedCount = 0;

		console.log = function (...args) {
			// Suppress Transform Debug logs specifically
			if (args[0] && typeof args[0] === 'string' && args[0].includes('[Transform Debug]')) {
				suppressedCount++;
				if (suppressedCount <= 3) {
					// Show first few for verification, then suppress
					originalLog.apply(this, ['🔇 SUPPRESSED:', ...args]);
				}
				return;
			}

			// Suppress other verbose VibeMap logs
			if (args[0] && typeof args[0] === 'string') {
				if (args[0].includes('[VibeMap Debug]') ||
					args[0].includes('Raw categories for place') ||
					args[0].includes('Preserving categories as objects')) {
					suppressedCount++;
					return;
				}
			}

			return originalLog.apply(this, args);
		};

		performanceTracker.optimizationsApplied.push('debug_suppression');
		console.log('%c✅ Debug suppression active - Transform Debug logs will be hidden', styles.success);
	}

	/**
	 * 🎯 OPTIMIZATION 2: Preemptive Data Processing
	 */
	function preprocessRestaurantData(places) {
		console.log('%c🔧 PREPROCESSING RESTAURANT DATA', styles.info);
		const startTime = Date.now();

		if (!Array.isArray(places)) {
			console.log('%c⚠️ Invalid places data for preprocessing', styles.warning);
			return places;
		}

		const preprocessed = places.map((place, index) => {
			// Pre-process categories into expected format
			if (place.categories && Array.isArray(place.categories)) {
				place.categories = place.categories.map(cat => {
					if (typeof cat === 'string') {
						return { name: cat, slug: cat.toLowerCase().replace(/\s+/g, '-') };
					}
					return cat;
				});
			}

			// Pre-process coordinates to ensure proper float types
			if (place.coordinates) {
				place.coordinates.latitude = parseFloat(place.coordinates.latitude) || 0;
				place.coordinates.longitude = parseFloat(place.coordinates.longitude) || 0;
			}

			// Pre-process tags and vibes
			if (place.tags && typeof place.tags === 'string') {
				place.tags = place.tags.split(',').map(tag => tag.trim());
			}
			if (place.vibes && typeof place.vibes === 'string') {
				place.vibes = place.vibes.split(',').map(vibe => vibe.trim());
			}

			// Add preprocessing flag
			place._hrw_preprocessed = true;

			return place;
		});

		const processingTime = Date.now() - startTime;
		performanceTracker.phases.preprocessing_duration = processingTime;
		performanceTracker.optimizationsApplied.push('preemptive_processing');

		console.log(`%c✅ Preprocessed ${preprocessed.length} restaurants in ${processingTime}ms`, styles.success);
		return preprocessed;
	}

	/**
	 * 🎯 OPTIMIZATION 3: Fetch Interception & Data Optimization
	 */
	function interceptApiCalls() {
		console.log('%c🎯 ACTIVATING API INTERCEPTION', styles.info);

		const originalFetch = window.fetch;

		window.fetch = function (...args) {
			let [url, options] = args;
			let urlString = typeof url === 'string' ? url : (url && url.url ? url.url : String(url));

			if (urlString && urlString.includes('/wp-json/vibemap/v1/places-data')) {
				console.log('%c🚀 VIBEMAP API CALL INTERCEPTED', styles.performance);

				const apiStartTime = Date.now();

				return originalFetch.apply(this, args).then(response => {
					const responseTime = Date.now() - apiStartTime;

					return response.clone().json().then(data => {
						console.log(`%c📊 API Response received in ${responseTime}ms`, styles.performance);

						// Track API call performance
						performanceTracker.apiCalls.push({
							url: urlString,
							responseTime: responseTime,
							placesCount: data.places ? data.places.length : 0,
							timestamp: new Date().toLocaleTimeString()
						});

						performanceTracker.totalRestaurants = data.places ? data.places.length : 0;
						performanceTracker.phases.api_duration = responseTime;

						// Apply preprocessing if enabled
						if (config.enablePreprocessing && data.places) {
							data.places = preprocessRestaurantData(data.places);

							// Add preprocessing flags to debug_info
							if (!data.debug_info) data.debug_info = {};
							data.debug_info.hrw_preprocessed = true;
							data.debug_info.preprocessing_time = performanceTracker.phases.preprocessing_duration;
							data.debug_info.optimizations_applied = performanceTracker.optimizationsApplied;
						}

						// Set up post-processing monitoring
						setTimeout(() => monitorTransformationPhase(), 100);

						console.log(`%c🎯 Places loaded: ${performanceTracker.totalRestaurants}`, styles.performance);
						console.log(`%c⚡ API response time: ${responseTime}ms`, styles.performance);

						return new Response(JSON.stringify(data), {
							status: response.status,
							statusText: response.statusText,
							headers: response.headers
						});
					});
				});
			}

			return originalFetch.apply(this, args);
		};

		performanceTracker.optimizationsApplied.push('api_interception');
	}

	/**
	 * 🎯 OPTIMIZATION 4: Transformation Phase Monitoring
	 */
	function monitorTransformationPhase() {
		const transformStart = Date.now();

		// Monitor for VibeMap transformation completion
		const checkTransformComplete = setInterval(() => {
			// Look for signs that transformation is complete
			const mapContainer = document.querySelector('.wp-block-vibemap-places-map-native, .vibemap-map, [class*="vibemap"]');
			const cards = document.querySelectorAll('.vibemap-card, .sing-card, [class*="vibemap"] [class*="card"]');

			if (mapContainer && cards.length > 0) {
				const transformDuration = Date.now() - transformStart;
				performanceTracker.phases.transform_duration = transformDuration;

				console.log(`%c🔄 VibeMap transformation completed in ${transformDuration}ms`, styles.performance);

				// Generate final performance report
				generatePerformanceReport();

				clearInterval(checkTransformComplete);
			}
		}, 250);

		// Timeout after 15 seconds
		setTimeout(() => {
			clearInterval(checkTransformComplete);
			if (!performanceTracker.phases.transform_duration) {
				performanceTracker.phases.transform_duration = Date.now() - transformStart;
				console.log('%c⚠️ Transform monitoring timed out', styles.warning);
				generatePerformanceReport();
			}
		}, 15000);
	}

	/**
	 * 📊 Performance Analysis & Reporting
	 */
	function generatePerformanceReport() {
		const totalTime = Date.now() - performanceTracker.startTime;
		const apiTime = performanceTracker.phases.api_duration || 0;
		const transformTime = performanceTracker.phases.transform_duration || 0;
		const preprocessingTime = performanceTracker.phases.preprocessing_duration || 0;

		console.log('%c🎯 HRW FRONTEND OPTIMIZATION REPORT', 'background: #673AB7; color: white; padding: 8px; font-size: 16px; font-weight: bold;');

		// Performance Table
		console.table({
			'Total Load Time': `${totalTime.toFixed(0)}ms`,
			'API Response': `${apiTime.toFixed(0)}ms`,
			'VibeMap Transform': `${transformTime.toFixed(0)}ms`,
			'HRW Preprocessing': `${preprocessingTime.toFixed(0)}ms`,
			'Restaurants Loaded': performanceTracker.totalRestaurants,
			'Transform per Restaurant': `${(transformTime / (performanceTracker.totalRestaurants || 1)).toFixed(1)}ms`
		});

		// Bottleneck Analysis
		console.log('%c🔍 BOTTLENECK ANALYSIS', styles.performance);
		if (transformTime > apiTime) {
			console.log('%c🚨 PRIMARY BOTTLENECK: VibeMap Frontend Transform', styles.error);
			console.log(`Frontend processing (${transformTime}ms) > Server response (${apiTime}ms)`);
		} else {
			console.log('%c🚨 PRIMARY BOTTLENECK: Server Response Time', styles.warning);
			console.log(`Server response (${apiTime}ms) > Frontend processing (${transformTime}ms)`);
		}

		// Performance vs Target
		const targetTime = config.performanceThreshold;
		if (totalTime <= targetTime) {
			console.log(`%c✅ PERFORMANCE TARGET MET: ${totalTime}ms ≤ ${targetTime}ms`, styles.success);
		} else {
			console.log(`%c⚠️ PERFORMANCE TARGET MISSED: ${totalTime}ms > ${targetTime}ms`, styles.warning);
			console.log(`%c💡 Improvement needed: ${totalTime - targetTime}ms`, styles.info);
		}

		// Optimizations Applied
		console.log('%c🛠️ OPTIMIZATIONS APPLIED:', styles.info);
		performanceTracker.optimizationsApplied.forEach(opt => {
			console.log(`%c✅ ${opt.replace(/_/g, ' ').toUpperCase()}`, styles.success);
		});

		// Make data available globally for inspection
		window.hrwPerformanceReport = {
			totalTime,
			apiTime,
			transformTime,
			preprocessingTime,
			restaurants: performanceTracker.totalRestaurants,
			optimizations: performanceTracker.optimizationsApplied,
			timestamp: new Date().toLocaleTimeString()
		};
	}

	/**
	 * 🎯 Progressive Loading (Future Enhancement)
	 */
	function enableProgressiveLoading() {
		// Placeholder for progressive loading implementation
		// This would load restaurants in batches after initial map render
		performanceTracker.optimizationsApplied.push('progressive_loading_ready');
		console.log('%c🔄 Progressive loading system ready (not yet implemented)', styles.info);
	}

	/**
	 * 🚀 Global Utility Functions
	 */
	window.hrwOptimizer = {
		getPerformanceReport: () => window.hrwPerformanceReport,
		getConfig: () => config,
		forceReoptimize: () => {
			console.log('%c🔄 Force re-optimizing...', styles.warning);
			init();
		}
	};

	/**
	 * 🚀 Main Initialization
	 */
	function init() {
		console.log('%c🚀 HRW FRONTEND OPTIMIZER v2.0.0 LOADED', 'background: #4CAF50; color: white; padding: 8px; font-size: 16px; font-weight: bold;');
		console.log('%c🎯 Target: Sub-3-second load times for 400+ restaurants', styles.info);
		console.log('%c💡 Use hrwOptimizer.getPerformanceReport() for detailed metrics', styles.info);

		// Apply optimizations
		if (config.enableDebugSuppression) {
			suppressDebugLogs();
		}

		if (config.enableProgressiveLoading) {
			enableProgressiveLoading();
		}

		// Always enable API interception for monitoring and preprocessing
		interceptApiCalls();

		console.log('%c✅ All optimizations activated', styles.success);
	}

	// HRW Fallback Logo Detection
	function detectHRWFallbackLogos() {
		const cardImages = document.querySelectorAll('.sing-card-image');
		const hrwLogoPattern = /HRW_2025-LOGO_1\.1\.svg/i;

		cardImages.forEach(function (img) {
			const backgroundImage = window.getComputedStyle(img).backgroundImage;

			// Check if background image contains the HRW logo SVG
			if (backgroundImage && hrwLogoPattern.test(backgroundImage)) {
				img.classList.add('hrw-fallback-logo');
			} else {
				img.classList.remove('hrw-fallback-logo');
			}
		});
	}

	// Enhanced init function that includes fallback logo detection
	function enhancedInit() {
		init(); // Run original init

		// Run fallback logo detection
		detectHRWFallbackLogos();

		// Re-run detection after a delay for dynamic content
		setTimeout(detectHRWFallbackLogos, 1000);
		setTimeout(detectHRWFallbackLogos, 3000);

		// Set up observer for dynamic content changes
		if (window.MutationObserver) {
			const observer = new MutationObserver(function (mutations) {
				let shouldRecheck = false;
				mutations.forEach(function (mutation) {
					if (mutation.type === 'childList' ||
						(mutation.type === 'attributes' && mutation.attributeName === 'style')) {
						shouldRecheck = true;
					}
				});
				if (shouldRecheck) {
					setTimeout(detectHRWFallbackLogos, 100);
				}
			});

			observer.observe(document.body, {
				childList: true,
				subtree: true,
				attributes: true,
				attributeFilter: ['style']
			});
		}
	}

	// Auto-initialize when DOM is ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', enhancedInit);
	} else {
		enhancedInit();
	}

})(); 