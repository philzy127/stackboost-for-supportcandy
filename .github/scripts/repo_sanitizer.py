import re
import sys
import os

def sanitize_plugin_file(filepath):
    if not os.path.exists(filepath):
        print(f"File not found: {filepath}")
        return

    print(f"Sanitizing {filepath}...")
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

    print(f"Sanitizing {filepath}...")
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

    print(f"Sanitizing {filepath}...")
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
        # We look for the method name in the callback array.
        pattern = r"^\s*add_action\s*\(\s*['\"][^'\"]+['\"]\s*,\s*\[\s*\$this\s*,\s*['\"]" + action + r"['\"]\s*\]\s*\);\s*$"
        content = re.sub(pattern, '', content, flags=re.MULTILINE)

    # Remove Settings Registration
    # add_settings_section( 'stackboost_license_section', ... );
    content = re.sub(r"^\s*add_settings_section\s*\(\s*['\"]stackboost_license_section['\"].*?\);\s*$", "", content, flags=re.MULTILINE | re.DOTALL)
    # add_settings_field( 'stackboost_license_key', ... );
    content = re.sub(r"^\s*add_settings_field\s*\(\s*['\"]stackboost_license_key['\"].*?\);\s*$", "", content, flags=re.MULTILINE | re.DOTALL)

    # Remove Methods
    methods_to_remove = [
        'display_license_notices',
        'render_license_input',
        'ajax_activate_license',
        'ajax_deactivate_license',
    ]
    for method in methods_to_remove:
        # Regex to match: public function method_name() { ... }
        # Assumes standard formatting.
        # Captures the function definition and recursively matches braces.
        # Since python's re module doesn't support recursion, we use a simpler approach for well-formatted code
        # or we scan line by line. Given the constraints and likely formatting, we can try to match the block.
        # However, a safer way for this specific file structure (standard PSR-like) is to match from declaration to end of method.
        # But for reliability, let's use a pattern that consumes the method body.

        # NOTE: Python re doesn't support recursive patterns.
        # We will use a counting approach to find the closing brace.

        pattern = r"(\s*/\*\*.*?\*/\s*)?public function " + method + r"\s*\(.*?\)\s*\{"
        match = re.search(pattern, content, re.DOTALL)
        if match:
            start_index = match.start()
            # Find the opening brace of the function
            open_brace_index = content.find('{', match.end() - 1)

            # Walk through to find matching closing brace
            balance = 1
            i = open_brace_index + 1
            while i < len(content) and balance > 0:
                if content[i] == '{':
                    balance += 1
                elif content[i] == '}':
                    balance -= 1
                i += 1

            if balance == 0:
                # Remove the block
                content = content[:start_index] + content[i:]

    # Remove the License Card HTML Block
    # Identified by <!-- Card 2: License --> and the following <div class="stackboost-card">...</div>
    card_pattern = r'<!-- Card 2: License -->\s*<div class="stackboost-card">.*?do_settings_sections\( \'stackboost-for-supportcandy\' \);\s*\?>\s*</form>\s*</div>'
    content = re.sub(card_pattern, '', content, flags=re.DOTALL)

    with open(filepath, 'w') as f:
        f.write(content)

def sanitize_uninstall_service(filepath):
    if not os.path.exists(filepath):
        print(f"File not found: {filepath}")
        return

    print(f"Sanitizing {filepath}...")
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
        # Match array line: 'option_name',
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
    base_dir = sys.argv[1]

    plugin_file = os.path.join(base_dir, 'src/WordPress/Plugin.php')
    functions_file = os.path.join(base_dir, 'includes/functions.php')
    settings_file = os.path.join(base_dir, 'src/WordPress/Admin/Settings.php')
    uninstall_file = os.path.join(base_dir, 'src/Services/UninstallService.php')

    sanitize_plugin_file(plugin_file)
    sanitize_functions_file(functions_file)
    sanitize_settings_file(settings_file)
    sanitize_uninstall_service(uninstall_file)
