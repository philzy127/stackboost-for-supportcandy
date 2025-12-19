import re
import sys
import os

def remove_method_by_name(content, method_name):
    """
    Removes a PHP method by name using a state machine to correctly handle braces,
    and safely identifies preceding docblocks without greedy regex backtracking.
    """
    # Regex to find the method definition ONLY.
    # We do NOT try to match the docblock here to avoid catastrophic backtracking.
    pattern = re.compile(r"(public function " + re.escape(method_name) + r"\s*\(.*?\)\s*\{)", re.DOTALL)

    match = pattern.search(content)
    if not match:
        return content

    start_index = match.start()
    # The match includes the opening brace '{', so end-1 is that brace.
    open_brace_index = match.end() - 1

    # --- Docblock Detection (Look Backwards) ---
    # Scan backwards from start_index to see if there is a docblock.
    cursor = start_index - 1

    # 1. Skip whitespace backwards
    while cursor >= 0 and content[cursor].isspace():
        cursor -= 1

    # 2. Check for closing comment '*/'
    if cursor >= 1 and content[cursor] == '/' and content[cursor-1] == '*':
        # We found a docblock end. Now find the start '/**'
        cursor -= 2 # Move past '*/'

        # Iterate backwards
        found_start = False
        while cursor >= 2:
            if content[cursor] == '*' and content[cursor-1] == '*' and content[cursor-2] == '/':
                found_start = True
                cursor -= 2 # Point to '/'
                break
            cursor -= 1

        if found_start:
            # We found the docblock start.
            start_index = cursor

            # Optional: Consume preceding newline if present
            if start_index > 0 and content[start_index-1] == '\n':
                 start_index -= 1

    # --- Brace Balancing (Forward Scan) ---
    STATE_CODE = 0
    STATE_STRING_SINGLE = 1
    STATE_STRING_DOUBLE = 2
    STATE_COMMENT_SINGLE = 3
    STATE_COMMENT_MULTI = 4

    state = STATE_CODE
    balance = 1 # We start after the first opening brace
    i = open_brace_index + 1
    length = len(content)

    while i < length and balance > 0:
        char = content[i]
        prev_char = content[i-1] if i > 0 else ''

        # Handle State Transitions
        if state == STATE_CODE:
            if char == "'":
                state = STATE_STRING_SINGLE
            elif char == '"':
                state = STATE_STRING_DOUBLE
            elif char == '/' and i+1 < length and content[i+1] == '/':
                state = STATE_COMMENT_SINGLE
                i += 1 # Skip next char
            elif char == '/' and i+1 < length and content[i+1] == '*':
                state = STATE_COMMENT_MULTI
                i += 1 # Skip next char
            elif char == '{':
                balance += 1
            elif char == '}':
                balance -= 1

        elif state == STATE_STRING_SINGLE:
            if char == "'" and prev_char != '\\': # Not escaped
                state = STATE_CODE

        elif state == STATE_STRING_DOUBLE:
            if char == '"' and prev_char != '\\': # Not escaped
                state = STATE_CODE

        elif state == STATE_COMMENT_SINGLE:
            if char == '\n':
                state = STATE_CODE

        elif state == STATE_COMMENT_MULTI:
            if char == '*' and i+1 < length and content[i+1] == '/':
                state = STATE_CODE
                i += 1 # Skip next char

        i += 1

    if balance == 0:
        # We found the closing brace.
        # Remove from start_index to i
        return content[:start_index] + content[i:]
    else:
        # Failed to find matching brace, return original (safety)
        print(f"Warning: Could not find matching brace for method {method_name}")
        return content

def sanitize_plugin_file(filepath):
    if not os.path.exists(filepath):
        print(f"File not found: {filepath}")
        return

    # print(f"Sanitizing {filepath}...")
    with open(filepath, 'r') as f:
        content = f.read()

    # 1. Remove USE statements
    modules_to_remove = [
        r'StackBoost\\ForSupportCandy\\Modules\\ConditionalViews\\WordPress',
        r'StackBoost\\ForSupportCandy\\Modules\\QueueMacro\\WordPress',
        r'StackBoost\\ForSupportCandy\\Modules\\AfterTicketSurvey\\WordPress',
        r'StackBoost\\ForSupportCandy\\Modules\\UnifiedTicketMacro\\WordPress',
        r'StackBoost\\ForSupportCandy\\Modules\\Directory\\WordPress',
        r'StackBoost\\ForSupportCandy\\Modules\\Directory\\Admin\\TicketWidgetSettings',
        r'StackBoost\\ForSupportCandy\\Modules\\OnboardingDashboard\\OnboardingDashboard',
    ]

    for module in modules_to_remove:
        pattern = r'^\s*use\s+' + module + r';\s*$'
        content = re.sub(pattern, '', content, flags=re.MULTILINE)

    # 2. Remove Initialization Blocks
    keys_to_remove = [
        'conditional_views',
        'queue_macro',
        'after_ticket_survey',
        'unified_ticket_macro',
        'staff_directory',
        'onboarding_dashboard'
    ]

    for key in keys_to_remove:
        regex = r"(\s*if\s*\(\s*stackboost_is_feature_active\s*\(\s*'" + key + r"'\s*\)\s*\)\s*\{(?:[^{}]*|\{[^{}]*\})*\})"
        content = re.sub(regex, '', content, flags=re.MULTILINE | re.DOTALL)

    with open(filepath, 'w') as f:
        f.write(content)

def sanitize_functions_file(filepath):
    if not os.path.exists(filepath):
        print(f"File not found: {filepath}")
        return

    # print(f"Sanitizing {filepath}...")
    with open(filepath, 'r') as f:
        content = f.read()

    # Remove usage of License Core class
    content = re.sub(r'^\s*use\s+StackBoost\\ForSupportCandy\\Core\\License;\s*$', '', content, flags=re.MULTILINE)

    # Replace License::get_tier() call with hardcoded 'lite'
    content = content.replace('$current_tier = License::get_tier();', "$current_tier = 'lite';")

    with open(filepath, 'w') as f:
        f.write(content)

def sanitize_settings_file(filepath):
    if not os.path.exists(filepath):
        print(f"File not found: {filepath}")
        return

    # print(f"Sanitizing {filepath}...")
    with open(filepath, 'r') as f:
        content = f.read()

    # Remove Actions
    actions_to_remove = [
        'display_license_notices',
        'ajax_activate_license',
        'ajax_deactivate_license',
    ]
    for action in actions_to_remove:
        # Regex to match add_action( 'hook', [ $this, 'method' ] );
        pattern = r"^\s*add_action\s*\(\s*['\"][^'\"]+['\"]\s*,\s*\[\s*\$this\s*,\s*['\"]" + action + r"['\"]\s*\]\s*\);\s*$"
        content = re.sub(pattern, '', content, flags=re.MULTILINE)

    # Remove Settings Registration
    content = re.sub(r"^\s*add_settings_section\s*\(\s*['\"]stackboost_license_section['\"].*?\);\s*$", "", content, flags=re.MULTILINE | re.DOTALL)
    content = re.sub(r"^\s*add_settings_field\s*\(\s*['\"]stackboost_license_key['\"].*?\);\s*$", "", content, flags=re.MULTILINE | re.DOTALL)

    # Remove Methods using robust parser
    methods_to_remove = [
        'display_license_notices',
        'render_license_input',
        'ajax_activate_license',
        'ajax_deactivate_license',
    ]
    for method in methods_to_remove:
        content = remove_method_by_name(content, method)

    # Remove the License Card HTML Block
    card_pattern = r'<!-- Card 2: License -->\s*<div class="stackboost-card">.*?do_settings_sections\( \'stackboost-for-supportcandy\' \);\s*\?>\s*</form>\s*</div>'
    content = re.sub(card_pattern, '', content, flags=re.DOTALL)

    with open(filepath, 'w') as f:
        f.write(content)

def sanitize_uninstall_service(filepath):
    if not os.path.exists(filepath):
        print(f"File not found: {filepath}")
        return

    # print(f"Sanitizing {filepath}...")
    with open(filepath, 'r') as f:
        content = f.read()

    options_to_remove = [
        'stackboost_license_key',
        'stackboost_license_instance_id',
        'stackboost_license_tier',
        'stackboost_license_variant_id',
        'sb_last_verified_at',
    ]

    for opt in options_to_remove:
        pattern = r"^\s*['\"]" + opt + r"['\"],\s*$"
        content = re.sub(pattern, '', content, flags=re.MULTILINE)

    transients_to_remove = [
        'stackboost_license_error_msg',
    ]
    for trans in transients_to_remove:
        pattern = r"^\s*['\"]" + trans + r"['\"],\s*$"
        content = re.sub(pattern, '', content, flags=re.MULTILINE)

    with open(filepath, 'w') as f:
        f.write(content)

if __name__ == "__main__":
    if len(sys.argv) < 2:
        print("Usage: python3 repo_sanitizer.py <repo_dir>")
        sys.exit(1)

    base_dir = sys.argv[1]

    plugin_file = os.path.join(base_dir, 'src/WordPress/Plugin.php')
    functions_file = os.path.join(base_dir, 'includes/functions.php')
    settings_file = os.path.join(base_dir, 'src/WordPress/Admin/Settings.php')
    uninstall_file = os.path.join(base_dir, 'src/Services/UninstallService.php')

    sanitize_plugin_file(plugin_file)
    sanitize_functions_file(functions_file)
    sanitize_settings_file(settings_file)
    sanitize_uninstall_service(uninstall_file)
