# AssemblyPress

AssemblyPress is a block-native community platform for WordPress.

The current product work is focused on an honest Member/Profile Foundation: WordPress users remain the identity layer, while AssemblyPress owns profile schema, profile values, visibility, member profile read/write flows, member directory/profile blocks, and a DataViews/DataForm admin surface for profile fields.

## Product Direction

Public and community surfaces are block-native.

Admin and management surfaces are DataViews-native.

Everything inside the product should feel like modern core WordPress.

## Foundation Docs

- [Product Brief](PRODUCT.md)
- [WPDS Guardrails](WPDS-GUARDRAILS.md)
- [Development Notes](DEVELOPMENT.md)

## Current Status

AssemblyPress now has its first plugin scaffold for native member profiles, profile field groups, profile fields, profile values, visibility, and member directories.

The first implementation slice includes:

- private AssemblyPress config CPTs for profile field groups and profile fields
- registered WordPress user meta for member profile values
- `assemblypress/v1` REST routes for profile fields, profile field groups, members, and the current member profile
- dynamic blocks for member directory, member profile, profile field, and profile editing
- a DataViews/DataForm-powered AssemblyPress admin screen for profile schema
- `@wordpress/scripts`, `wp-env`, PHPCS, PHPUnit bootstrap, and Playwright E2E scaffolding

Profile form configuration is intentionally quarantined until the Jetpack Forms-derived architecture is ready. Forums, topics, replies, queues, transitions, audit events, and moderation capabilities belong to a later Forum architecture build.

Local WordPress development runs through `wp-env` on `http://localhost:8890`.

## Local Development

- `npm run wp-env start` starts the local WordPress environment.
- `composer test` runs PHPUnit inside the `wp-env` `tests-cli` container.
- `composer test:local` is only for manually configured local WordPress test suites with `WP_TESTS_DIR`.
- `composer lint`, `npm run lint:js`, and `npm run build` are the current baseline checks.

## License

AssemblyPress is licensed under the GNU General Public License v2.0 or later. See [LICENSE](LICENSE).
