#!/bin/bash

# Exit on error
set -e

echo "Starting build process..."

# Define directories
SRC_DIR="stackboost-for-supportcandy"
BUILD_DIR="build"
PRO_DIR="$BUILD_DIR/pro/stackboost-for-supportcandy"
REPO_DIR="$BUILD_DIR/repo/stackboost-for-supportcandy"

# Clean previous builds
rm -rf "$BUILD_DIR"
mkdir -p "$PRO_DIR"
mkdir -p "$REPO_DIR"

echo "Copying source files..."
rsync -av --exclude '.git' --exclude '.github' --exclude 'build' --exclude 'tests' --exclude 'Documentation' "$SRC_DIR/" "$PRO_DIR/" > /dev/null
rsync -av --exclude '.git' --exclude '.github' --exclude 'build' --exclude 'tests' --exclude 'Documentation' "$SRC_DIR/" "$REPO_DIR/" > /dev/null

# --- BUILD PREMIUM VERSION ---
echo "Building Premium version..."
# No longer modifying header; handled dynamically by plugin code based on license.

cd "$BUILD_DIR/pro"
zip -r -q ../../stackboost-for-supportcandy-premium.zip .
cd ../..

# --- BUILD REPO (FREE) VERSION ---
echo "Building Repo version..."
echo "Removing prohibited directories and files..."
rm -rf "$REPO_DIR/src/Modules/Directory"
rm -rf "$REPO_DIR/src/Modules/ConditionalViews"
rm -rf "$REPO_DIR/src/Modules/OnboardingDashboard"
rm -rf "$REPO_DIR/src/Modules/QueueMacro"
rm -rf "$REPO_DIR/src/Modules/UnifiedTicketMacro"
rm -rf "$REPO_DIR/src/Modules/AfterTicketSurvey"
rm -rf "$REPO_DIR/src/Modules/ChatBubbles"
rm -rf "$REPO_DIR/includes/libraries/dompdf"
rm -rf "$REPO_DIR/assets/libraries/datatables"

# Remove License and PDF Service files
rm -f "$REPO_DIR/src/Services/LicenseManager.php"
rm -f "$REPO_DIR/src/Core/License.php"
rm -f "$REPO_DIR/src/Services/PdfService.php"

# Remove Directory Module files
rm -f "$REPO_DIR/src/Services/DirectoryService.php"
rm -f "$REPO_DIR/single-sb_staff_dir.php"
rm -f "$REPO_DIR/template-parts/directory-modal-content.php"
rm -f "$REPO_DIR/assets/js/stackboost-directory.js"
rm -f "$REPO_DIR/assets/css/stackboost-directory.css"

# Remove Onboarding Dashboard Module files
rm -f "$REPO_DIR/assets/js/onboarding-dashboard.js"
rm -f "$REPO_DIR/assets/css/onboarding-dashboard.css"

# Remove ATS Module files
rm -f "$REPO_DIR/assets/css/stackboost-ats-frontend.css"
rm -f "$REPO_DIR/assets/js/stackboost-ats-frontend.js"

echo "Sanitizing Code..."
# Pass the root directory to the sanitizer
python3 .github/scripts/repo_sanitizer.py "$REPO_DIR"

cd "$BUILD_DIR/repo"
zip -r -q ../../stackboost-for-supportcandy.zip .
cd ../..

echo "Build complete!"
