# SS Git Sync Architecture

## Two Plugins
- **ss-git-sync-master/** – install only on the source site. Exports selected Smart Slider 3 projects to Git.
- **ss-git-sync-secondary/** – install on every downstream site. Pulls `.ss3` files from Git, deletes any old copy of the slider, and re-imports the latest version.
- Each plugin bundles its own Git wrapper, logging, and settings helpers so you can drop the folder straight into `wp-content/plugins/`.
- Both plugins support SSH deploy keys and HTTPS Personal Access Tokens (stored encrypted) so teams can choose the authentication model that best fits their environment.

## Master Flow
1. Configure repository URL, branch, exports directory, and the project map (alias → `.ss3`).
2. When you click **Export & Push Now**, the plugin exports each slider via Smart Slider’s API, writes the `.ss3` file into the plugin’s `exports/` folder, and pushes the commit.
3. Slider IDs are resolved at export time so aliases remain stable.

## Secondary Flow
1. Configure the same repository/branch plus the project map (alias → `.ss3`).
2. **Pull & Import Now** (or the scheduled cron) pulls the repo, deletes the existing slider for that alias, imports the new `.ss3`, updates the alias, and stores the Smart Slider ID alongside the settings.
3. Project IDs are cached in the site option so future imports always replace the same slider—no more `slider-2` duplicates.

## Key Improvements
- Settings are saved via a custom handler that normalises project maps; the fields no longer clear themselves after each save.
- Slider IDs are stored with the plugin settings (`project_ids`), and importer runs always synchronise aliases and IDs to stop duplication.
- Each plugin does one job: the master never handles imports, and the secondary never pushes.
