#!/bin/bash
#
# Set up Symfony Messenger for DrupalAISMintegration
#

set -e

echo "======================================"
echo "  Symfony Messenger Setup"
echo "======================================"
echo ""

echo "→ Checking module status..."
ddev drush pm:list --filter=sm

echo ""
echo "→ Enabling required modules..."
ddev drush en sm sm_transport_doctrine sm_scheduler -y

echo ""
echo "→ Clearing cache..."
ddev drush cr

echo ""
echo "→ Checking available messenger commands..."
ddev drush list | grep -i messenger || echo "No messenger commands found yet"

echo ""
echo "======================================"
echo "  ✓ Setup complete!"
echo "======================================"
echo ""
echo "Try one of these commands to process messages:"
echo "  ddev drush messenger:consume"
echo "  ddev drush sm:consume"
echo "  ddev drush queue:run messenger"
echo ""
echo "Or check available commands:"
echo "  ddev drush list messenger"
echo ""
