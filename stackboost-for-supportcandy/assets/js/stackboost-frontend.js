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
		if (features.hover_card?.enabled) {
			feature_ticket_hover_card();
		}

		const emptyColsConfig = features.hide_empty_columns || {};
		const conditionalConfig = features.conditional_hiding || {};
		if (emptyColsConfig.enabled || emptyColsConfig.hide_priority || conditionalConfig.enabled) {
			feature_manage_column_visibility();
		}

		if (features.ticket_type_hiding?.enabled) {
			feature_hide_ticket_types_for_non_agents();
		}
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
	 * Feature: Ticket Hover Card.
	 */
	function feature_ticket_hover_card() {
		const cache = {};
		let activeTippyInstance = null; // Tracks the currently visible tippy instance

		async function fetchTicketDetails(ticketId) {
			if (cache[ticketId]) return cache[ticketId];
			try {
				const response = await fetch(settings.ajax_url, {
					method: 'POST',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
					body: new URLSearchParams({ action: 'wpsc_get_individual_ticket', nonce: settings.nonce, ticket_id: ticketId })
				});
				if (!response.ok) return '<div>Error fetching ticket info.</div>';
				const html = await response.text();
				const doc = new DOMParser().parseFromString(html, 'text/html');
				// Wrap in a fixed-width container with min-width to prevent resizing/squashing
				const content = doc.querySelector('.wpsc-it-widget.wpsc-itw-ticket-fields')?.outerHTML || '<div>No details found.</div>';
				return (cache[ticketId] = `<div style="width: 350px; min-width: 350px !important;">${content}</div>`);
			} catch (error) {
				return '<div style="width: 350px; min-width: 350px !important;">Error fetching ticket info.</div>';
			}
		}

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
				hideOnClick: true, // Use tippy's built-in behavior to hide on outside clicks
				popperOptions: {
					modifiers: [
						{
							name: 'flip',
							options: {
								fallbackPlacements: ['left-start', 'top-start', 'bottom-start'],
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
				},
				onHide(instance) {
					// Clear the active instance when it's hidden
					if (activeTippyInstance && activeTippyInstance.id === instance.id) {
						activeTippyInstance = null;
					}
				}
			});

			row.addEventListener('contextmenu', (e) => {
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

})(jQuery);