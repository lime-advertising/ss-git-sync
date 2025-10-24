# Milestones

## M0 — Split Architecture (Complete)
- [x] Master and secondary plugins separated, each bundling its own support utilities.
- [x] Settings forms persist project maps reliably on both plugins.
- [x] Secondary importer deletes/re-imports sliders without creating duplicates.

## M1 — Smart Slider API Hardening (In Progress)
- [ ] Add automated detection for Smart Slider export/import class changes across versions.
- [ ] Provide optional fallbacks (manual export/import helper screens).

## M2 — Operational Enhancements (Planned)
- [ ] CLI commands for scripted deployments (`wp ssgsm export`, `wp ssgss import`).
- [ ] Health checks and logging improvements (e.g., Slack notifications on failure).
- [ ] Optional checksum verification for `.ss3` files after Git clone.

## M3 — Quality & Observability (Planned)
- [ ] Automated tests for settings handlers and Git wrapper (using mocks).
- [ ] Diagnostic command to list mapped sliders and their stored IDs.
- [ ] UI widgets showing last import/export timestamp per slider.
