# HTTPS Personal Access Tokens

If SSH deploy keys are not an option, both SS Git Sync plugins can authenticate to Git over HTTPS with a GitHub Personal Access Token (PAT). Tokens are encrypted before they’re stored in WordPress, but you should still treat them like passwords and scope them narrowly.

## Prerequisites
- GitHub account (or the equivalent on your Git hosting platform).
- Ability to create PATs with repository access.
- WordPress admin access to paste the token into the plugin settings.

> **Note:** GitHub disables new “classic” PATs by default. You can use either a fine-grained PAT (preferred) or a classic PAT with the scopes listed below.

## Master Site (Exporter)
1. **Create the token**
   - Visit <https://github.com/settings/tokens> → **Fine-grained tokens → Generate new token**.
   - Select the repository that stores the Smart Slider exports.
   - Grant at least:
     - `Contents` → **Read and write** (required to push).
     - `Metadata` → **Read-only** (GitHub adds this automatically).
   - Set an expiration that matches your rotation policy (shorter is better).
   - Generate the token and copy it immediately.

   **Classic token alternative:** enable `repo` scope (read/write) and leave other scopes disabled.

2. **Configure the plugin**
   - In WordPress, go to **Settings → SS Git Sync (Master)**.
   - Under **Authentication**, select **HTTPS + Personal Access Token**.
   - Optional: enter the GitHub username that owns the token (leave blank to use the `x-oauth-basic` username).
   - Paste the token into the **Personal Access Token** field.
   - Save settings. The token field empties on the next page load—this is expected.

3. **Trigger a test export**
   - Click **Export & Push Now**.
   - Confirm the commit appears in your repository.

## Secondary Sites (Importer)
Create a separate PAT for each environment so you can revoke access independently.

1. **Create the token**
   - Navigate to the PAT creation page.
   - Choose the exports repository.
   - Grant:
     - `Contents` → **Read-only** (secondary sites only pull).
     - `Metadata` → **Read-only**.
   - Generate and copy the token.

2. **Configure the plugin**
   - In WordPress, open **Settings → SS Git Sync (Secondary)**.
   - Select **HTTPS + Personal Access Token**.
   - Optional: record the GitHub username tied to the token.
   - Paste the token and save.

3. **Verify connectivity**
   - Click **Pull & Import Now**.
   - Check that the repo pulls successfully and sliders re-import.

## Maintenance & Security Tips
- Rotate tokens on a schedule or immediately when staff leave.
- Because tokens are encrypted using WordPress salts, keep `wp-config.php` secure; changing salts will invalidate stored tokens.
- If the repository moves or is renamed, update the plugin’s repository URL before running the next sync.
- Prefer SSH deploy keys when possible—tokens expose more attack surface if compromised.

## Token Distribution Scaffold
- The master plugin now includes a **Secondary Sites** table and a **Distribute HTTPS Token** form. Populate each downstream site with its URL and a shared secret (the secret is encrypted at rest).
- Each secondary site exposes a scaffold REST endpoint at `/wp-json/ssgs/v1/token`. Configure the matching **Token Sync Secret** on the secondary settings screen so the master can authenticate when the dispatcher is completed.
- The current implementation logs pending distribution requests; wire up the HTTP dispatch logic and secondary-side importer when you’re ready to automate monthly token rotation.
