#!/bin/bash
#
# Quick update script for DrupalAISMintegration
# This pulls changes and updates the Drupal site
#

set -e  # Exit on error

echo "======================================"
echo "  Drupal Update Script"
echo "======================================"
echo ""

# Pull latest changes
echo "→ Pulling latest changes from git..."
git pull

echo ""
echo "→ Installing/updating Composer dependencies..."
ddev composer install

echo ""
echo "→ Running database updates..."
ddev drush updb -y

echo ""
echo "→ Importing configuration..."
ddev drush config:import -y

echo ""
echo "→ Rebuilding cache..."
ddev drush cr

echo ""
echo "======================================"
echo "  ✓ Update complete!"
echo "======================================"
echo ""
echo "Your site is now up to date."
echo ""
