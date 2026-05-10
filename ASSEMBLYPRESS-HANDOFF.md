# AssemblyPress Handoff

Date: 2026-05-09

## Repository

- Repo: `https://github.com/dhanson-wp/assemblypress`
- Local path: `/Users/derekhanson/Documents/New project`
- Current branch: `feat/member-profiles-scaffold`
- Branch naming convention requested by Derek: `feat/...`

## Current State

The plugin scaffold exists and should be treated as a first technical spike, not a final UX direction.

Implemented primitives include:

- Main plugin bootstrap: `assemblypress.php`
- Internal classes under `includes/`
- Internal config entity CPTs:
  - `ap_profile_field_grp`
  - `ap_profile_field`
  - `ap_profile_form`
- REST namespace: `assemblypress/v1`
- Initial routes:
  - `/profile-fields`
  - `/profile-field-groups`
  - `/profile-forms`
  - `/members`
  - `/members/<id>`
  - `/me/profile`
- Dynamic blocks:
  - `assemblypress/member-directory`
  - `assemblypress/member-profile`
  - `assemblypress/profile-field`
  - `assemblypress/edit-profile-form`
- Tooling:
  - `@wordpress/scripts`
  - `wp-env`
  - Playwright scaffold
  - PHPCS
  - PHPUnit scaffold

## Important Correction

Historical note: an earlier admin UI at:

`http://localhost:8890/wp-admin/admin.php?page=assemblypress`

was not acceptable as a product direction.

It was a reverted, plain wp-admin scaffold after failed attempts to imitate the Site Editor. It was also not using DataViews in the way Derek expects.

The current direction is now a real DataViews/DataForm-powered wp-admin screen. Continue from the native WordPress package work, not from the old custom shell.

## Product Direction

Derek wants AssemblyPress admin screens to use real WordPress-native interfaces and patterns, especially DataViews/DataForm and core Gutenberg package behavior. The next thread should study and reference public core code and visuals before implementing.

Good references to study first:

- Gutenberg repo:
  - `packages/edit-site`
  - `packages/dataviews`
  - `packages/components`
  - `packages/interface`
  - `packages/core-data`
- WordPress Developer Blog DataViews/DataForm examples
- The live Site Editor UI in `wp-admin/site-editor.php`
- Any current Make/Core posts about admin redesign and DataViews
- Brian Coords' `bacoords/wp-content-types` as product/field-editor guidance, especially `src/content-type-editor/components/fields/FieldEditorModal.js`, `FieldsDataView.js`, and field config files. Borrow patterns, not wholesale architecture.
- Secure Custom Fields as a maturity benchmark for field-type behavior and edge cases, not as a codebase to fork wholesale.

## Current Admin Architecture

The AssemblyPress admin app now lives in:

- Source: `assets/src/admin/index.js`
- Styles: `assets/src/admin/style.css`
- Built assets: `assets/build/admin/`
- Enqueue/root: `includes/class-admin.php`

Key architectural decisions:

- Import DataViews/DataForm from `@wordpress/dataviews/wp` in plugin context.
- Keep DataViews/DataForm controlled by local React state; REST remains the persistence boundary.
- Keep wp-admin chrome and native component styling. Do not imitate Site Editor chrome.
- Use DataViews for browsing profile fields and DataForm for add/edit modals.
- Profile field records persist as internal config entity CPT posts with JSON `post_content` and `menu_order`.
- Select field options are edited as one line per option in the modal and saved as structured `[{ value, label }]`.
- Field ordering currently uses DataViews row actions, `Move up` and `Move down`, backed by `menu_order`. Drag and drop is a later refinement after the ordering model settles.
- User-backed default identity fields (`display_name`, `bio`, `website`) are protected system fields. REST responses expose `is_system`; updates preserve their key/type/source/user mapping; deletes are blocked. Admins may still tune label, description, visibility, editable, required, and order.

## Current Admin Behavior

Implemented:

- Real DataViews table for Profile Fields.
- Search, selection checkboxes, bulk delete action, row actions, view options.
- Create/edit/delete profile fields through REST.
- Select field options editor using `value|Label` lines.
- Up/down ordering actions.
- System badges and protected actions for user-backed identity fields.
- White full-screen wp-admin canvas with DataViews alignment fixes.

Recent browser verification:

- Editing existing `Preferred Contact` select field opens an Options section.
- Saving options closes the modal without console errors.
- Moving `Preferred Contact` down reorders it after `Display name`.

Known test data:

- A browser-created field `Preferred Contact` with key `prefer_contact` may exist in the local wp-env database.

## Next Refinement Priorities

1. Introduce field groups as a first-class admin concept; Derek called out that groups will be important for organizing profile fields.
2. Revisit the current Status column; it communicates system/custom protection but feels awkward in the table and may need a different treatment.
3. Add a better field type picker with WordPress icons and clearer type categories.
4. Decide whether field ordering should graduate from up/down actions to drag and drop.
5. Add field-type-specific validation/defaults beyond select options.
6. Expand automated REST/PHP coverage around admin permissions and profile value updates.

## Lessons From This Thread

- Do not copy or fake Site Editor chrome.
- Do not create a custom visual system.
- Do not call fallback HTML tables “DataViews.”
- Do not use private/unstable APIs unless Derek explicitly approves.
- If a core pattern is not available publicly, say so clearly and choose an honest wp-admin fallback.
- Study real code references before making UI changes.
- Use Brian Coords' `wp-content-types` as a useful product reference for content modeling and field editing, while preserving AssemblyPress' member-profile-specific data model.
- Use gstack QA continuously while refining browser-visible behavior.

## Current Browser Observation

Derek selected the old admin page and commented:

> Not dataviews.

That was correct at the time. The current screen is now a real DataViews/DataForm implementation and should be reviewed as the starting point for refinement.

## Verification Last Run

After the select-options and ordering refinement:

- `npm run lint:js` passed
- `npm run build` passed
- `composer lint` passed

Build still reports expected webpack asset-size warnings for the admin bundle.

## Gstack Skills

Copied from Claude skills into Codex agent skills:

- Source: `/Users/derekhanson/.claude/skills/gstack/.cursor/skills/`
- Target: `/Users/derekhanson/.agents/skills/`

Also present:

- `/Users/derekhanson/.agents/skills/gstack/SKILL.md`

Fresh threads should be able to discover `gstack-context-save`, `gstack-context-restore`, and related `gstack-*` skills.

## Suggested Next Thread Prompt

Start by saying:

> Read `ASSEMBLYPRESS-HANDOFF.md`. Do not implement yet. Study core WordPress DataViews/DataForm and Site Editor code references, then propose the correct AssemblyPress admin architecture.
