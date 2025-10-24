# Troubleshooting

## Git Issues
- **`fatal: Could not read from remote repository`** – check SSH keys and repo permissions for the web user.
- **`fatal: Cannot fast-forward to multiple branches`** – two pulls ran simultaneously. Re-run the pull once; the first invocation usually succeeded.

## Smart Slider Import Problems
- Verify the slider alias in the project map matches the Smart Slider alias exactly.
- Ensure the `.ss3` file exists in the plugin’s `exports/` directory on the secondary site.
- Check `wp-content/ss-git-sync.log` for `Failed to delete existing slider` or `Smart Slider import failed` entries.
- The importer deletes the old slider before import; if a new alias appears (e.g. `slider-2`), delete the duplicates and re-run the import after confirming the mapping.

## Missing Project Map Entries
- Settings are saved via the plugin’s custom handler. If fields still appear empty, confirm `ssgsm_settings` / `ssgss_settings` in the `wp_options` table actually contain the `projects` array.

## Cron Not Running
- Use `wp cron event list | grep ssgss_cron_sync` to confirm the hook is scheduled.
- If `DISABLE_WP_CRON` is defined, add a real server cron hitting `wp cron event run ssgss_cron_sync` at the desired cadence.
