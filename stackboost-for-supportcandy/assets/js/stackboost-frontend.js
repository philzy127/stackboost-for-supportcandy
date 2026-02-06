/**
 * StackBoost Frontend Script
 *
 * This script is fully configurable via the admin settings page.
 * It uses a single MutationObserver for efficiency and is driven by the
 * `stackboost_settings` object localized from PHP.
 *
 * @package StackBoost\ForSupportCandy
 */

(function ($) {
	'use strict';

	// `stackboost_settings` is localized from PHP in the main plugin file.
	const settings = window.stackboost_settings || {};
	const features = settings.features || {};

	/**
	 * Main initializer. Sets up a MutationObserver to watch for DOM changes,
	 * ensuring features are applied even on AJAX-loaded content.
	 */
	function init() {
		const observer = new MutationObserver(function (mutations) {
			if (mutations.length) {
				run_features();
			}
		});

		observer.observe(document.body, {
			childList: true,
			subtree: true,
		});

		run_features();
	}

	/**
	 * Central dispatcher. Checks if features are enabled and calls the corresponding function.
	 */
	function run_features() {
		if (features.ticket_details_card?.enabled) {
			feature_ticket_details_card();
		}

		let contextualRuleApplied = false;
		if (features.contextual_views?.enabled) {
			contextualRuleApplied = feature_contextual_views();
		}

		// If Contextual Views applied a rule, we skip the legacy Conditional Views logic for visibility.
		// However, we might still want 'hide empty columns' logic to run ON TOP of the contextual view?
		// The proposal says: "If a ContextualView rule exists... it takes precedence and disables the old ConditionalView logic".
		// It doesn't explicitly say disable "Hide Empty Columns", but usually specific view settings override dynamic ones.
		// For safety, let's assume we skip the legacy manager entirely if a contextual rule matched.
		if (!contextualRuleApplied) {
			const emptyColsConfig = features.hide_empty_columns || {};
			const conditionalConfig = features.conditional_hiding || {};
			if (emptyColsConfig.enabled || emptyColsConfig.hide_priority || conditionalConfig.enabled) {
				feature_manage_column_visibility();
			}
		}

		if (features.ticket_type_hiding?.enabled) {
			feature_hide_ticket_types_for_non_agents();
		}
	}

	/**
	 * Feature: Contextual Ticket Views (Revamp).
	 * Returns true if a rule was found and applied.
	 */
	function feature_contextual_views() {
		const rules = features.contextual_views.rules || {};
		const currentViewId = document.querySelector('#wpsc-input-filter')?.value || '0';
		const pageView = currentViewId.replace('default-', ''); // Clean up 'default-' prefix if present

		// Find rule for this view
		// The rules object is keyed by rule ID, but we need to search by view_id.
		let activeRule = null;
		for (const id in rules) {
			if (rules[id].view_id == pageView) {
				activeRule = rules[id];
				break;
			}
		}

		if (!activeRule) {
			return false; // No rule for this view, fall back to legacy
		}

		const table = document.querySelector('table.wpsc-ticket-list-tbl');
		if (!table) return false;

		const thead = table.querySelector('thead');
		const tbody = table.querySelector('tbody');
		if (!thead || !tbody) return false;

		const headerRow = thead.querySelector('tr');
		const bodyRows = Array.from(tbody.querySelectorAll('tr'));

		const currentHeaders = Array.from(headerRow.children);

		// Map current column slugs (or text) to their indices
		// We rely on text content matching the configured column labels or slugs?
		// The rule stores slugs. The table headers display Labels.
		// We need a map of Slug -> Header Text or vice versa.
		// PHP passes `columns` map (slug -> label).
		// We can try to match header text to the known labels.

		const allColumns = features.contextual_views.all_columns || {};
		const headerMap = []; // Index -> Slug

		currentHeaders.forEach((th, index) => {
			const text = th.textContent.trim();
			// Reverse lookup in allColumns (Label -> Slug)
			// This is fuzzy because labels might have HTML or be slightly different.
			// Ideally we would add data-slug attributes to TH in PHP, but we can't easily hook there without replacing templates.
			// So we try best effort text match.
			let matchedSlug = null;
			for (const slug in allColumns) {
				if (allColumns[slug] === text) {
					matchedSlug = slug;
					break;
				}
			}
			// Fallback: Check if it matches a standard field manually if not found (e.g. checkbox column?)
			if (!matchedSlug) {
				// Special handling for checkbox or actions if any?
				// SC usually has Ticket ID, Subject, etc.
			}
			headerMap[index] = matchedSlug;
		});

		// Calculate desired order
		const desiredSlugs = activeRule.columns || [];

		// Create a visibility plan: Show/Hide based on presence in desiredSlugs
		// AND Reorder.

		// We will reconstruct the rows.
		// NOTE: This is destructive to events if we just move HTML.
		// DataTables: Check if API is available.
		if ($.fn.DataTable && $.fn.DataTable.isDataTable(table)) {
			// If DataTables is active, use colReorder if available, or column().visible().
			// SC DataTables implementation might differ.
			// Use generic DOM approach for visibility first.

			// For ordering: doing it via DOM on a DataTable is risky.
			// We will try to just Hide/Show first, and if possible Reorder visually.
			// Given the complexity of DataTables + DOM manipulation, we start with Visibility.
			// The requirement says "utilize a virtual header mapping" if colReorder not available.
			// Virtual header mapping implies swapping content.

			// Implementation Strategy:
			// 1. Identify indices of desired columns in the current table.
			// 2. Hide everything else.
			// 3. Move the desired columns (TH and TDs) to the front in order.

			const indicesToKeep = [];
			const slugToIndices = {};

			headerMap.forEach((slug, index) => {
				if (slug) {
					slugToIndices[slug] = index;
				}
			});

			// We need to handle columns that are NOT in our slug map (like Checkbox column at index 0 maybe?)
			// Usually index 0 is checkbox. We probably want to keep it?
			// User rule defines "Active Columns". If checkbox isn't in it, do we hide it?
			// Probably yes, strict workspace.

			const newOrderIndices = [];

			// Always keep Checkbox if it exists and looks like one?
			// Checkbox usually has class `wpsc_tl_check_all` or similar.
			const checkboxTh = headerRow.querySelector('.wpsc-tl-chk-all, input[type="checkbox"]')?.closest('th');
			let checkboxIndex = -1;
			if (checkboxTh) {
				checkboxIndex = Array.from(headerRow.children).indexOf(checkboxTh);
				// If strictly following rule, we might hide it if not requested, but usually workspace implies data columns.
				// Let's assume we keep checkbox at the start always for functionality.
				newOrderIndices.push(checkboxIndex);
			}

			desiredSlugs.forEach(slug => {
				// Find index of this slug in current table
				// We iterate headers again to find match because headerMap might be incomplete
				// or we use the map we built.

				// Try to find index by text matching again if map failed, or use map.
				let index = -1;
				// Try map first
				for(let i=0; i<headerMap.length; i++) {
					if (headerMap[i] === slug) {
						index = i;
						break;
					}
				}

				if (index === -1) {
					// Fuzzy search by label
					const label = allColumns[slug];
					if (label) {
						currentHeaders.forEach((th, i) => {
							if (th.textContent.trim() === label) index = i;
						});
					}
				}

				if (index !== -1 && index !== checkboxIndex) {
					newOrderIndices.push(index);
				}
			});

			// Apply Visibility & Order
			// We iterate through all current indices. If not in newOrderIndices, HIDE.
			// If in newOrderIndices, we need to re-append in that order.

			const fragment = document.createDocumentFragment();

			// Reorder Headers
			newOrderIndices.forEach(index => {
				if (currentHeaders[index]) {
					currentHeaders[index].style.display = ''; // Ensure visible
					headerRow.appendChild(currentHeaders[index]); // Move to end (effectively reordering as we loop)
				}
			});

			// Hide others
			currentHeaders.forEach((th, index) => {
				if (!newOrderIndices.includes(index)) {
					th.style.display = 'none';
				}
			});

			// Reorder Body
			bodyRows.forEach(row => {
				const cells = Array.from(row.children);
				newOrderIndices.forEach(index => {
					if (cells[index]) {
						cells[index].style.display = '';
						row.appendChild(cells[index]);
					}
				});

				cells.forEach((td, index) => {
					if (!newOrderIndices.includes(index)) {
						td.style.display = 'none';
					}
				});
			});

			return true;
		}

		return false; // Fallback if table not found or error
	}

	/**
	 * Unified Column Visibility Manager.
	 */
	function feature_manage_column_visibility() {
		const emptyColsConfig = features.hide_empty_columns || {};
		const conditionalConfig = features.conditional_hiding || {};

		const table = document.querySelector('table.wpsc-ticket-list-tbl');
		const tbody = table?.querySelector('tbody');
		if (!table || !tbody || !tbody.rows.length) return;

		const headers = Array.from(table.querySelectorAll('thead tr th'));
		const rows = Array.from(tbody.querySelectorAll('tr'));
		if (!headers.length || !rows.length) return;

		const visibilityPlan = {};
		headers.forEach((_, i) => { visibilityPlan[i] = 'show'; });
		const matrix = rows.map(row => Array.from(row.children).map(td => td.textContent.trim()));

		if (emptyColsConfig.enabled || emptyColsConfig.hide_priority) {
			headers.forEach((th, i) => {
				const headerText = th.textContent.trim().toLowerCase();
				if (emptyColsConfig.hide_priority && headerText === 'priority') {
					if (!matrix.some(row => row[i] && row[i].toLowerCase() !== 'low')) {
						visibilityPlan[i] = 'hide';
					}
				} else if (emptyColsConfig.enabled) {
					if (matrix.every(row => !row[i] || row[i] === '')) {
						visibilityPlan[i] = 'hide';
					}
				}
			});
		}

		if (conditionalConfig.enabled && conditionalConfig.rules?.length) {
			const currentViewId = document.querySelector('#wpsc-input-filter')?.value || '0';
			const pageView = currentViewId.replace('default-', '');
			const columnKeyMap = conditionalConfig.columns || {};
			const headerIndexMap = {};
			headers.forEach((th, index) => { headerIndexMap[th.textContent.trim().toLowerCase()] = index; });

			const isRuleActiveForView = (rule, viewId) => (rule.condition === 'in_view' && viewId === String(rule.view)) || (rule.condition === 'not_in_view' && viewId !== String(rule.view));

			conditionalConfig.rules.forEach(rule => {
				if (rule.action === 'show_only' && !isRuleActiveForView(rule, pageView)) {
					const columnIndex = headerIndexMap[columnKeyMap[rule.columns]?.toLowerCase()];
					if (columnIndex !== undefined) visibilityPlan[columnIndex] = 'hide';
				}
			});

			conditionalConfig.rules.forEach(rule => {
				if (isRuleActiveForView(rule, pageView)) {
					const columnIndex = headerIndexMap[columnKeyMap[rule.columns]?.toLowerCase()];
					if (columnIndex !== undefined) {
						visibilityPlan[columnIndex] = (rule.action === 'show' || rule.action === 'show_only') ? 'show' : 'hide';
					}
				}
			});
		}

		headers.forEach((th, i) => { th.style.display = (visibilityPlan[i] === 'hide') ? 'none' : ''; });
		rows.forEach(row => {
			Array.from(row.children).forEach((td, i) => { td.style.display = (visibilityPlan[i] === 'hide') ? 'none' : ''; });
		});
	}

	/**
	 * Feature: Ticket Details Card.
	 */
	function feature_ticket_details_card() {
		const cache = {};
		let activeTippyInstance = null; // Tracks the currently visible tippy instance

		const viewType = settings.ticket_details_view_type || 'standard';

		async function fetchTicketDetails(ticketId) {
			if (cache[ticketId]) return cache[ticketId];

			let detailsHtml = '';
			let extraContentHtml = '';
			let effectiveViewType = viewType;

			// 1. Fetch from Backend (UTM or Extra Content)
			try {
				const response = await fetch(settings.ajax_url, {
					method: 'POST',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
					body: new URLSearchParams({
						action: 'stackboost_get_ticket_details_card',
						nonce: settings.nonce,
						ticket_id: ticketId
					})
				});

				if (response.ok) {
					const json = await response.json();
					if (json.success) {
						// Check if backend forced a fallback
						if (json.data.effective_view_type) {
							effectiveViewType = json.data.effective_view_type;
						}

						if (effectiveViewType === 'utm') {
							// For UTM, the backend returns separated parts
							detailsHtml = json.data.details || '';
							extraContentHtml = json.data.history || '';
						} else {
							// For Standard, backend returns ONLY the extra content (Description/History)
							extraContentHtml = json.data.history || '';
						}
					}
				}
			} catch (e) {
				if (typeof sbUtilError === 'function') {
					sbUtilError('Error fetching ticket details from backend', e);
				}
			}

			// 2. Fetch/Scrape Standard Details (if needed)
			// Checks effectiveViewType to handle fallback from UTM -> Standard
			if (effectiveViewType === 'standard') {
				try {
					const response = await fetch(settings.ajax_url, {
						method: 'POST',
						headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
						body: new URLSearchParams({ action: 'wpsc_get_individual_ticket', nonce: settings.nonce, ticket_id: ticketId })
					});
					if (response.ok) {
						const html = await response.text();
						const doc = new DOMParser().parseFromString(html, 'text/html');
						const content = doc.querySelector('.wpsc-it-widget.wpsc-itw-ticket-fields')?.outerHTML || '<div>No details found.</div>';
						detailsHtml = content;
					}
				} catch (error) {
					detailsHtml = '<div>Error fetching ticket info.</div>';
				}
			}

			// Helper to check for meaningful content
			function hasMeaningfulContent(html) {
				if (!html) return false;
				const temp = document.createElement('div');
				temp.innerHTML = html;

				// 1. Try to find specific thread bodies first
				const threadBodies = temp.querySelectorAll('.stackboost-thread-body');
				if (threadBodies.length > 0) {
					// Iterate through all threads. If ANY thread has meaningful content, return true.
					for (let i = 0; i < threadBodies.length; i++) {
						const text = threadBodies[i].textContent.trim().toLowerCase();
						if (text.length > 0 && text !== 'not applicable' && text !== 'n/a') {
							return true;
						}
					}
					// If we looped through all threads and found nothing meaningful, return false.
					return false;
				}

				// 2. Fallback: Check inside the widget body if it exists
				const widgetBody = temp.querySelector('.wpsc-widget-body');
				const target = widgetBody ? widgetBody : temp;
				const text = target.textContent.trim().toLowerCase();
				return text.length > 0 && text !== 'not applicable' && text !== 'n/a';
			}

			// Clean up extra content if it's "Not Applicable"
			if (!hasMeaningfulContent(extraContentHtml)) {
				extraContentHtml = '';
			}

			// Combine
			// We check the content size logic later in onShow
			const finalHtml = `<div class="stackboost-ticket-card-container" style="width: 350px; min-width: 350px !important;">
				<div class="stackboost-card-section stackboost-card-details">${detailsHtml}</div>
				<div class="stackboost-card-section stackboost-card-history">${extraContentHtml}</div>
			</div>`;

			return (cache[ticketId] = finalHtml);
		}

		// Public accessor to get active instance (for toggle updates)
		window.stackboostActiveTippy = function() { return activeTippyInstance; };

		document.querySelectorAll('tr.wpsc_tl_tr:not(._contextAttached)').forEach(row => {
			row.classList.add('_contextAttached');

			const ticketId = row.getAttribute('onclick')?.match(/wpsc_tl_handle_click\(.*?,\s*(\d+),/)?.[1];
			if (!ticketId) return;

			const tippyInstance = tippy(row, {
				allowHTML: true,
				interactive: true,
				trigger: 'manual',
				placement: 'right-start',
				maxWidth: 'none', // Allow our fixed width to take precedence
				offset: [0, 10],
				appendTo: () => document.body,
				hideOnClick: false, // We handle outside clicks manually to safely ignore the lightbox
				onClickOutside(instance, event) {
					// Prevent closing if clicking inside our lightbox
					// We check for the lightbox ID, the close button ID, or the class hierarchy
					if (
						event.target.id === 'stackboost-widget-modal' ||
						event.target.id === 'stackboost-widget-modal-close' ||
						event.target.closest('#stackboost-widget-modal')
					) {
						return;
					}
					instance.hide();
				},
				popperOptions: {
					modifiers: [
						{
							name: 'flip',
							options: {
								fallbackPlacements: ['left-start', 'top-start', 'bottom-start'],
								altBoundary: true, // Force flip to check alternative boundaries
							},
						},
						{
							name: 'preventOverflow',
							options: {
								tether: false, // Prevent squashing/sticking to reference
								altAxis: true, // Allow shifting on the cross axis
							},
						},
					],
				},
				async onShow(instance) {
					// Ensure only one tippy is visible at a time
					if (activeTippyInstance && activeTippyInstance.id !== instance.id) {
						activeTippyInstance.hide();
					}
					activeTippyInstance = instance;

					instance.setContent('Loading...');
					const content = await fetchTicketDetails(ticketId);
					instance.setContent(content);

					// Smart Layout Logic
					// We need to wait for render or force a check
					requestAnimationFrame(() => {
						const popper = instance.popper;
						if (!popper) return;

						const windowHeight = window.innerHeight;
						const contentHeight = popper.getBoundingClientRect().height;
						// Increase threshold to 85% to be less aggressive for standard content
						const threshold = windowHeight * 0.85;

						const $container = $(instance.popper).find('.stackboost-ticket-card-container');
					const $historySection = $container.find('.stackboost-card-history');

					// If history is empty, hide the container to prevent empty spacing
					if ($historySection.html().trim() === '') {
						$historySection.hide();
					}

						const hasDetails = $container.find('.stackboost-card-details').html().trim().length > 10;
					const hasHistory = $historySection.is(':visible') && $historySection.html().trim().length > 10;

						// Only switch to horizontal if we have both sections AND it's too tall
						if (hasDetails && hasHistory && contentHeight > threshold) {
							// Too tall! Switch to horizontal layout
							$container.addClass('stackboost-layout-horizontal');
							// Increase width to accommodate two columns
							$container.css({
								'width': '720px',
								'min-width': '720px',
								'display': 'flex',
								'gap': '15px',
								'align-items': 'flex-start'
							});
							$container.find('.stackboost-card-section').css({
								'flex': '1',
								'width': '50%'
							});

						// Remove margins from inner widgets for cleaner layout
						$container.find('.wpsc-it-widget, .stackboost-dashboard').css('margin', '0');

							// Force Tippy to update position with new dimensions
							instance.setProps({ maxWidth: 'none' }); // Ensure no constraint
						}
					});
				},
				onHide(instance) {
					// Clear the active instance when it's hidden
					if (activeTippyInstance && activeTippyInstance.id === instance.id) {
						activeTippyInstance = null;
					}
				}
			});

			// Add mousedown listener to prevent text selection on Shift+RightClick
			row.addEventListener('mousedown', (e) => {
				// If Shift is pressed and it's a right-click (button 2)
				if (e.shiftKey && e.button === 2) {
					// Prevent the default browser behavior (which triggers text selection)
					e.preventDefault();
				}
			});

			// Close the card when clicking the row (which opens the ticket)
			row.addEventListener('click', () => {
				tippyInstance.hide();
			});

			row.addEventListener('contextmenu', (e) => {
				// Allow native context menu if Shift key is pressed
				if (e.shiftKey) {
					return;
				}

				e.preventDefault();

				// Set the reference to a virtual element at the cursor position
				// This makes the tippy appear at the cursor, but stay static afterwards.
				const rect = {
					width: 0,
					height: 0,
					top: e.clientY,
					right: e.clientX,
					bottom: e.clientY,
					left: e.clientX,
				};

				tippyInstance.setProps({
					getReferenceClientRect: () => rect,
					placement: 'right-start', // Initial preference
				});

				tippyInstance.show();
			});
		});
	}

	/**
	 * Feature: Hide Ticket Types from Non-Agents.
	 */
	function feature_hide_ticket_types_for_non_agents() {
		const isAgent = document.querySelector('.wpsc-menu-list.agent-profile, #menu-item-8128');
		const fieldId = features.ticket_type_hiding.field_id;
		const typesToHide = features.ticket_type_hiding.types_to_hide;

		if (!isAgent && fieldId && typesToHide.length) {
			const select = document.querySelector(`select[name="cust_${fieldId}"]`);
			if (select && $.fn.select2) {
				const $select = $(select);
				let changesMade = false;
				$select.find('option').each(function () {
					if (typesToHide.includes($(this).text().trim())) {
						$(this).remove();
						changesMade = true;
					}
				});
				if (changesMade) $select.trigger('change.select2');
			}
		}
	}

	$(document).ready(init);

	// Event Delegation for Collapsible Widgets (Injected via Tippy or Standard)
	$(document).on('click', '.wpsc-itw-toggle', function() {
		const $toggle = $(this);
		const $widget = $toggle.closest('.wpsc-it-widget');
		const $body = $widget.find('.wpsc-widget-body');

		// Check if this is one of our managed widgets or standard scraped content
		// We use slideToggle for animation
		$body.slideToggle(200, function() {
			// Update icon after animation
			if ($body.is(':visible')) {
				$toggle.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
			} else {
				$toggle.removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
			}

			// If inside a Tippy, force an update to resize/reposition
			if (typeof window.stackboostActiveTippy === 'function') {
				const activeInstance = window.stackboostActiveTippy();
				if (activeInstance && activeInstance.popperInstance) {
					activeInstance.popperInstance.update();
				} else if (activeInstance && activeInstance.popper) {
					// Fallback for older tippy versions
					activeInstance.popper.update();
				}
			}
		});
	});

})(jQuery);