# AssemblyPress Development Notes

## Local Environments

AssemblyPress is the plugin/product repo.

Local WordPress development runs through `wp-env`, not WordPress Studio. The repo's `.wp-env.json` maps this directory into `wp-content/plugins/assemblypress`, serves the development site at `http://localhost:8890`, serves the tests environment at port `8999`, and activates AssemblyPress in both the `cli` and `tests-cli` containers after start.

Use the current repo shape as the active AssemblyPress plugin scaffold. Do not add WordPress Studio config or parallel local-environment setup unless the project intentionally changes away from `wp-env`.

Common local commands:

- `npm run wp-env start` starts the WordPress development and test containers.
- `npm run wp-env stop` stops the containers.
- `npm run build` builds the admin bundle.
- `npm run lint:js` lints JavaScript sources.
- `composer lint` runs PHPCS.
- `composer test` runs PHPUnit inside the `wp-env` `tests-cli` container.
- `composer test:local` runs plain `phpunit` and is only for a manually configured local WordPress test suite with `WP_TESTS_DIR`.

## Repo Boundaries

This repo is for the AssemblyPress plugin.

The current first implementation slice is the Member/Profile Foundation: WordPress users remain the identity layer while AssemblyPress owns profile field groups, profile fields, field options, profile values, visibility, member profile read/write flows, member directory/profile blocks, and the DataViews/DataForm admin surface for profile schema.

In scope:

- plugin product documentation
- plugin architecture
- WordPress admin and management UI
- block-native public/community surfaces
- custom blocks, patterns, templates, and plugin-provided community primitives
- REST, core-data, DataViews, and WordPress-native state flows

Out of scope for this repo:

- marketing website docs
- marketing `DESIGN.md`
- marketing block theme files
- campaign pages
- brand system implementation outside the plugin

## Agent Workflow

Agent work should stay source-grounded, public-safe, and scoped to the active product slice.

Before implementation work:

- Inspect the repo and current working tree first.
- Use WordPress-native APIs and public Gutenberg package contracts before custom abstractions.
- Keep implementation notes, local prompts, session summaries, screenshots, and agent scratch files out of version control unless they are intentionally public project documentation.
- Do not commit local machine paths, private conversation summaries, or agent-specific operational notes.
- Run the documented checks before reporting work as complete.

Profile forms are quarantined until the Jetpack Forms-derived architecture is ready. Forums, topics, replies, queues, transitions, audit events, and moderation capabilities belong to the later Forum architecture build.

## Before Code Changes

Before creating or changing plugin code:

1. Confirm this is the plugin repo, not the marketing repo.
2. Run WordPress project triage once the repo has code or scaffolded structure.
3. Identify the target surface: public block surface, Site Editor surface, admin DataViews surface, REST/data layer, or plugin lifecycle.
4. Load the relevant skill instructions for that surface.
5. Check `PRODUCT.md` and `WPDS-GUARDRAILS.md` for product and implementation constraints.

## Verification Expectations

Verification should match the surface being changed.

For plugin structure:

- plugin activates without fatals or notices
- activation, deactivation, and uninstall behavior is explicit and safe
- capabilities, nonces, sanitization, and escaping are handled correctly

For blocks:

- block appears in the inserter
- block inserts, saves, reloads, and renders without invalid markup
- editor and frontend assets load where expected
- dynamic rendering is used when community data should stay current

For admin UI:

- collection views use DataViews unless a reason is documented
- UI uses WordPress components
- loading, empty, error, and permission states are handled
- bulk actions, filters, sorting, and pagination behave predictably when present

For data flows:

- REST endpoints have schemas and permission callbacks
- core-data is used where WordPress entities can own the state
- mutations enforce capability checks and nonces
- input is sanitized and output is escaped

Profile schema management currently uses `AssemblyPress\Capabilities::manage_profile_schema()`, which maps to `list_users` as a temporary first-slice permission. Replace that mapping with an AssemblyPress-specific capability before expanding the permission model.

Current storage:

- Profile schema is stored in private AssemblyPress config CPTs for profile field groups and profile fields.
- Profile values are stored in registered WordPress user meta.
- This storage description is the current Member/Profile Foundation path, not a storage decision for later forum, moderation, onboarding, account-state, or broader community architecture.

## Current REST Contract

The active profile schema REST contract is intentionally small:

- `GET /assemblypress/v1/profile-fields` reads the profile field collection.
- `POST /assemblypress/v1/profile-fields` creates a profile field.
- `PUT /assemblypress/v1/profile-fields/<id>` updates a profile field.
- `DELETE /assemblypress/v1/profile-fields/<id>` deletes a profile field.
- `GET /assemblypress/v1/profile-field-groups` reads the profile field group collection.
- `POST /assemblypress/v1/profile-field-groups` creates a profile field group.
- `PUT /assemblypress/v1/profile-field-groups/<id>` updates a profile field group.
- `DELETE /assemblypress/v1/profile-field-groups/<id>` deletes an empty profile field group.

Single-item read routes for `/profile-fields/<id>` and `/profile-field-groups/<id>` are deferred until a real current consumer needs them. The admin app currently reads collections and uses item routes only for mutations.

## PHPUnit

PHPUnit runs through the `wp-env` tests container so the WordPress test suite and test database are provided by the repo's `.wp-env.json` configuration.

- Start the environment with `npm run wp-env start` when needed.
- Run PHP tests with `composer test`; this executes PHPUnit inside `wp-env`'s `tests-cli` container.
- If you have a separate local WordPress test suite configured, `composer test:local` still runs plain `phpunit` and respects `WP_TESTS_DIR`.

## Remaining Setup Decisions

The active repo name, plugin slug, PHP namespace, JavaScript package namespace, REST namespace, and first implementation slice are no longer open setup questions. Remaining setup decisions are narrower:

- formal minimum supported WordPress version
- formal release support policy for PHP beyond the current Composer constraint
- whether later, out-of-scope community entities need taxonomies
