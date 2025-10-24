# Operations Playbook

## Master Site
- Trigger **Export & Push Now** after editing a slider; the plugin re-exports mapped projects and pushes the commit.
- Check `wp-content/ss-git-sync.log` for git/export entries if the push fails.

## Secondary Sites
- **Pull & Import Now** pulls Git, deletes the existing slider, imports the new `.ss3`, and updates cached IDs.
- Cron hook `ssgss_cron_sync` runs the same process automatically—set the frequency in settings and ensure `wp-cron` (or a real cron hitting `wp cron event run ssggss_cron_sync`) is active.

## Logs
- All git/export/import activity is appended to `wp-content/ss-git-sync.log` with timestamps and exit codes.

## Slider IDs & Aliases
- The importer stores Smart Slider IDs in the `project_ids` setting. If you rename an alias on the master, update the project map on every site to match.
- Because the importer deletes the previous slider before import, duplicates are no longer created.

## Failure Recovery
1. Check the log file for git or Smart Slider errors.
2. Re-run the action manually from the settings page.
3. Verify the server still has Git access (run `ssh -T git@github.com` as the web user).
4. Confirm the `.ss3` file exists under each plugin’s `exports/` directory.
