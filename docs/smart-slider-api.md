# Smart Slider 3 API Notes

## Key Namespaces
- `\Nextend\SmartSlider3\Application\ApplicationSmartSlider3` — singleton entry point; use `getApplicationTypeAdmin()` for MVCHelper-aware context.
- `\Nextend\SmartSlider3\Application\Model\ModelSliders` — resolves slider IDs (`getByAlias($alias)`), deletes (`deletePermanently()`), and clears caches.
- `\Nextend\SmartSlider3\BackupSlider\ExportSlider` — creates `.ss3` packages with `create(true)` returning a temp file path under `wp_upload_dir()/export/`.
- `\Nextend\SmartSlider3\BackupSlider\ImportSlider` — imports `.ss3` payloads; call `enableReplace()` to preserve slider IDs.
- `\Nextend\SmartSlider3\PublicApi\Project` — exposes `import()` and `clearCache()` helpers for non-admin contexts.

## Export Flow
1. Resolve target slider ID from alias via `ModelSliders::getByAlias()`.
2. Instantiate `ExportSlider($adminContext, $sliderId)` where `$adminContext = ApplicationSmartSlider3::getInstance()->getApplicationTypeAdmin()`.
3. Call `$tmp = $export->create(true);` to write archive to uploads `export/` folder.
4. Copy `$tmp` into the plugin `exports/` directory, then unlink the temporary file.

## Import Flow
1. Instantiate `$import = new ImportSlider($adminContext);` and call `$import->enableReplace();` to reuse slider IDs from the archive.
2. Run `$projectId = $import->import($pathToSs3, $groupId = 0, $imageImportMode = 'clone', $linkedVisuals = 1);`.
3. On success, call `Project::clearCache($projectId);` to invalidate Smart Slider caches.
4. If the slider alias already exists with a different ID, the replace call removes it before inserting the updated version.

## Considerations
- `ExportSlider::create(false)` streams download to browser; always pass `true` for unattended exports so files land on disk.
- Temp export folder lives under uploads; ensure the web user can create `wp-content/uploads/export/`.
- Import requires writable uploads for image cloning and may expand archives in PHP temp directories; validate disk space.
- When Smart Slider classes change, focus adjustments to the `SSGS\Exporter::smartSliderExport()` and `SSGS\Importer::smartSliderImport()` helpers only.
