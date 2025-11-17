# Developer Guide: Refactoring the UTM for Existing Tickets

## 1. Project Goal

The primary objective of this task is to refactor the Unified Ticket Macro (UTM) module to ensure it correctly generates and displays for **existing tickets**, not just newly created ones. The current implementation fails for existing tickets because it attempts to save to the database in the middle of an email-sending process, which is unstable.

The solution is to adapt the existing, proven "transient-first with shutdown hook" pattern that is already used for new tickets.

## 2. Technical Analysis & Research Findings

Our research has identified the critical flaw in the current "just-in-time" logic for existing tickets:

*   **The Problem:** When `replace_utm_macro` is called for a ticket with an empty cache, it generates the HTML and then immediately calls `update_utm_cache()`.
*   **The Flaw:** The `update_utm_cache()` function contains a `$ticket->save()` call. Executing a `save()` operation within a WordPress filter that is actively processing email data can lead to race conditions, fatal errors, or other unpredictable behavior, causing the macro replacement to fail.
*   **The Evidence:** The fact that the macro works perfectly for *new tickets* proves that the existing architecture for handling them is correct. That architecture deliberately avoids calling `save()` directly. It uses a short-lived transient to hold the data and defers the permanent save to the `shutdown` hook, which runs safely after all other processes are complete.

## 3. Step-by-Step Implementation Plan

All changes for this task will be made in the following file:
*   `stackboost-for-supportcandy/src/Modules/UnifiedTicketMacro/Core.php`

### 3.1. Modify the `replace_utm_macro` Function

The logic must be updated to handle the "empty cache" scenario for existing tickets by mirroring the new-ticket process.

**Locate this block of code:**
```php
			} else {
				\stackboost_log( '[UTM] replace_utm_macro() - WARNING: No cache found. Generating on-the-fly for ticket ID: ' . $ticket->id, 'module-utm' );
				// Generate the HTML just-in-time for this email.
				$cached_html = $this->build_live_utm_html( $ticket );
				// Proactively save the newly generated cache for future requests.
				$this->update_utm_cache( $ticket );
			}
```

**Replace it with the following logic:**
```php
			} else {
				\stackboost_log( '[UTM] replace_utm_macro() - WARNING: No permanent cache found. Generating on-the-fly for ticket ID: ' . $ticket->id, 'module-utm' );

				// 1. Generate the HTML on-the-fly for immediate use in the email.
				$cached_html = $this->build_live_utm_html( $ticket );

				// 2. Store the generated HTML in a short-lived transient. This makes it available for the deferred save.
				set_transient( 'stackboost_utm_temp_cache_' . $ticket->id, $cached_html, 60 );
				\stackboost_log( '[UTM] replace_utm_macro() - Just-in-time transient cache set for ticket ID: ' . $ticket->id, 'module-utm' );

				// 3. Register the deferred save action to safely write to the database after the request is finished.
				// This reuses the exact same safe mechanism that new tickets use.
				if ( ! has_action( 'shutdown', array( $this, 'deferred_save' ) ) ) {
					add_action( 'shutdown', array( $this, 'deferred_save' ) );
					\stackboost_log( '[UTM] replace_utm_macro() - Shutdown action registered for just-in-time save.', 'module-utm' );
				}

				// 4. Assign the ticket object to the class property so the shutdown hook knows which ticket to save.
				$this->deferred_ticket_to_save = $ticket;
			}
```

**Important:** The `if ( ! has_action( ... ) )` check is a defensive measure to prevent the shutdown hook from being registered more than once per request.

## 4. Logging Guidance

All new diagnostic logs added during this task must use the central `\stackboost_log()` function.

*   **Function:** `\stackboost_log( $message, $context )`
*   **Context:** For all logs related to this module, please use the context `'module-utm'`.

This will ensure all diagnostic output is consistent and can be easily filtered from the central log file.
