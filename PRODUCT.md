# AssemblyPress Product Brief

## Strategic Ambition

AssemblyPress is a block-native community platform for WordPress.

Its goal is to become the primary open-source community plugin for WordPress: a modern, unified successor to BuddyPress and bbPress that treats forums, member spaces, profiles, moderation, and community management as one coherent WordPress-native product.

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

## Forums by Default

Forums are a first-class default product primitive.

AssemblyPress should not require a separate forum plugin to deliver core community value. Structured discussion belongs inside the community model from the beginning, alongside members, spaces, moderation, notifications, templates, and blocks.

The goal is not to clone bbPress. The goal is to make structured community discussion feel native to modern WordPress.

## Full Site Editing Thesis

The community should be designed in the Site Editor.

Community home pages, member directories, profiles, spaces, discussion archives, topic views, and onboarding surfaces should be composed with blocks, patterns, and templates.

When a designer creates a community experience in Figma, developers should be able to translate that work into native and custom blocks, block patterns, template parts, theme.json decisions, and Gutenberg controls. AssemblyPress should make modern WordPress the design and implementation surface for the community.

## Modern WordPress Admin Thesis

AssemblyPress should not recreate legacy WordPress admin screens.

All plugin management surfaces should move toward the modern WordPress admin direction: DataViews, WordPress components, core-data patterns, and interfaces aligned with the Site Editor and next-generation admin work.

Admin screens should feel like modern WordPress product surfaces, not old settings pages with custom tables.

Use DataViews for collections, moderation queues, members, spaces, discussions, reports, and any structured management surface. Use DataForm or WordPress component patterns for editing records and settings.

## Strategic Anti-References

BuddyPress and bbPress are the main strategic anti-references.

Avoid:

- dated admin and front-end experiences
- fragmented plugin setup
- unclear boundaries between profiles, groups, activity, and forums
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

The first version should prove the core community loop:

1. A site owner can launch a community.
2. Members can join and have an identity.
3. Members can participate in structured discussions.
4. The site owner can shape the experience in the Site Editor.
5. Moderators can keep the community healthy.

Everything else should support that loop.

Long-term product surfaces may include:

- community home
- member profiles
- member directory
- spaces or groups
- forums and discussions
- topic and reply views
- activity or updates
- notifications
- moderation queues
- onboarding and setup
- admin settings
- Site Editor templates, patterns, and blocks

## Success Criteria

AssemblyPress succeeds when a WordPress user can:

- launch a complete community without assembling multiple legacy plugins
- use forums as a default part of the community model
- customize community pages in the Site Editor
- use blocks and patterns instead of shortcode-driven layouts
- manage members, discussions, spaces, and moderation with modern WordPress UI
- recognize the product as WordPress-native, not a SaaS iframe or custom admin island
- see BuddyPress and bbPress as legacy tools by comparison, not because AssemblyPress is flashy, but because it is clearer, more coherent, and more native to modern WordPress
