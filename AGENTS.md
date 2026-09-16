# AssemblyPress Agent Instructions

AssemblyPress is a public WordPress plugin repository for a block-native community platform.

## Current Product Slice

The active implementation slice is the Member Directory MVP:

- WordPress users provide identity.
- Registration, email activation, invitations, membership requests, and owner administration support directory membership. Pending joining records do not create WordPress accounts until verified and approved.
- AssemblyPress owns profile field groups, profile fields, field options, profile values, visibility, member profile read/write flows, member directory/profile blocks, and the DataViews/DataForm admin surface for profile schema.
- Profile values currently use registered WordPress user meta.
- Profile schema currently uses private AssemblyPress config CPTs for profile field groups and profile fields.

Profile forms are quarantined until the Jetpack Forms-derived architecture is ready.

Forums, topics, replies, queues, transitions, audit events, and moderation capabilities belong to a later Forum architecture build.

## Public Repo Hygiene

Do not commit private session notes, local handoffs, internal conversation summaries, local absolute paths, generated scratch artifacts, or agent-specific operational state.

Use public-facing project docs for durable guidance:

- `README.md`
- `PRODUCT.md`
- `DEVELOPMENT.md`
- `WPDS-GUARDRAILS.md`
- `AGENTS.md`

Local-only files such as `ASSEMBLYPRESS-HANDOFF.md`, `LOCAL-HANDOFF.md`, `.agent-local/`, `.codex/`, `.claude/`, `.cursor/`, `.agents/`, and `.gstack/` are intentionally ignored.

## Work Rules

- Inspect the working tree before editing.
- Preserve existing user or agent changes unless explicitly asked to replace them.
- Keep changes scoped to the active slice unless the task explicitly widens scope.
- Prefer WordPress-native APIs, Gutenberg public package APIs, and core UI grammar.
- Do not use private or unstable Gutenberg APIs unless the decision is documented.
- Keep frontend output theme-led and avoid heavy plugin styling unless the product need is proven.
- Keep admin collections DataViews-native.
- Treat the temporary profile value editor as a testing surface, not the final Forms architecture.

## Verification

Run the relevant checks before reporting completion:

```bash
composer lint
composer test
npm run lint:js
npm run build
```

For browser-visible work, smoke test the affected wp-admin and frontend surfaces in `wp-env`.
