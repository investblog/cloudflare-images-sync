# Next Version Issue Draft

## Title

vNext: harden docs, UX, and operability before adding major features

## Summary

The plugin foundation is solid: admin actions consistently use nonce and capability checks, the code is split into `Admin` / `Core` / `Repos`, and the core feature set is already useful.

For the next version, the highest-value work is not a large new feature. The priority should be tightening correctness and polish in areas that currently create avoidable support load or credibility gaps:

- fix documentation drift
- fix broken text encoding in UI/docs
- add minimal automated behavioral coverage
- improve bulk/diagnostic operability
- define a formal security reporting path

## Findings

### 1. Documentation drift is now user-visible

The public docs still reference old command/filter names and an outdated version badge.

- `README.md` still shows version `1.0.3` instead of current `1.0.7`
- `README.md` documents `wp cfi ...`, but the registered command is `wp cfimg`
- `README.md` documents `cfi_delivery_url` and `cfi_resolve_source` filters, but there are no matching `apply_filters()` calls in the plugin code

Impact:

- users will copy commands that do not work
- developers may rely on extension points that do not exist
- this creates avoidable trust/support issues around releases

References:

- [README.md](/W:/Projects/wpci/README.md)
- [images-sync-for-cloudflare.php](/W:/Projects/wpci/plugin/images-sync-for-cloudflare.php)

### 2. Broken text encoding leaks into user-facing strings

The repository contains mojibake such as broken dashes/arrows in multiple files. This is visible in README, readme.txt, comments, and admin UI strings.

Impact:

- lowers perceived quality in wp-admin and plugin directory materials
- creates translation churn
- makes documentation/screenshots look unreliable

References:

- [README.md](/W:/Projects/wpci/README.md)
- [readme.txt](/W:/Projects/wpci/plugin/readme.txt)
- [SettingsPage.php](/W:/Projects/wpci/plugin/src/Admin/SettingsPage.php)
- [MappingsPage.php](/W:/Projects/wpci/plugin/src/Admin/MappingsPage.php)
- [PreviewPage.php](/W:/Projects/wpci/plugin/src/Admin/PreviewPage.php)
- [AdminMenu.php](/W:/Projects/wpci/plugin/src/Admin/AdminMenu.php)

### 3. CI currently checks style, not behavior

The CI workflow runs PHPCS and build packaging, but there is no automated coverage for:

- settings save flow
- mapping CRUD flow
- nonce/capability enforcement
- sync engine behavior
- CLI behavior

Impact:

- regressions in security-sensitive admin flows are easy to ship
- docs/implementation drift is more likely to recur
- refactors remain expensive because there is no safety net

Reference:

- [.github/workflows/ci.yml](/W:/Projects/wpci/.github/workflows/ci.yml)

### 4. Bulk sync and diagnostics are operationally thin

Bulk sync exists and is useful, but visibility is still fairly shallow:

- no dry-run summary in the admin UI before enqueueing
- no progress summary beyond logs
- no first-class diagnostics page for config/API/Flexible Variants/Action Scheduler state

Impact:

- harder support/debugging on real client sites
- admins need to infer state from logs instead of seeing health directly

References:

- [MappingsPage.php](/W:/Projects/wpci/plugin/src/Admin/MappingsPage.php)
- [BulkEnqueuer.php](/W:/Projects/wpci/plugin/src/Jobs/BulkEnqueuer.php)
- [SettingsPage.php](/W:/Projects/wpci/plugin/src/Admin/SettingsPage.php)

### 5. Cloudflare upload path loads full files into memory

`CloudflareImagesClient::build_multipart_body()` uses `file_get_contents()` and constructs a full multipart body string in PHP memory.

Impact:

- large images increase memory pressure
- background/bulk sync reliability will degrade first on constrained hosting

This is not an immediate blocker, but it is the main scalability hotspot in the current implementation.

Reference:

- [CloudflareImagesClient.php](/W:/Projects/wpci/plugin/src/Api/CloudflareImagesClient.php#L345)

### 6. Security intake is ad hoc

There is no visible `SECURITY.md` or dedicated reporting policy/contact in the repo.

Impact:

- real reports arrive through random channels
- spam reports are harder to triage consistently
- disclosure expectations are unclear

## Proposed Scope For Next Version

### Must-have

1. Fix README and plugin docs to match shipped behavior.

- update version badge/release links
- replace `wp cfi` with `wp cfimg`
- remove or implement the documented filters
- do a full naming pass for old `cfi_` leftovers in user-facing docs

2. Normalize text encoding across the repository.

- convert broken mojibake strings to plain UTF-8 / ASCII-safe text
- verify wp-admin headings, descriptions, button labels, and readme content

3. Add a security policy.

- add `SECURITY.md`
- define one private contact channel
- define expected report format
- state no public disclosure until validation/patching

4. Add minimal automated tests for critical flows.

- settings POST requires nonce and capability
- mappings POST/AJAX requires nonce and capability
- preview sync action requires nonce and capability
- sync engine basic happy path / empty source / no-op path

### Should-have

5. Add a diagnostics/status page or expand the dashboard widget.

- credentials configured or missing
- last API test timestamp
- Flexible Variants status and last checked time
- Action Scheduler availability
- last sync errors / recent failures

6. Add a bulk sync dry-run summary in admin.

- estimated post count
- selected mapping
- whether queue support is available
- warning when Cloudflare credentials are incomplete

7. Improve logs for support cases.

- clearer success/error summaries
- structured context for attachment ID and Cloudflare image ID where available
- optional filters by level/mapping/post

### Nice-to-have

8. Export/import for presets and mappings.

9. More WP-CLI coverage.

- diagnostics
- dry-run sync summary
- maybe orphan scan / repair helpers

10. Rework upload memory behavior if practical.

- investigate streaming/multipart alternatives compatible with WP HTTP API
- at minimum, document recommended PHP memory expectations for bulk sync

## Implementation Notes

If the documented filters are intended product surface, implement them explicitly rather than silently removing them from the docs. That gives third-party integrators a stable extension story and is likely worth more than another UI feature.

If the next release has limited bandwidth, the best release shape is:

1. docs + encoding cleanup
2. `SECURITY.md`
3. a thin automated test layer for admin/security-critical flows
4. one small operability improvement, preferably diagnostics or bulk dry-run

## Suggested Release Theme

"Trust and operability release"

This version should make the plugin easier to trust, easier to support, and safer to evolve.
