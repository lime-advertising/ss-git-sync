# Setup Guide

## Prerequisites
- WordPress 6.0+ with PHP 8.0+.
- Smart Slider 3 installed and licensed on all sites.
- Git CLI available for the web user.
- SSH deploy keys (or HTTPS PAT) configured for each site.

## Master Site (Exporter)
1. Upload `ss-git-sync-master/` to `wp-content/plugins/` and activate it.
2. Ensure the plugin’s `exports/` folder is writable by the web user.
3. Configure the plugin (Settings → **SS Git Sync (Master)**):
   - Repository URL (SSH strongly recommended, HTTPS supported).
   - Branch name (default `main`).
   - Export directory (defaults to the plugin folder).
   - Authentication: choose SSH or HTTPS token (tokens are stored encrypted; leave the token field blank on later saves to keep the existing value).
   - Project map: Smart Slider alias → filename (e.g. `homepage_hero` → `homepage_hero.ss3`).
4. Click **Save Settings**. The form persists values immediately—no more disappearing fields.
5. Trigger **Export & Push Now** to create the initial commit and push.

## Secondary Sites (Importer)
1. Upload `ss-git-sync-secondary/` to each downstream site and activate it.
2. Ensure the plugin’s `exports/` folder is writable.
3. Configure the plugin (Settings → **SS Git Sync (Secondary)**):
   - Repository URL/branch pointing to the same Git repo as the master.
   - Export directory (defaults to the plugin folder).
   - Authentication mode (SSH or HTTPS token). Tokens are stored encrypted and never displayed once saved.
   - Cron frequency for automated pulls (e.g. hourly).
   - Project map matching the master alias → filename entries.
4. Save the settings. The project list remains intact after saving.
5. Click **Pull & Import Now** to pull Git, delete any existing slider with that alias, and import the newest `.ss3` file.

## SSH Key Quick Reference
```bash
ssh-keygen -t ed25519 -C "ssgs-<ENV>" -f ~/.ssh/ssgs_key
cat ~/.ssh/ssgs_key.pub   # add as deploy key in repo (write for master, read for secondary)
printf "Host github.com\n  IdentityFile ~/.ssh/ssgs_key\n  IdentitiesOnly yes\n" >> ~/.ssh/config
ssh -T git@github.com     # accept host key
```

After both plugins are installed and configured, the master can continue exporting while each secondary site pulls and imports automatically (on demand or via cron).
