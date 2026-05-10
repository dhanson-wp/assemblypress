# AssemblyPress

AssemblyPress is a block-native community platform for WordPress.

Its goal is to become the primary open-source community plugin for WordPress: a modern, unified successor to BuddyPress and bbPress that treats forums, member spaces, profiles, moderation, and community management as one coherent WordPress-native product.

## Product Direction

Public and community surfaces are block-native.

Admin and management surfaces are DataViews-native.

Everything inside the product should feel like modern core WordPress.

## Foundation Docs

- [Product Brief](PRODUCT.md)
- [WPDS Guardrails](WPDS-GUARDRAILS.md)
- [Development Notes](DEVELOPMENT.md)

## Current Status

AssemblyPress now has its first plugin scaffold for native member profiles, profile fields, profile forms, and member directories.

The first implementation slice includes:

- private AssemblyPress profile field, field group, and form configuration entities
- registered user meta for member profile values
- `assemblypress/v1` REST routes for fields, forms, members, and the current member profile
- dynamic blocks for member directory, member profile, profile field, and edit profile form
- an AssemblyPress admin screen with members, profile fields, and profile forms tabs
- `@wordpress/scripts`, `wp-env`, PHPCS, PHPUnit bootstrap, and Playwright E2E scaffolding

Local WordPress development runs through `wp-env` on `http://localhost:8890`.

## License

AssemblyPress is licensed under the GNU General Public License v2.0 or later. See [LICENSE](LICENSE).
