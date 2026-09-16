# AssemblyPress

AssemblyPress is a block-native community platform for WordPress.

AssemblyPress helps people join a member directory, find other members, and view and maintain profiles. Site owners manage members and profile fields through DataViews and design the public experience with blocks, templates, and patterns.

## Product Direction

Public and community surfaces are block-native.

Admin and management surfaces are DataViews-native.

Everything inside the product should feel like modern core WordPress.

## Foundation Docs

- [Product Brief](PRODUCT.md)
- [WPDS Guardrails](WPDS-GUARDRAILS.md)
- [Development Notes](DEVELOPMENT.md)

## Current Status

AssemblyPress includes member administration, profile schema and values, configurable directory access, and membership registration, activation, invitations, and approval workflows.

The MVP focuses on discovering members and viewing their profiles. Groups, posts, forums, and conversations remain future work.

The first implementation slice includes:

- private AssemblyPress config CPTs for profile field groups and profile fields
- registered WordPress user meta for member profile values
- `assemblypress/v1` REST routes for profile fields, profile field groups, members, and the current member profile
- dynamic blocks for member directory, member profile, profile field, and profile editing
- a DataViews/DataForm-powered AssemblyPress admin screen for profile schema
- `@wordpress/scripts`, `wp-env`, PHPCS, PHPUnit bootstrap, and Playwright E2E scaffolding

Profile form configuration is intentionally quarantined until the Jetpack Forms-derived architecture is ready. Forums, topics, replies, queues, transitions, audit events, and moderation capabilities belong to a later Forum architecture build.

## Member destinations

Every site member has a profile, subject to configured access rules, at `/members/{user_nicename}/` and an owner-only editing destination at `/members/{user_nicename}/edit/`. With plain permalinks, AssemblyPress generates equivalent query-string URLs. WordPress author archives remain independent. These member paths are reserved by the plugin; no page per member is needed.

Block themes can customize **Member profile** and **Edit member profile** in the Site Editor's Templates screen. The shared templates use the theme's header, footer, and layout. Classic themes receive a minimal profile surface between their header and footer.

Profile and profile-field blocks resolve an explicit member selection first, then `assemblypress/memberId` block context, the queried author/member, and finally the signed-in member on ordinary pages. Invalid explicit members and unknown member routes never fall back to the viewer. Unknown routes return 404, and another member's edit destination returns 403 for signed-in visitors.

Public profile images use the configured `avatar` field through WordPress's avatar APIs, including directory/profile blocks and REST avatar URLs. Missing, private, deleted, non-image, or privately attached images retain the WordPress avatar fallback. This controls rendered visibility; it does not make previously uploaded media URLs private. Members can upload or remove a profile image directly on their editing page. JPEG, PNG, and WebP uploads are limited to 2 MB and 4096 pixels per side. The form explains field requirements and visibility, and preserves entered text after validation errors.

Member Directory cards support nested core and Profile Field blocks, named member selection, display-name search, and pagination. Directories list subscribers by default; editors can explicitly include all site accounts. Use the **Member profile with editable fields** pattern to compose a shared profile layout.

Local WordPress development runs through `wp-env` on `http://localhost:8890`.

## Set up a directory

1. Open **AssemblyPress → Profile Fields** to define the information members share.
2. Create a page using the **Member directory** pattern. Arrange search, results, cards, counts, empty states, and pagination with core layout blocks.
3. Create a page using **Member Registration** if people should be able to join or request access.
4. In **AssemblyPress → Settings**, select the pages, joining mode, audience, eligible roles, and optional public-field filters.
5. Use **Members** to manage profiles and **Invitations and requests** to invite people or review verified applications.
6. Open **Design** to edit shared profile templates or manage reusable patterns. Block themes support shared template editing; classic themes retain their header/footer fallback.

Joining is closed until configured. Activation links expire after one day; invitations expire after seven days. Accounts are created only after email verification and any required approval. Configure and test WordPress email delivery before inviting members. Password setup and recovery use WordPress's standard account flow.

Owners control layout through blocks and patterns, field definitions through Profile Fields, and behavior through Settings. Updates preserve saved layouts. New composable blocks coexist with earlier card-only directories.

## Local Development

- `npm run wp-env start` starts the local WordPress environment.
- `composer test` runs PHPUnit inside the `wp-env` `tests-cli` container.
- `composer test:local` is only for manually configured local WordPress test suites with `WP_TESTS_DIR`.
- `composer lint`, `npm run lint:js`, and `npm run build` are the current baseline checks.
- `npm run playground:demo` starts a disposable WordPress Playground demo using `blueprints/first-viable-version.json`.

## License

AssemblyPress is licensed under the GNU General Public License v2.0 or later. See [LICENSE](LICENSE).
