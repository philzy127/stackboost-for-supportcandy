import sys
import re

def modify_pro_header(filepath):
    with open(filepath, 'r') as f:
        content = f.read()

    pattern = r'(^\s*\*\s*Plugin Name:\s*StackBoost\s*-\s*For SupportCandy)'
    replacement = r'\1 - Pro'

    new_content = re.sub(pattern, replacement, content, flags=re.MULTILINE)

    with open(filepath, 'w') as f:
        f.write(new_content)

if __name__ == "__main__":
    modify_pro_header(sys.argv[1])
