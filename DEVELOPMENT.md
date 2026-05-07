# AssemblyPress Development Notes

## Local Environments

AssemblyPress is the plugin/product repo.

It should be built and tested in its own WordPress Studio Local, separate from the marketing site and any marketing block theme work.

Do not add WordPress Studio config, plugin scaffolding, package files, or source folders until the repo shape and first implementation slice are intentionally chosen.

## Repo Boundaries

This repo is for the AssemblyPress plugin.

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

## Skill Routing

Before implementation work, route tasks through the relevant WordPress skills.

Expected routing:

- `wordpress-router` for initial WordPress project classification
- `wp-project-triage` for deterministic repo inspection
- `wp-plugin-development` for plugin structure, lifecycle, settings, security, and packaging
- `wp-block-development` for custom blocks, block metadata, rendering, serialization, and block compatibility
- `wp-block-themes` when testing or shaping Site Editor templates, patterns, and theme behavior
- `wordpress-dataviews` for admin collections, management screens, pickers, CRUD flows, and structured data browsing
- `wpds` for WordPress Design System components, tokens, and UI practices
- `wp-rest-api` for custom REST endpoints and schemas
- `wordpress-core-data` for Gutenberg data flows, entity reads/writes, core-data, block bindings, and editor state

Impeccable may be used for UX shaping, prototype critique, hierarchy, onboarding, and clarity. It should not override WPDS or WordPress-native implementation requirements.

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

## Open Decisions

These decisions remain intentionally open:

- plugin slug
- PHP namespace
- JavaScript package namespace
- GitHub repository name
- custom post type names
- taxonomy names
- REST namespace
- first implementation slice
- exact WordPress Studio Local configuration
- minimum supported WordPress and PHP versions
