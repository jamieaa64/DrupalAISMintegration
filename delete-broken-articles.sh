#!/bin/bash
#
# Delete broken article nodes created before article content type was properly configured
# Run this AFTER importing the article content type configuration
#

set -e

echo "======================================"
echo "  Delete Broken Article Nodes"
echo "======================================"
echo ""
echo "This will delete all nodes with type 'article' and recreate them properly."
echo ""
read -p "Do you want to continue? (y/N): " -n 1 -r
echo ""

if [[ ! $REPLY =~ ^[Yy]$ ]]; then
  echo "Cancelled."
  exit 0
fi

echo ""
echo "→ Deleting all article nodes..."
ddev drush entity:delete node --bundle=article -y

echo ""
echo "======================================"
echo "  ✓ Cleanup complete!"
echo "======================================"
echo ""
echo "All article nodes have been deleted."
echo "You can now generate new articles at:"
echo "  /demo/content-processor/generate"
echo ""
