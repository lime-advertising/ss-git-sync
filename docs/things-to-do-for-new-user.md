# Things To Do For A New Contributor

1. **Review the repo layout**
- `ss-git-sync-master/` exports sliders; `ss-git-sync-secondary/` imports them.
- Each plugin is self-contained; just copy the folder into `wp-content/plugins/`.
2. **Provision access**
   - Confirm SSH access to the Git remote for both master and secondary servers.
   - Ensure Smart Slider 3 is active and you can see slider aliases in the UI.
3. **Install the plugins**
- Activate the master plugin on the source site; activate the secondary plugin on every downstream site.
- Verify the `exports/` directory inside each plugin is writable.
- Decide on authentication up front (SSH deploy keys or HTTPS PAT). HTTPS tokens can be pasted directly into the settings screen and are stored encrypted.
4. **Configure settings**
   - Master: repo, branch, exports dir, and project map. Save and run **Export & Push Now**.
   - Secondary: same repo/branch, desired cron frequency, and identical project map. Save and run **Pull & Import Now**.
5. **Verify sync loop**
   - Edit a slider on the master, export/push, then pull/import on a secondary and confirm the slider updates.
   - Check `wp-content/ss-git-sync.log` for git/import/export entries if something looks off.
6. **Housekeeping**
   - Register the cron hook (`ssgss_cron_sync`) with real cron if you prefer deterministic scheduling.
   - Keep an eye on `project_ids` stored in each site’s settings if Smart Slider aliases change.
