# SS Git Sync

SS Git Sync is a pair of WordPress plugins that keeps Smart Slider 3 projects in step across multiple sites using Git. One site acts as the **master exporter**, committing `.ss3` packages to a Git repository; every downstream site runs the **secondary importer**, pulling those archives back into Smart Slider 3 on demand.

The current iteration removes cron-driven automation in favor of explicit, traceable actions you can trigger from the WordPress admin. Centralized token distribution and remote imports make it easy to manage 40+ downstream sites without logging into each one.

---

## Repository Layout

```
ss-git-sync/
├── ss-git-sync-master/      # Master plugin (exports sliders + pushes to Git)
├── ss-git-sync-secondary/   # Secondary plugin (pulls from Git + imports sliders)
└── README.md                # You are here
```

Each plugin is self-contained; drop the folder into `wp-content/plugins/` on the appropriate site and activate it.

---

## Requirements

- WordPress 6.0+ with PHP 8.0+
- Smart Slider 3 installed, licensed, and the sliders you plan to sync already created
- Git CLI available for the web user on every site
- GitHub (or compatible) repository to store the exported `.ss3` archives
- GitHub Personal Access Tokens (PATs)
  - Master site PAT requires read/write access (pushes commits)
  - Secondary PATs require read-only access (pulls commits)
- Ability to configure outbound HTTPS if your hosting restricts it

> **Why PATs if the repo is public?**  
> The master needs write access, and PATs give you revocation/audit control. Tokens are validated via the GitHub API on save/distribution, so typos or revoked credentials are caught even for public repositories.

---

## Setup

### 1. Master Site (Exporter)

1. Upload `ss-git-sync-master/` to `wp-content/plugins/` and activate it.
2. Ensure the plugin’s `exports/` directory is writable.
3. In **Settings → SS Git Sync (Master)** configure:
   - **Repository URL** (HTTPS). SSH is not supported in this build.
  - **Branch** (defaults to `main`).
  - **Exports Directory** if different from the plugin folder.
  - **PAT Username** (optional) and **Personal Access Token** (stored encrypted).
  - **Project Map**: Smart Slider alias → target `.ss3` filename.
  - **Secondary Sites**: label, site URL, and a shared secret for each downstream WordPress site.
4. Save. Values persist immediately; secrets remain masked.
5. Run **Export & Push Now** to create the initial commit. Commits look like `SSGSM export on https://example.com @ 2025-10-24 17:19:49`.

### 2. Secondary Sites (Importer)

1. Upload `ss-git-sync-secondary/` to each downstream site and activate it.
2. Ensure its `exports/` directory is writable.
3. In **Settings → SS Git Sync (Secondary)** configure:
  - **Repository URL** / **Branch** matching the master site.
  - **Exports Directory** (defaults to the plugin folder).
  - **PAT Username** (optional) and **Personal Access Token** (stored encrypted). Token validation runs against GitHub when you save; invalid tokens are rejected.
  - **Token Sync Secret** – must match the secret you entered on the master so remote actions can authenticate.
  - **Project Map** identical to the master configuration.
4. Save and run **Pull & Import Now** once to establish the first clone/import.

> Secondary sites no longer use WP Cron. Imports run when you click **Pull & Import Now** or when the master triggers a remote import.

---

## Day-to-Day Workflow

1. **Edit a slider** on the master site using Smart Slider 3.
2. **Export & Push Now** from the master plugin:
  - Exports each mapped slider to `.ss3`.
  - Commits & pushes the changes to your Git repo.
3. **Distribute HTTPS Token** (optional):
  - Paste a new PAT and select the target secondary sites.
  - Tokens are validated with GitHub before being stored.
  - The master dashboard records success/failure per site.
4. **Trigger Remote Import** from the master (or log into a secondary and click **Pull & Import Now**):
  - Selected sites fetch the repo, remove the old slider, import the new archive, and log the outcome.
  - The master dashboard updates with a timestamp, result, and any message returned by the secondary.

All actions append to `wp-content/ss-git-sync.log` on each site. The master provides a log viewer (**SS Git Sync → Logs**) with filtering/pagination without leaving wp-admin.

---

## Remote Controls Explained

### Token Distribution
- Validates the PAT using the GitHub REST API (`GET /user`).
- Sends the token + optional username to `/wp-json/ssgs/v1/token` on each selected secondary.
- Secondary stores the encrypted token, clears cache, and immediately pulls/imports.
- Errors (invalid token, bad shared secret, network issues) are surfaced in the master notice + status table.

### Remote Import
- Sends a signed request to `/wp-json/ssgs/v1/import` on each selected secondary.
- Secondary runs `Importer::pullAndImportAll()` and returns success/failure with a message.
- Useful after an export or when you want to resync a group of downstream sites from a single button.

### Secondary Status Table
- Appears beneath the actions on the master settings page.
- Columns: site label, last action (Token Distribution / Remote Import), result (success/error), timestamp + “time ago”, and the message captured during the last run.
- Status entries are updated whenever token distribution or remote import actions are executed.

---

## Logs & Troubleshooting

### Viewing Logs
- Master & secondary write to `wp-content/ss-git-sync.log`.
- Master’s **Logs** screen shows entries with ISO timestamps, channel (`git`, `export`, `import`, `rest`, `distributor`…), exit code, and full messages (including Git hints).
- Filters allow you to isolate a channel or paginate through history.

### Common Checks

| Symptom | What to Check |
| ------- | ------------- |
| Token distribution fails immediately | PAT validation failed (wrong scopes, typo, revoked). Fix the token and retry. |
| Remote import fails | Secondary likely has an authentication issue. The status table shows the error (e.g., “Personal Access Token not configured.”). Correct the PAT and re-run. |
| Slider didn’t update | Make sure the project alias exists on both sites, the `.ss3` file was exported, and the secondary’s import reported success. |
| Git errors | Inspect the `git` channel in the log (missing repo permissions, bad remote URL, etc.). |

When in doubt, re-run the action manually—everything is idempotent and designed to report errors rather than fail silently.

---

## Security Notes

- PATs and shared secrets are encrypted with WordPress salts before storage.
- Validation requests only run for GitHub hosts; other Git providers can be supported by extending the helper.
- Deploy keys / SSH are not part of this build; HTTPS + PAT provides centralized control and monitoring.

---

## Development Notes

- Both plugins are written with WordPress core APIs only—no external dependencies.
- Shared helpers live in each plugin’s `includes/` directory; changes to encryption or Git behavior will need to be mirrored if you keep the plugins decoupled.
- Logs intentionally capture Git stdout/stderr verbatim to simplify debugging in production environments.

---

## Getting Started Checklist

1. **Create PATs** (read/write for master, read-only for each secondary).
2. **Install & configure** the master plugin.
3. **Install & configure** every secondary plugin.
4. **Run a manual export/import** to verify the loop.
5. **Distribute tokens** from the master and confirm validation succeeds.
6. **Trigger remote import** to ensure downstream sites respond.

Once this loop is working, day-to-day maintenance is just “export after every Smart Slider change” and using the master dashboard to push tokens or imports whenever needed.

Happy syncing!
