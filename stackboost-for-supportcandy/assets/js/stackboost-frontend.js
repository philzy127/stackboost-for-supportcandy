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
		let floatingCard = document.getElementById('floatingTicketCard');
		if (!floatingCard) {
			floatingCard = document.createElement('div');
			floatingCard.id = 'floatingTicketCard';
			Object.assign(floatingCard.style, { position: 'absolute', zIndex: '9999', background: '#fff', border: '1px solid #ccc', padding: '10px', boxShadow: '0 4px 12px rgba(0, 0, 0, 0.2)', maxWidth: '400px', display: 'none' });
			const contentContainer = document.createElement('div');
			contentContainer.className = 'stackboost-card-content';
			const closeButton = document.createElement('span');
			closeButton.innerHTML = '&times;';
			Object.assign(closeButton.style, { position: 'absolute', top: '3px', right: '5px', cursor: 'pointer', fontSize: '20px', color: '#333', background: '#f1f1f1', borderRadius: '50%', width: '24px', height: '24px', lineHeight: '24px', textAlign: 'center', fontWeight: 'bold' });
			closeButton.addEventListener('click', () => { floatingCard.style.display = 'none'; });
			floatingCard.appendChild(closeButton);
			floatingCard.appendChild(contentContainer);
			document.body.appendChild(floatingCard);
		}

		const contentContainer = floatingCard.querySelector('.stackboost-card-content');
		const cache = {};
		let lastEventCoords = { clientX: 0, clientY: 0, pageX: 0, pageY: 0 };

		function updatePosition() {
			if (!floatingCard || floatingCard.style.display === 'none') return;

			const cardRect = floatingCard.getBoundingClientRect();
			const viewportWidth = window.innerWidth;
			const viewportHeight = window.innerHeight;
			const offset = 15;

			let top = lastEventCoords.pageY + offset;
			let left = lastEventCoords.pageX + offset;

			// Horizontal Flip
			if (lastEventCoords.clientX + offset + cardRect.width > viewportWidth) {
				left = lastEventCoords.pageX - offset - cardRect.width;
			}

			// Vertical Flip
			if (lastEventCoords.clientY + offset + cardRect.height > viewportHeight) {
				top = lastEventCoords.pageY - offset - cardRect.height;
			}

			// Safety: Ensure we don't flip off the top/left edge of the document
			if (left < 0) left = 0;
			if (top < 0) top = 0;

			floatingCard.style.top = `${top}px`;
			floatingCard.style.left = `${left}px`;
		}

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
				return (cache[ticketId] = doc.querySelector('.wpsc-it-widget.wpsc-itw-ticket-fields')?.outerHTML || '<div>No details found.</div>');
			} catch (error) {
				return '<div>Error fetching ticket info.</div>';
			}
		}

		document.addEventListener('click', (e) => {
			if (floatingCard && !floatingCard.contains(e.target)) floatingCard.style.display = 'none';
		});

		document.querySelectorAll('tr.wpsc_tl_tr:not(._contextAttached)').forEach(row => {
			row.classList.add('_contextAttached');
			row.addEventListener('contextmenu', async (e) => {
				e.preventDefault();
				const ticketId = row.getAttribute('onclick')?.match(/wpsc_tl_handle_click\(.*?,\s*(\d+),/)?.[1];
				if (ticketId) {
					lastEventCoords = { clientX: e.clientX, clientY: e.clientY, pageX: e.pageX, pageY: e.pageY };
					contentContainer.innerHTML = 'Loading...';
					floatingCard.style.display = 'block';
					updatePosition();
					contentContainer.innerHTML = await fetchTicketDetails(ticketId);
					updatePosition();
				}
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