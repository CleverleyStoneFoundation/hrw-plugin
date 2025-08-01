/**
 * HRW Restaurant Card Enhancer
 * 
 * Enhances VibeMap cards with HRW restaurant card data
 * 
 * @package HRW_Plugin
 * @since 1.0.0
 */

(function ($) {
	'use strict';

	// HRW Card Enhancer Object
	const HRWCardEnhancer = {

		// Store the intercepted API data
		apiPlacesData: [],
		placesDataMap: {},

		/**
		 * Initialize the card enhancer
		 */
		init: function () {
			console.log('HRW Card Enhancer: Initializing');
			console.log('HRW Card Enhancer: jQuery version:', $.fn.jquery);
			console.log('HRW Card Enhancer: DOM ready state:', document.readyState);
			console.log('HRW Card Enhancer: Window vibemapData:', window.vibemapData);

			// Set up API debugging
			this.setupApiDebugging();

			// Wait for VibeMap to load and render cards
			this.waitForVibeMapCards();

			// Set up mutation observer for dynamically loaded cards
			this.setupMutationObserver();
		},

		/**
 * Wait for VibeMap cards to be rendered
 */
		waitForVibeMapCards: function () {
			const self = this;

			// Check every 500ms for up to 10 seconds
			let attempts = 0;
			const maxAttempts = 20;

			const checkForCards = setInterval(function () {
				attempts++;
				console.log('HRW Card Enhancer: Checking for cards, attempt:', attempts);

				// Look for existing cards on the page
				const existingCards = document.querySelectorAll('.sing-card, .wp-block-vibemap-single-card, .vibemap-card, [class*="vibemap"] [class*="card"]');
				console.log('HRW Card Enhancer: Found', existingCards.length, 'potential cards');

				if (self.enhanceExistingCards() > 0 || attempts >= maxAttempts) {
					clearInterval(checkForCards);
					console.log('HRW Card Enhancer: Stopped checking for cards');
				}
			}, 500);
		},

		/**
 * Set up API debugging to intercept VibeMap API calls
 */
		setupApiDebugging: function () {
			const self = this;
			const originalFetch = window.fetch;

			window.fetch = function (...args) {
				const url = args[0];

				// Check if this is a VibeMap API call
				if (url && url.includes('/vibemap/v1/places-data')) {
					console.log('HRW Card Enhancer: Intercepted VibeMap API call to:', url);

					return originalFetch.apply(this, args)
						.then(response => {
							// Clone the response to read it without affecting the original
							const clonedResponse = response.clone();

							clonedResponse.json().then(data => {
								console.log('HRW Card Enhancer: VibeMap API response:', data);

								if (data.places) {
									console.log('HRW Card Enhancer: API returned', data.places.length, 'places');

									// Store the API data for later use
									self.apiPlacesData = data.places;
									self.createPlacesDataMap(data.places);

									// Check if any place has HRW card data
									const placesWithHRWData = data.places.filter(place => place.hrw_card_data);
									console.log('HRW Card Enhancer: Places with HRW card data:', placesWithHRWData.length);

									if (placesWithHRWData.length > 0) {
										console.log('HRW Card Enhancer: Sample HRW card data:', placesWithHRWData[0].hrw_card_data);
									}

									// Try to enhance existing cards now that we have data
									setTimeout(() => {
										self.enhanceExistingCards();
									}, 1000);
								}
							}).catch(e => {
								console.error('HRW Card Enhancer: Error parsing API response:', e);
							});

							return response;
						});
				}

				return originalFetch.apply(this, args);
			};
		},

		/**
		 * Create a mapping of places data for easy lookup
		 */
		createPlacesDataMap: function (places) {
			this.placesDataMap = {};

			places.forEach(place => {
				// Map by various possible identifiers
				if (place.id) {
					this.placesDataMap[place.id] = place;
				}
				if (place.title) {
					this.placesDataMap[place.title.toLowerCase()] = place;
				}
				if (place.permalink) {
					this.placesDataMap[place.permalink] = place;
				}
				if (place.slug) {
					this.placesDataMap[place.slug] = place;
				}
			});

			console.log('HRW Card Enhancer: Created places data map with', Object.keys(this.placesDataMap).length, 'entries');
		},

		/**
		 * Set up mutation observer for dynamically loaded cards
		 */
		setupMutationObserver: function () {
			const self = this;

			// Create mutation observer to watch for new cards
			const observer = new MutationObserver(function (mutations) {
				mutations.forEach(function (mutation) {
					if (mutation.type === 'childList') {
						mutation.addedNodes.forEach(function (node) {
							if (node.nodeType === Node.ELEMENT_NODE) {
								self.enhanceCard(node);
							}
						});
					}
				});
			});

			// Start observing
			observer.observe(document.body, {
				childList: true,
				subtree: true
			});
		},

		/**
		 * Enhance existing cards on the page
		 */
		enhanceExistingCards: function () {
			const self = this;
			let enhancedCount = 0;

			// Look for VibeMap cards
			const cardSelectors = [
				'.sing-card',
				'.wp-block-vibemap-single-card',
				'.vibemap-card',
				'[class*="vibemap"] [class*="card"]'
			];

			cardSelectors.forEach(function (selector) {
				const cards = document.querySelectorAll(selector);
				cards.forEach(function (card) {
					if (self.enhanceCard(card)) {
						enhancedCount++;
					}
				});
			});

			if (enhancedCount > 0) {
				console.log('HRW Card Enhancer: Enhanced ' + enhancedCount + ' existing cards');
			}

			return enhancedCount;
		},

		/**
		 * Enhance a single card element
		 */
		enhanceCard: function (cardElement) {
			console.log('HRW Card Enhancer: Attempting to enhance card:', cardElement);

			// Skip if already enhanced
			if (cardElement.hasAttribute('data-hrw-enhanced')) {
				console.log('HRW Card Enhancer: Card already enhanced, skipping');
				return false;
			}

			// Mark as enhanced
			cardElement.setAttribute('data-hrw-enhanced', 'true');

			// Try to get card data from various sources
			const cardData = this.getCardData(cardElement);
			console.log('HRW Card Enhancer: Retrieved card data:', cardData);

			if (cardData && cardData.hrw_card_data) {
				console.log('HRW Card Enhancer: Enhancing card with HRW data:', cardData.hrw_card_data);

				// Add HRW-specific class
				cardElement.classList.add('hrw-enhanced-card');

				// Enhance the card content
				this.enhanceCardContent(cardElement, cardData.hrw_card_data);

				return true;
			} else {
				console.log('HRW Card Enhancer: No HRW card data found, skipping enhancement');
			}

			return false;
		},

		/**
		 * Get card data from various sources
		 */
		getCardData: function (cardElement) {
			let cardData = null;

			// First try to use our stored API data
			if (this.apiPlacesData.length > 0) {
				cardData = this.findMatchingPlace(cardElement);
			}

			// Fallback: Try to get data from element attributes
			if (!cardData) {
				const dataAttribute = cardElement.getAttribute('data-place-data') ||
					cardElement.getAttribute('data-card-data') ||
					cardElement.getAttribute('data-attributes');

				if (dataAttribute) {
					try {
						cardData = JSON.parse(dataAttribute);
					} catch (e) {
						console.warn('HRW Card Enhancer: Failed to parse card data', e);
					}
				}
			}

			// Fallback: Try to get data from window.vibemapData if available
			if (!cardData && window.vibemapData && window.vibemapData.places) {
				// Find matching place data
				const link = cardElement.querySelector('a[href]');
				if (link) {
					const href = link.getAttribute('href');
					cardData = window.vibemapData.places.find(place =>
						place.permalink === href || place.slug === href
					);
				}
			}

			return cardData;
		},

		/**
		 * Find matching place data for a card element
		 */
		findMatchingPlace: function (cardElement) {
			// Try to find matching place data by various identifiers

			// 1. Try by permalink (most reliable)
			const link = cardElement.querySelector('a[href]');
			if (link) {
				const href = link.getAttribute('href');
				if (this.placesDataMap[href]) {
					console.log('HRW Card Enhancer: Found match by permalink:', href);
					return this.placesDataMap[href];
				}
			}

			// 2. Try by title
			const titleElement = cardElement.querySelector('h4.title, .title, h2, h3');
			if (titleElement) {
				const title = titleElement.textContent.trim().toLowerCase();
				if (this.placesDataMap[title]) {
					console.log('HRW Card Enhancer: Found match by title:', title);
					return this.placesDataMap[title];
				}
			}

			// 3. Try by article ID (if available)
			const articleId = cardElement.getAttribute('id');
			if (articleId && this.placesDataMap[articleId]) {
				console.log('HRW Card Enhancer: Found match by article ID:', articleId);
				return this.placesDataMap[articleId];
			}

			// 4. Try by data attributes
			const dataId = cardElement.getAttribute('data-id') || cardElement.getAttribute('data-place-id');
			if (dataId && this.placesDataMap[dataId]) {
				console.log('HRW Card Enhancer: Found match by data ID:', dataId);
				return this.placesDataMap[dataId];
			}

			console.log('HRW Card Enhancer: No matching place data found for card element:', cardElement);
			return null;
		},

		/**
		 * Enhance card content with HRW data
		 */
		enhanceCardContent: function (cardElement, hrwCardData) {
			try {
				// Add subtitle if available
				if (hrwCardData.display_subtitle) {
					this.addSubtitle(cardElement, hrwCardData.display_subtitle);
				}

				// Add chips/bubbles for taxonomy data
				if (hrwCardData.chips && hrwCardData.chips.length > 0) {
					this.addChips(cardElement, hrwCardData.chips);
				}

				// Enhance reservation information
				if (hrwCardData.reservation_data) {
					this.enhanceReservationInfo(cardElement, hrwCardData.reservation_data);
				}

				// Add custom CSS classes for styling
				this.addCustomClasses(cardElement, hrwCardData);

			} catch (e) {
				console.error('HRW Card Enhancer: Error enhancing card content', e);
			}
		},

		/**
		 * Add subtitle to card
		 */
		addSubtitle: function (cardElement, subtitle) {
			const subtitleElement = cardElement.querySelector('.subtitle');
			if (subtitleElement) {
				subtitleElement.textContent = subtitle;
			} else {
				// Create new subtitle element
				const titleElement = cardElement.querySelector('.title, h4, h3, h2');
				if (titleElement) {
					const newSubtitle = document.createElement('div');
					newSubtitle.className = 'subtitle hrw-subtitle';
					newSubtitle.textContent = subtitle;
					titleElement.insertAdjacentElement('afterend', newSubtitle);
				}
			}
		},

		/**
		 * Add chips/bubbles to card
		 */
		addChips: function (cardElement, chips) {
			// Remove existing chip containers
			const existingChips = cardElement.querySelectorAll('.chip-container');
			existingChips.forEach(container => container.remove());

			// Group chips by type
			const chipsByType = {};
			chips.forEach(chip => {
				if (!chipsByType[chip.type]) {
					chipsByType[chip.type] = [];
				}
				chipsByType[chip.type].push(chip);
			});

			// Create chip containers
			Object.keys(chipsByType).forEach(type => {
				const container = document.createElement('ul');
				container.className = `chip-container ${type}`;

				chipsByType[type].forEach(chip => {
					const chipElement = document.createElement('li');
					chipElement.className = 'chip';
					chipElement.style.backgroundColor = chip.color || '#ccc';

					// Add icon if available
					if (chip.icon) {
						const icon = document.createElement('i');
						icon.className = `fas ${chip.icon}`;
						chipElement.appendChild(icon);
						chipElement.appendChild(document.createTextNode(' '));
					}

					chipElement.appendChild(document.createTextNode(chip.label));
					container.appendChild(chipElement);
				});

				// Add container to card
				const cardBottom = cardElement.querySelector('.sing-card-bottom, .inner-column, .card-content');
				if (cardBottom) {
					cardBottom.appendChild(container);
				}
			});
		},

		/**
		 * Enhance reservation information
		 */
		enhanceReservationInfo: function (cardElement, reservationData) {
			// Find existing reservation elements
			const reservationElements = cardElement.querySelectorAll('[class*="reservation"], [class*="contact"]');

			reservationElements.forEach(element => {
				// Add reservation status
				if (reservationData.status) {
					const statusElement = document.createElement('div');
					statusElement.className = 'hrw-reservation-status';
					statusElement.innerHTML = `<i class="fas ${reservationData.status_icon}"></i> ${reservationData.status}`;
					element.insertAdjacentElement('beforebegin', statusElement);
				}

				// Update reservation links
				if (reservationData.options) {
					this.updateReservationLinks(element, reservationData.options);
				}
			});
		},

		/**
		 * Update reservation links
		 */
		updateReservationLinks: function (container, options) {
			// Clear existing links
			const existingLinks = container.querySelectorAll('a');
			existingLinks.forEach(link => link.remove());

			// Add new links
			options.forEach(option => {
				const link = document.createElement('a');
				link.href = option.url;
				link.className = `hrw-reservation-link ${option.type}`;
				link.innerHTML = `<i class="fas ${option.icon}"></i> ${option.label}`;

				if (option.target) {
					link.target = option.target;
				}

				container.appendChild(link);
			});
		},

		/**
		 * Add custom CSS classes
		 */
		addCustomClasses: function (cardElement, hrwCardData) {
			// Add classes based on card data
			if (hrwCardData.reservation_data) {
				if (hrwCardData.reservation_data.is_walk_ins) {
					cardElement.classList.add('hrw-walk-ins');
				} else {
					cardElement.classList.add('hrw-reservations-needed');
				}
			}

			if (hrwCardData.has_reservations) {
				cardElement.classList.add('hrw-has-reservations');
			}
		}
	};

	// Initialize when DOM is ready
	$(document).ready(function () {
		HRWCardEnhancer.init();
	});

	// Also initialize on window load for better compatibility
	$(window).on('load', function () {
		setTimeout(function () {
			HRWCardEnhancer.enhanceExistingCards();
		}, 1000);
	});

	// Make HRWCardEnhancer available globally
	window.HRWCardEnhancer = HRWCardEnhancer;

})(jQuery); 