# Symfony Messenger Setup Guide

## Check Module Status

First, check if the Symfony Messenger module is enabled:

```bash
ddev drush pm:list --filter=sm
```

## Enable Required Modules

If not enabled, install the required modules:

```bash
# Enable Symfony Messenger and related modules
ddev drush en sm sm_transport_doctrine sm_scheduler -y
ddev drush cr
```

## Available Drush Commands

After enabling, check available messenger commands:

```bash
ddev drush list | grep -i messenger
```

Common commands might be:
- `ddev drush messenger:consume` (or similar)
- `ddev drush sm:consume`
- `ddev drush queue:run` (alternative for some setups)

## Process Messages

Once modules are enabled, try these commands:

```bash
# Option 1: Consume messenger messages
ddev drush messenger:consume

# Option 2: If using queue fallback
ddev drush queue:run messenger

# Option 3: Process with time limit
ddev drush messenger:consume --time-limit=60
```

## Testing Without Worker

For testing purposes, you can also configure Symfony Messenger to run synchronously (no worker needed). This processes messages immediately instead of queuing them.

Would you like me to create a synchronous configuration for easier testing?

## Alternative: Cron Processing

You can also set up cron to process messages periodically:

```bash
# Add to crontab or run manually
ddev drush cron
```

## Troubleshooting

If commands still don't work:
1. Check composer.json has the sm modules
2. Run `ddev composer install`
3. Clear cache: `ddev drush cr`
4. Re-enable modules: `ddev drush en sm -y`
