import re
import sys
import os

def sanitize_plugin_file(filepath):
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

if __name__ == "__main__":
    sanitize_plugin_file(sys.argv[1])
