# Quick Update Instructions

This repository includes helper scripts to make local development faster.

## Option 1: Manual Updates (Quick & Simple)

After pulling changes, just run:

```bash
./update.sh
```

This will:
- Pull latest changes from git
- Install/update Composer dependencies
- Run database updates
- Import configuration
- Clear cache

## Option 2: Automatic Updates (Set it and forget it)

Install the git hook **once** to automatically update after every `git pull`:

```bash
./install-hooks.sh
```

After installation, the hook will automatically:
- Install Composer dependencies (only if composer.lock changed)
- Run database updates
- Import configuration (only if config files changed)
- Clear cache

**Note**: The hook only runs when DDEV is running. If DDEV is stopped, you'll need to run `./update.sh` manually.

## What Gets Run

Both methods ensure your local environment is synchronized:

1. **Git pull** - Get latest code
2. **Composer install** - Update PHP dependencies
3. **Database updates** - Apply schema changes (`drush updb`)
4. **Config import** - Sync configuration (`drush config:import`)
5. **Cache clear** - Rebuild cache (`drush cr`)

## Troubleshooting

### Script won't run
Make sure scripts are executable:
```bash
chmod +x update.sh install-hooks.sh
```

### DDEV not running
Start DDEV first:
```bash
ddev start
```

### Hook not working
Check if the hook is installed:
```bash
ls -la .git/hooks/post-merge
```

If not, run `./install-hooks.sh` again.
