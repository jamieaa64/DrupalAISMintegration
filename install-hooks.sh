#!/bin/bash
#
# Install git hooks for DrupalAISMintegration
# Run this once to enable automatic updates after git pull
#

set -e

echo "======================================"
echo "  Git Hooks Installer"
echo "======================================"
echo ""

# Check if we're in a git repository
if [ ! -d ".git" ]; then
  echo "Error: Not in a git repository!"
  echo "Please run this script from the repository root."
  exit 1
fi

# Install post-merge hook
if [ -f ".git/hooks/post-merge" ]; then
  echo "⚠ post-merge hook already exists."
  echo ""
  read -p "Do you want to overwrite it? (y/N): " -n 1 -r
  echo ""
  if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "Installation cancelled."
    exit 0
  fi
fi

echo "→ Installing post-merge hook..."
cp hooks/post-merge .git/hooks/post-merge
chmod +x .git/hooks/post-merge

echo ""
echo "======================================"
echo "  ✓ Git hooks installed!"
echo "======================================"
echo ""
echo "The post-merge hook will now run automatically after 'git pull'."
echo "It will:"
echo "  - Install Composer dependencies (if composer.lock changed)"
echo "  - Run database updates"
echo "  - Import configuration (if config files changed)"
echo "  - Clear cache"
echo ""
echo "You can still use './update.sh' to run updates manually."
echo ""
