/**
 * HRW Gutenberg Block Performance Timing
 * 
 * Measures frontend React component performance and API coordination
 * Helps identify if delays are in API calls vs frontend processing
 * 
 * @package HRW_Plugin
 * @since 1.2.0
 */

(function () {
	'use strict';

	// Performance tracker for Gutenberg/React components
	const frontendTracker = {
		apiCalls: {},
		componentTimes: {},
		renderingTimes: {},
		startTime: Date.now()
	};

	console.log('%c🎯 HRW Frontend Timing: Initialized', 'color: #007cba; font-weight: bold;');

	/**
	 * Track API call performance
	 */
	function trackApiCall(url, startTime, endTime, size) {
		const duration = endTime - startTime;
		const apiName = url.includes('places-data') ? 'places-data' : 'settings';

		frontendTracker.apiCalls[apiName] = {
			url: url,
			duration: duration,
			size: size || 'unknown',
			startTime: startTime,
			endTime: endTime,
			timestamp: new Date().toLocaleTimeString()
		};

		console.log(`%c📡 API Call [${apiName}]: ${duration}ms`, 'color: #d63638; font-weight: bold;');

		if (size) {
			const sizeKB = Math.round(size / 1024);
			console.log(`%c📊 Payload size [${apiName}]: ${sizeKB}KB`, 'color: #d63638;');
		}
	}

	/**
	 * Track component rendering performance
	 */
	function trackComponentRender(componentName, startTime, endTime) {
		const duration = endTime - startTime;

		frontendTracker.componentTimes[componentName] = {
			duration: duration,
			startTime: startTime,
			endTime: endTime,
			timestamp: new Date().toLocaleTimeString()
		};

		console.log(`%c⚛️ Component [${componentName}]: ${duration}ms`, 'color: #00a32a; font-weight: bold;');
	}

	/**
	 * Intercept fetch calls to track API performance
	 */
	const originalFetch = window.fetch;
	window.fetch = function (...args) {
		let url = args[0];
		if (typeof url === 'object' && url.url) {
			url = url.url;
		}

		// Only track VibeMap API calls
		if (url.includes('/wp-json/vibemap/v1/')) {
			const apiStartTime = Date.now();
			console.log(`%c🚀 API Request started: ${url}`, 'color: #007cba;');

			return originalFetch.apply(this, args).then(response => {
				const apiEndTime = Date.now();

				// Get response size if possible
				const contentLength = response.headers.get('content-length');
				const responseSize = contentLength ? parseInt(contentLength) : null;

				trackApiCall(url, apiStartTime, apiEndTime, responseSize);

				return response;
			}).catch(error => {
				const apiEndTime = Date.now();
				console.error(`%c❌ API Error: ${url}`, 'color: #d63638;', error);
				trackApiCall(url, apiStartTime, apiEndTime);
				throw error;
			});
		}

		return originalFetch.apply(this, args);
	};

	/**
	 * Monitor DOM changes for React component rendering
	 */
	function monitorComponentRendering() {
		const observer = new MutationObserver(function (mutations) {
			mutations.forEach(function (mutation) {
				if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
					mutation.addedNodes.forEach(function (node) {
						if (node.nodeType === 1) { // Element node
							// Check for VibeMap related classes/elements
							if (node.classList && (
								node.classList.contains('vibemap') ||
								node.classList.contains('wp-block-vibemap') ||
								node.querySelector && node.querySelector('[class*="vibemap"]')
							)) {
								const renderTime = Date.now() - frontendTracker.startTime;
								console.log(`%c🎨 VibeMap DOM Element rendered at: ${renderTime}ms`, 'color: #00a32a;');

								frontendTracker.renderingTimes['dom_element'] = {
									time: renderTime,
									element: node.className || node.tagName,
									timestamp: new Date().toLocaleTimeString()
								};
							}
						}
					});
				}
			});
		});

		observer.observe(document.body, {
			childList: true,
			subtree: true
		});
	}

	/**
	 * Track map/component initialization
	 */
	function trackMapInitialization() {
		// Check for map container every 100ms
		const mapCheckInterval = setInterval(function () {
			const mapContainer = document.querySelector('[id*="map"], [class*="map"], .vibemap-container');

			if (mapContainer && !frontendTracker.componentTimes['map_container']) {
				const mapTime = Date.now() - frontendTracker.startTime;
				console.log(`%c🗺️ Map container found at: ${mapTime}ms`, 'color: #00a32a; font-weight: bold;');

				trackComponentRender('map_container', frontendTracker.startTime, Date.now());
				clearInterval(mapCheckInterval);
			}
		}, 100);

		// Stop checking after 30 seconds
		setTimeout(() => clearInterval(mapCheckInterval), 30000);
	}

	/**
	 * Generate performance report
	 */
	function generatePerformanceReport() {
		const totalTime = Date.now() - frontendTracker.startTime;

		const report = {
			totalFrontendTime: totalTime,
			apiCalls: frontendTracker.apiCalls,
			componentTimes: frontendTracker.componentTimes,
			renderingTimes: frontendTracker.renderingTimes,
			summary: {
				apiCallCount: Object.keys(frontendTracker.apiCalls).length,
				totalApiTime: Object.values(frontendTracker.apiCalls).reduce((sum, call) => sum + call.duration, 0),
				componentCount: Object.keys(frontendTracker.componentTimes).length,
				averageApiTime: Object.keys(frontendTracker.apiCalls).length > 0
					? Math.round(Object.values(frontendTracker.apiCalls).reduce((sum, call) => sum + call.duration, 0) / Object.keys(frontendTracker.apiCalls).length)
					: 0
			}
		};

		console.log('%c📊 HRW Frontend Performance Report:', 'color: #007cba; font-weight: bold; font-size: 14px;');
		console.table(report.summary);
		console.log('Full report:', report);

		return report;
	}

	// Initialize monitoring
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () {
			monitorComponentRendering();
			trackMapInitialization();
		});
	} else {
		monitorComponentRendering();
		trackMapInitialization();
	}

	// Generate report after 10 seconds
	setTimeout(generatePerformanceReport, 10000);

	// Make functions available globally for debugging
	window.hrwFrontendTiming = {
		getReport: generatePerformanceReport,
		tracker: frontendTracker
	};

})(); 