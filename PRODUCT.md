# AssemblyPress Product Brief

## Strategic Ambition

AssemblyPress is a block-native community platform for WordPress.

Its long-term goal is to become the primary open-source community plugin for WordPress. The current implementation slice is narrower: a Member/Profile Foundation built on WordPress users for identity and AssemblyPress-owned profile schema, values, visibility, directory, profile, and editing surfaces.

AssemblyPress should make WordPress feel like the best place to build an owned community again.

## Product Thesis

WordPress already has the primitives for modern community software: users, roles, posts, comments, taxonomies, blocks, templates, patterns, REST APIs, DataViews, and the Site Editor.

AssemblyPress turns those primitives into a coherent community platform.

Instead of rebuilding community software outside WordPress, AssemblyPress should make community feel like a natural extension of modern WordPress itself.

## Product Architecture

Public and community surfaces are block-native.

Admin and management surfaces are DataViews-native.

This split is foundational. The front of the community should be composed with blocks, patterns, templates, and theme-aware styling. The management experience should use modern WordPress admin patterns, WordPress components, DataViews, DataForm, core-data, and REST-backed entities where appropriate.

AssemblyPress should avoid shortcode-first thinking, isolated custom app shells, and legacy admin screens as default product architecture.

## Current Slice

The first real implementation slice is the Member/Profile Foundation.

In scope now:

- WordPress users as identity
- AssemblyPress profile field groups
- AssemblyPress profile fields and options
- AssemblyPress-owned profile values
- field visibility
- public member profile reads
- current-member profile writes
- member directory/profile blocks
- DataViews/DataForm admin for profile schema

Profile form configuration is experimental until the Jetpack Forms-derived architecture is ready.

## Full Site Editing Thesis

The community should be designed in the Site Editor.

Member directories and profiles should be composed with blocks, patterns, and templates.

When a designer creates a community experience in Figma, developers should be able to translate that work into native and custom blocks, block patterns, template parts, theme.json decisions, and Gutenberg controls. AssemblyPress should make modern WordPress the design and implementation surface for the community.

## Modern WordPress Admin Thesis

AssemblyPress should not recreate legacy WordPress admin screens.

All plugin management surfaces should move toward the modern WordPress admin direction: DataViews, WordPress components, core-data patterns, and interfaces aligned with the Site Editor and next-generation admin work.

Admin screens should feel like modern WordPress product surfaces, not old settings pages with custom tables.

Use DataViews for profile schema collections and future structured management surfaces. Use DataForm or WordPress component patterns for editing records and settings.

Forum, moderation, and audit management surfaces belong to the later Forum architecture build, not the current Member/Profile Foundation.

## Strategic Anti-References

BuddyPress and bbPress are the main strategic anti-references.

Avoid:

- dated admin and front-end experiences
- fragmented plugin setup
- unclear boundaries between profiles and later forum/community primitives
- excessive configuration before value appears
- UI that feels like legacy wp-admin instead of modern WordPress
- shortcode-first layouts as the primary product surface
- custom app experiences that ignore Gutenberg, blocks, DataViews, and the Site Editor

## Primary Users

AssemblyPress is for:

- site owners who want an owned community instead of renting attention from social platforms
- WordPress agencies building community sites for clients
- designers creating community experiences in Figma and the Site Editor
- developers extending community features with blocks, REST APIs, templates, and WordPress hooks
- community managers who need moderation, clarity, and daily operating workflows
- open-source contributors who want a credible modern community platform for WordPress

## Product Shape

The first foundation version should prove the member/profile loop:

1. WordPress users act as member identity.
2. Site owners can define profile groups and fields.
3. Members can read and update their profile values.
4. Visitors can browse a member directory and view member profiles.
5. The site owner can shape member/profile surfaces with blocks.

Everything else should support that loop.

Quarantined for later architecture:

- spaces or groups
- forums and discussions
- topic and reply views
- activity or updates
- notifications
- moderation queues
- onboarding and setup
- transitions and audit events

## Success Criteria

AssemblyPress succeeds when a WordPress user can:

- define profile groups and profile fields
- let members edit AssemblyPress-owned profile values
- publish member directory and profile surfaces with blocks
- use blocks and patterns instead of shortcode-driven layouts
- manage profile schema with modern WordPress UI
- recognize the product as WordPress-native, not a SaaS iframe or custom admin island
- see a credible path toward a clearer, more coherent, and more native WordPress community product
