# Developer Guide: Implementing the UTM Ticket Lifecycle Logging System

## 1. Project Goal

The primary objective of this project is to implement a comprehensive logging system within the Unified Ticket Macro (UTM) module. This system must capture all data passed by key SupportCandy action hooks throughout the ticket lifecycle, from creation to deletion. The goal is to provide developers with a clear and complete data trail for diagnostic and debugging purposes.

## 2. File Manifest

This implementation exclusively modifies the following file:

*   `stackboost-for-supportcandy/src/Modules/UnifiedTicketMacro/WordPress.php`

All new code is contained within this file.

## 3. Step-by-Step Implementation

The implementation consists of two parts: registering the action hooks and creating the callback functions that handle the logging.

### 3.1. Hook Registration

The following block of code must be added to the `__construct` method of the `WordPress` class in the file listed above. This registers our custom logging functions to execute when SupportCandy fires its core lifecycle events.

**Code for Hook Registration:**
```php
// Comprehensive logging hooks.
add_action( 'wpsc_create_new_ticket', array( $this, 'log_wpsc_create_new_ticket' ), 10, 1 );
add_action( 'wpsc_post_reply', array( $this, 'log_wpsc_post_reply' ), 10, 1 );
add_action( 'wpsc_submit_note', array( $this, 'log_wpsc_submit_note' ), 10, 1 );
add_action( 'wpsc_change_assignee', array( $this, 'log_wpsc_change_assignee' ), 10, 4 );
add_action( 'wpsc_change_ticket_status', array( $this, 'log_wpsc_change_ticket_status' ), 10, 4 );
add_action( 'wpsc_change_ticket_priority', array( $this, 'log_wpsc_change_ticket_priority' ), 10, 4 );
add_action( 'wpsc_delete_ticket', array( $this, 'log_wpsc_delete_ticket' ), 10, 1 );
```

### 3.2. Callback Implementation

The following public methods must be added to the `WordPress` class in the same file. Each function corresponds to one of the hooks registered above. Their sole purpose is to capture all arguments passed by the hook and send them to the global `stackboost_log()` function.

**Critical Note on `log_wpsc_create_new_ticket`**: This callback is wrapped in a `try...catch` block. This is a defensive measure to ensure that any unexpected error during the logging of a new ticket (e.g., an incompletely formed ticket object) does not crash the ticket creation process itself.

**Code for Callback Functions:**
```php
/**
 * Wrapper functions to call the central logger.
 */
public function log_wpsc_create_new_ticket( $ticket ) {
    try {
        if ( ! is_a( $ticket, 'WPSC_Ticket' ) ) {
            \stackboost_log(
                array(
                    'message' => '[UTM HOOK WARNING] wpsc_create_new_ticket: $ticket is not a WPSC_Ticket object.',
                    'ticket'  => $ticket,
                )
            );
            return;
        }
        \stackboost_log(
            array(
                'message' => '[UTM HOOK FIRED] wpsc_create_new_ticket',
                'ticket'  => $ticket,
            )
        );
    } catch ( \Throwable $e ) {
        \stackboost_log(
            array(
                'message'   => '[UTM HOOK FATAL ERROR] wpsc_create_new_ticket: An error occurred.',
                'error'     => $e->getMessage(),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
            )
        );
    }
}

public function log_wpsc_post_reply( $thread ) {
    \stackboost_log(
        array(
            'message' => '[UTM HOOK FIRED] wpsc_post_reply',
            'thread'  => $thread,
            'ticket'  => $thread->ticket,
        )
    );
}

public function log_wpsc_submit_note( $thread ) {
    \stackboost_log(
        array(
            'message' => '[UTM HOOK FIRED] wpsc_submit_note',
            'thread'  => $thread,
            'ticket'  => $thread->ticket,
        )
    );
}

public function log_wpsc_change_assignee( $ticket, $prev, $new, $customer_id ) {
    \stackboost_log(
        array(
            'message'     => '[UTM HOOK FIRED] wpsc_change_assignee',
            'ticket'      => $ticket,
            'prev'        => $prev,
            'new'         => $new,
            'customer_id' => $customer_id,
        )
    );
}

public function log_wpsc_change_ticket_status( $ticket, $prev, $new, $customer_id ) {
    \stackboost_log(
        array(
            'message'     => '[UTM HOOK FIRED] wpsc_change_ticket_status',
            'ticket'      => $ticket,
            'prev'        => $prev,
            'new'         => $new,
            'customer_id' => $customer_id,
        )
    );
}

public function log_wpsc_change_ticket_priority( $ticket, $prev, $new, $customer_id ) {
    \stackboost_log(
        array(
            'message'     => '[UTM HOOK FIRED] wpsc_change_ticket_priority',
            'ticket'      => $ticket,
            'prev'        => $prev,
            'new'         => $new,
            'customer_id' => $customer_id,
        )
    );
}

public function log_wpsc_delete_ticket( $ticket ) {
    \stackboost_log(
        array(
            'message' => '[UTM HOOK FIRED] wpsc_delete_ticket',
            'ticket'  => $ticket,
        )
    );
}
```

## 4. Expected Output & Example Log Trace

Once implemented, performing actions on a SupportCandy ticket will produce detailed logs in `stackboost-for-supportcandy/logs/debug.log`. Each log entry will be a serialized PHP array containing a descriptive message and the full data objects passed by the corresponding hook.

Below is a full lifecycle log trace from a test ticket, showing the output from ticket creation, status changes, user/agent replies, agent assignment, priority change, and final deletion.

```
[2025-11-16 15:15:49 EST] Array
(
    [message] => [UTM HOOK FIRED] wpsc_create_new_ticket
    [ticket] => WPSC_Ticket Object
        (
            [data:WPSC_Ticket:private] => Array
                (
                    [id] => 42
                    [is_active] => 1
                    [customer] => 18
                    [subject] => Stackboost Helpdesk Ticket
                    [status] => 1
                    [priority] => 1
                    [category] => 1
                    [assigned_agent] =>
                    [date_created] => 2025-11-16 20:15:49
                    [date_updated] => 2025-11-16 20:15:49
                    [agent_created] => 0
                    [ip_address] => 100.40.206.59
                    [source] => browser
                    [browser] => Google Chrome
                    [os] => Windows 10
                    [add_recipients] =>
                    [prev_assignee] =>
                    [date_closed] =>
                    [user_type] => registered
                    [last_reply_on] => 2025-11-16 20:15:49
                    [last_reply_by] => 18
                    [last_reply_source] => browser
                    [auth_code] => yMSWcvzw
                    [tags] =>
                    [live_agents] =>
                    [misc] =>
                    [cust_26] => 2
                    [cust_27] => 10
                    [cust_28] =>
                    [cust_32] =>
                    [cust_36] =>
                    [cust_37] =>
                    [cust_38] =>
                    [cust_39] => {{current_user_email}}
                    [cust_40] =>
                )

            [is_modified:WPSC_Ticket:private] =>
        )

)

[2025-11-16 15:16:33 EST] Array
(
    [message] => [UTM HOOK FIRED] wpsc_change_ticket_status
    [ticket] => WPSC_Ticket Object
        (
            [data:WPSC_Ticket:private] => Array
                (
                    [id] => 42
                    [is_active] => 1
                    [customer] => 18
                    [subject] => Stackboost Helpdesk Ticket
                    [status] => 3
                    [priority] => 1
                    [category] => 1
                    [assigned_agent] =>
                    [date_created] => 2025-11-16 20:15:49
                    [date_updated] => 2025-11-16 20:16:33
                    [agent_created] => 0
                    [ip_address] => 100.40.206.59
                    [source] => browser
                    [browser] => Google Chrome
                    [os] => Windows 10
                    [add_recipients] =>
                    [prev_assignee] =>
                    [date_closed] => 0000-00-00 00:00:00
                    [user_type] => registered
                    [last_reply_on] => 2025-11-16 20:16:33
                    [last_reply_by] => 18
                    [last_reply_source] => browser
                    [auth_code] => yMSWcvzw
                    [tags] =>
                    [live_agents] =>
                    [misc] =>
                    [cust_26] => 2
                    [cust_27] => 10
                    [cust_28] =>
                    [cust_32] =>
                    [cust_36] =>
                    [cust_37] =>
                    [cust_38] =>
                    [cust_39] => {{current_user_email}}
                    [cust_40] =>
                )

            [is_modified:WPSC_Ticket:private] =>
        )

    [prev] => 1
    [new] => 3
    [customer_id] => 0
)

[2025-11-16 15:16:33 EST] Array
(
    [message] => [UTM HOOK FIRED] wpsc_post_reply
    [thread] => WPSC_Thread Object
        (
            [data:WPSC_Thread:private] => Array
                (
                    [id] => 114
                    [ticket] => 42
                    [is_active] => 1
                    [customer] => 18
                    [type] => reply
                    [body] => <p>Replying as a user</p>
                    [attachments] =>
                    [ip_address] => 100.40.206.59
                    [source] => browser
                    [os] => Windows 10
                    [browser] => Google Chrome
                    [seen] =>
                    [date_created] => 2025-11-16 20:16:33
                    [date_updated] => 2025-11-16 20:16:33
                )

            [is_modified:WPSC_Thread:private] =>
        )

    [ticket] => WPSC_Ticket Object
        (
            [data:WPSC_Ticket:private] => Array
                (
                    [id] => 42
                    [is_active] => 1
                    [customer] => 18
                    [subject] => Stackboost Helpdesk Ticket
                    [status] => 3
                    [priority] => 1
                    [category] => 1
                    [assigned_agent] =>
                    [date_created] => 2025-11-16 20:15:49
                    [date_updated] => 2025-11-16 20:16:33
                    [agent_created] => 0
                    [ip_address] => 100.40.206.59
                    [source] => browser
                    [browser] => Google Chrome
                    [os] => Windows 10
                    [add_recipients] =>
                    [prev_assignee] =>
                    [date_closed] => 0000-00-00 00:00:00
                    [user_type] => registered
                    [last_reply_on] => 2025-11-16 20:16:33
                    [last_reply_by] => 18
                    [last_reply_source] => browser
                    [auth_code] => yMSWcvzw
                    [tags] =>
                    [live_agents] =>
                    [misc] =>
                    [cust_26] => 2
                    [cust_27] => 10
                    [cust_28] =>
                    [cust_32] =>
                    [cust_36] =>
                    [cust_37] =>
                    [cust_38] =>
                    [cust_39] => {{current_user_email}}
                    [cust_40] =>
                )

            [is_modified:WPSC_Ticket:private] =>
        )

)

[2025-11-16 15:17:04 EST] Array
(
    [message] => [UTM HOOK FIRED] wpsc_change_ticket_status
    [ticket] => WPSC_Ticket Object
        (
            [data:WPSC_Ticket:private] => Array
                (
                    [id] => 42
                    [is_active] => 1
                    [customer] => 18
                    [subject] => Stackboost Helpdesk Ticket
                    [status] => 2
                    [priority] => 1
                    [category] => 1
                    [assigned_agent] =>
                    [date_created] => 2025-11-16 20:15:49
                    [date_updated] => 2025-11-16 20:17:04
                    [agent_created] => 0
                    [ip_address] => 100.40.206.59
                    [source] => browser
                    [browser] => Google Chrome
                    [os] => Windows 10
                    [add_recipients] =>
                    [prev_assignee] =>
                    [date_closed] => 0000-00-00 00:00:00
                    [user_type] => registered
                    [last_reply_on] => 2025-11-16 20:17:04
                    [last_reply_by] => 17
                    [last_reply_source] => browser
                    [auth_code] => yMSWcvzw
                    [tags] =>
                    [live_agents] => {"7":"2025-11-16 20:16:53"}
                    [misc] =>
                    [cust_26] => 2
                    [cust_27] => 10
                    [cust_28] =>
                    [cust_32] =>
                    [cust_36] =>
                    [cust_37] =>
                    [cust_38] =>
                    [cust_39] => {{current_user_email}}
                    [cust_40] =>
                )

            [is_modified:WPSC_Ticket:private] =>
        )

    [prev] => 3
    [new] => 2
    [customer_id] => 0
)

[2025-11-16 15:17:04 EST] Array
(
    [message] => [UTM HOOK FIRED] wpsc_post_reply
    [thread] => WPSC_Thread Object
        (
            [data:WPSC_Thread:private] => Array
                (
                    [id] => 116
                    [ticket] => 42
                    [is_active] => 1
                    [customer] => 17
                    [type] => reply
                    [body] => <p>Replying as an Agent</p>
                    [attachments] =>
                    [ip_address] => 100.40.206.59
                    [source] => browser
                    [os] => Windows 10
                    [browser] => Google Chrome
                    [seen] =>
                    [date_created] => 2025-11-16 20:17:04
                    [date_updated] => 2025-11-16 20:17:04
                )

            [is_modified:WPSC_Thread:private] =>
        )

    [ticket] => WPSC_Ticket Object
        (
            [data:WPSC_Ticket:private] => Array
                (
                    [id] => 42
                    [is_active] => 1
                    [customer] => 18
                    [subject] => Stackboost Helpdesk Ticket
                    [status] => 2
                    [priority] => 1
                    [category] => 1
                    [assigned_agent] =>
                    [date_created] => 2025-11-16 20:15:49
                    [date_updated] => 2025-11-16 20:17:04
                    [agent_created] => 0
                    [ip_address] => 100.40.206.59
                    [source] => browser
                    [browser] => Google Chrome
                    [os] => Windows 10
                    [add_recipients] =>
                    [prev_assignee] =>
                    [date_closed] => 0000-00-00 00:00:00
                    [user_type] => registered
                    [last_reply_on] => 2025-11-16 20:17:04
                    [last_reply_by] => 17
                    [last_reply_source] => browser
                    [auth_code] => yMSWcvzw
                    [tags] =>
                    [live_agents] => {"7":"2025-11-16 20:16:53"}
                    [misc] =>
                    [cust_26] => 2
                    [cust_27] => 10
                    [cust_28] =>
                    [cust_32] =>
                    [cust_36] =>
                    [cust_37] =>
                    [cust_38] =>
                    [cust_39] => {{current_user_email}}
                    [cust_40] =>
                )

            [is_modified:WPSC_Ticket:private] =>
        )

)

[2025-11-16 15:17:51 EST] Array
(
    [message] => [UTM HOOK FIRED] wpsc_change_assignee
    [ticket] => WPSC_Ticket Object
        (
            [data:WPSC_Ticket:private] => Array
                (
                    [id] => 42
                    [is_active] => 1
                    [customer] => 18
                    [subject] => Stackboost Helpdesk Ticket
                    [status] => 2
                    [priority] => 1
                    [category] => 1
                    [assigned_agent] => 7
                    [date_created] => 2025-11-16 20:15:49
                    [date_updated] => 2025-11-16 20:17:04
                    [agent_created] => 0
                    [ip_address] => 100.40.206.59
                    [source] => browser
                    [browser] => Google Chrome
                    [os] => Windows 10
                    [add_recipients] =>
                    [prev_assignee] =>
                    [date_closed] => 0000-00-00 00:00:00
                    [user_type] => registered
                    [last_reply_on] => 2025-11-16 20:17:04
                    [last_reply_by] => 17
                    [last_reply_source] => browser
                    [auth_code] => yMSWcvzw
                    [tags] =>
                    [live_agents] => {"7":"2025-11-16 20:17:05"}
                    [misc] =>
                    [cust_26] => 2
                    [cust_27] => 10
                    [cust_28] =>
                    [cust_32] =>
                    [cust_36] =>
                    [cust_37] =>
                    [cust_38] =>
                    [cust_39] => {{current_user_email}}
                    [cust_40] =>
                )

            [is_modified:WPSC_Ticket:private] =>
        )

    [prev] => Array
        (
        )

    [new] => Array
        (
            [0] => WPSC_Agent Object
                (
                    [data:WPSC_Agent:private] => Array
                        (
                            [id] => 7
                            [user] => 17
                            [customer] => 17
                            [role] => 1
                            [name] => Philip E
                            [workload] => 0
                            [unresolved_count] => 38
                            [is_agentgroup] => 0
                            [is_active] => 1
                        )

                    [is_modified:WPSC_Agent:private] =>
                )

        )

    [customer_id] => 17
)

[2025-11-16 15:18:04 EST] Array
(
    [message] => [UTM HOOK FIRED] wpsc_change_ticket_priority
    [ticket] => WPSC_Ticket Object
        (
            [data:WPSC_Ticket:private] => Array
                (
                    [id] => 42
                    [is_active] => 1
                    [customer] => 18
                    [subject] => Stackboost Helpdesk Ticket
                    [status] => 2
                    [priority] => 3
                    [category] => 1
                    [assigned_agent] => 7
                    [date_created] => 2025-11-16 20:15:49
                    [date_updated] => 2025-11-16 20:18:04
                    [agent_created] => 0
                    [ip_address] => 100.40.206.59
                    [source] => browser
                    [browser] => Google Chrome
                    [os] => Windows 10
                    [add_recipients] =>
                    [prev_assignee] =>
                    [date_closed] => 0000-00-00 00:00:00
                    [user_type] => registered
                    [last_reply_on] => 2025-11-16 20:17:04
                    [last_reply_by] => 17
                    [last_reply_source] => browser
                    [auth_code] => yMSWcvzw
                    [tags] =>
                    [live_agents] => {"7":"2025-11-16 20:17:53"}
                    [misc] =>
                    [cust_26] => 2
                    [cust_27] => 10
                    [cust_28] =>
                    [cust_32] =>
                    [cust_36] =>
                    [cust_37] =>
                    [cust_38] =>
                    [cust_39] => {{current_user_email}}
                    [cust_40] =>
                )

            [is_modified:WPSC_Ticket:private] =>
        )

    [prev] => 1
    [new] => 3
    [customer_id] => 17
)

[2025-11-16 15:18:25 EST] Array
(
    [message] => [UTM HOOK FIRED] wpsc_post_reply
    [thread] => WPSC_Thread Object
        (
            [data:WPSC_Thread:private] => Array
                (
                    [id] => 120
                    [ticket] => 42
                    [is_active] => 1
                    [customer] => 17
                    [type] => reply
                    [body] => <p>Reply &amp; Close Test</p>
                    [attachments] =>
                    [ip_address] => 100.40.206.59
                    [source] => browser
                    [os] => Windows 10
                    [browser] => Google Chrome
                    [seen] =>
                    [date_created] => 2025-11-16 20:18:25
                    [date_updated] => 2025-11-16 20:18:25
                )

            [is_modified:WPSC_Thread:private] =>
        )

    [ticket] => WPSC_Ticket Object
        (
            [data:WPSC_Ticket:private] => Array
                (
                    [id] => 42
                    [is_active] => 1
                    [customer] => 18
                    [subject] => Stackboost Helpdesk Ticket
                    [status] => 4
                    [priority] => 3
                    [category] => 1
                    [assigned_agent] => 7
                    [date_created] => 2025-11-16 20:15:49
                    [date_updated] => 2025-11-16 20:18:25
                    [agent_created] => 0
                    [ip_address] => 100.40.206.59
                    [source] => browser
                    [browser] => Google Chrome
                    [os] => Windows 10
                    [add_recipients] =>
                    [prev_assignee] =>
                    [date_closed] => 2025-11-16 20:18:25
                    [user_type] => registered
                    [last_reply_on] => 2025-11-16 20:18:25
                    [last_reply_by] => 17
                    [last_reply_source] => browser
                    [auth_code] => yMSWcvzw
                    [tags] =>
                    [live_agents] => {"7":"2025-11-16 20:18:05"}
                    [misc] =>
                    [cust_26] => 2
                    [cust_27] => 10
                    [cust_28] =>
                    [cust_32] =>
                    [cust_36] =>
                    [cust_37] =>
                    [cust_38] =>
                    [cust_39] => {{current_user_email}}
                    [cust_40] =>
                )

            [is_modified:WPSC_Ticket:private] =>
        )

)

[2025-11-16 15:18:25 EST] Array
(
    [message] => [UTM HOOK FIRED] wpsc_change_ticket_status
    [ticket] => WPSC_Ticket Object
        (
            [data:WPSC_Ticket:private] => Array
                (
                    [id] => 42
                    [is_active] => 1
                    [customer] => 18
                    [subject] => Stackboost Helpdesk Ticket
                    [status] => 4
                    [priority] => 3
                    [category] => 1
                    [assigned_agent] => 7
                    [date_created] => 2025-11-16 20:15:49
                    [date_updated] => 2025-11-16 20:18:25
                    [agent_created] => 0
                    [ip_address] => 100.40.206.59
                    [source] => browser
                    [browser] => Google Chrome
                    [os] => Windows 10
                    [add_recipients] =>
                    [prev_assignee] =>
                    [date_closed] => 2025-11-16 20:18:25
                    [user_type] => registered
                    [last_reply_on] => 2025-11-16 20:18:25
                    [last_reply_by] => 17
                    [last_reply_source] => browser
                    [auth_code] => yMSWcvzw
                    [tags] =>
                    [live_agents] => {"7":"2025-11-16 20:18:05"}
                    [misc] =>
                    [cust_26] => 2
                    [cust_27] => 10
                    [cust_28] =>
                    [cust_32] =>
                    [cust_36] =>
                    [cust_37] =>
                    [cust_38] =>
                    [cust_39] => {{current_user_email}}
                    [cust_40] =>
                )

            [is_modified:WPSC_Ticket:private] =>
        )

    [prev] => 2
    [new] => 4
    [customer_id] => 17
)

[2025-11-16 15:18:30 EST] Array
(
    [message] => [UTM HOOK FIRED] wpsc_delete_ticket
    [ticket] => WPSC_Ticket Object
        (
            [data:WPSC_Ticket:private] => Array
                (
                    [id] => 42
                    [is_active] => 0
                    [customer] => 18
                    [subject] => Stackboost Helpdesk Ticket
                    [status] => 4
                    [priority] => 3
                    [category] => 1
                    [assigned_agent] => 7
                    [date_created] => 2025-11-16 20:15:49
                    [date_updated] => 2025-11-16 20:18:30
                    [agent_created] => 0
                    [ip_address] => 100.40.206.59
                    [source] => browser
                    [browser] => Google Chrome
                    [os] => Windows 10
                    [add_recipients] =>
                    [prev_assignee] =>
                    [date_closed] => 2025-11-16 20:18:25
                    [user_type] => registered
                    [last_reply_on] => 2025-11-16 20:18:25
                    [last_reply_by] => 17
                    [last_reply_source] => browser
                    [auth_code] => yMSWcvzw
                    [tags] =>
                    [live_agents] => {"7":"2025-11-16 20:18:05"}
                    [misc] =>
                    [cust_26] => 2
                    [cust_27] => 10
                    [cust_28] =>
                    [cust_32] =>
                    [cust_36] =>
                    [cust_37] =>
                    [cust_38] =>
                    [cust_39] => {{current_user_email}}
                    [cust_40] =>
                )

            [is_modified:WPSC_Ticket:private] =>
        )

)
```