# AssemblyPress Product Brief

## Strategic Ambition

AssemblyPress is a block-native community platform for WordPress.

The MVP is a Member Directory that helps people find other members and view their profiles. Profile maintenance and membership administration support that experience. WordPress users provide identity; AssemblyPress owns profile schema, values, directory presentation, and joining workflows.

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

The current implementation is the Member Directory MVP.

In scope now:

- WordPress users as identity
- AssemblyPress profile field groups
- AssemblyPress profile fields and options
- AssemblyPress-owned profile values
- field visibility
- public member profile reads
- current-member profile writes
- member directory/profile blocks
- DataViews/DataForm admin for members, profile schema, invitations, and membership requests
- registration, email activation, invitations, and owner approval
- owner settings for access, eligibility, and directory filters

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

## Workflow references

BuddyPress is a reference for member administration, profiles, and joining workflows. AssemblyPress expresses these workflows through current WordPress components, DataViews, blocks, and templates.

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

## First Viable Version

The release target is a usable member directory with owner administration, registration, activation, invitations, and membership requests. Owners can define profile fields, manage members, choose access rules, and compose directory/profile patterns. Members can join, maintain their information, find others, and return from profiles to their search results.

Quarantined for later architecture:

- spaces or groups
- forums and discussions
- topic and reply views
- activity or updates
- notifications
- moderation queues
- broader onboarding beyond membership registration
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

### Editable member surfaces and maintenance

The Member Directory lists subscribers by default. Its **Include all site accounts** setting is an explicit opt-in to listing other roles, including administrators. Directory eligibility controls discovery, not authorization to visit a profile URL. Search matches public display names only. The public REST member collection follows directory eligibility and audience settings. Authorized administrative selection can include other site accounts.

Site editors can compose the repeated directory card with nested core blocks and Profile Field blocks. Leave member selection on **Automatic member context** for shared layouts. The **Member profile with editable fields** pattern combines a member heading with individually editable fields. Schema remains in AssemblyPress's DataViews interface; layout remains in the Site Editor.

Member maintenance is a fixed schema adapter, not a form builder. It uses the same field definitions and validation as REST profile updates. A future Jetpack Forms-derived renderer can replace this adapter without migrating profile values or introducing another schema. Members cannot override the owner's field visibility in this slice. Public fields are visible to visitors; private fields are available only to the member and authorized editors. Empty optional fields can be left blank.

Profile images use a restricted frontend upload handler: authenticated self-editing, nonce verification, JPEG/PNG/WebP content checks, a 2 MB limit (or the host's lower upload limit), and a 4096-pixel dimension limit. This does not grant access to the media library or the general upload capability. Images are publicly accessible media, so private image fields cannot accept uploads. Removing a profile image clears the association without deleting shared media; failed saves delete only files uploaded in that request. Validation failures keep submitted text in the current request, rather than putting private values in URLs or persistent draft storage. File selections must be chosen again after an error.

The enhanced member image control uses WordPress components for upload, replacement, removal, and Undo. Image changes are staged alongside other profile fields until **Save profile**; selecting a replacement displays a local preview. The native file field remains available without JavaScript.

### Owner workflows and design freedom

AssemblyPress opens on **Members**. Its DataViews list defaults to directory members; removing the Directory filter reveals all site accounts, with an explanation of role-based exclusion. Owners can edit profile values through DataForm, including owner-only fields, or open the public profile or WordPress account.

**Profile Fields** retains the schema editor. **Invitations and requests** manages joining records. **Settings** controls joining mode, directory/profile audience, eligible roles, page destinations, and public select-field filters. **Design** links to native page, template, and pattern editors.

The Member Directory pattern contains independent Search and Filters, Result Count, Results, Empty State, and Pagination blocks. Owners can rearrange these through core layout blocks, design the repeated card, choose its column count, and create their own patterns. Profile Field blocks support labels, linked names/images, and name heading levels. Member Navigation provides a local return link that retains the directory query. Existing card-only directory layouts continue to render.

### Joining and access

Joining defaults to closed. Owners can choose open registration, invitation-only access, or email verification followed by approval. Invitations remain an explicit owner action in every mode. Registration uses a fixed block adapter, not a separate form builder.

Pending joining records are private `ap_membership` posts, separate from WordPress identities. An applicant receives a one-day verification link; an invitation expires after seven days. Links contain random tokens stored only as hashes and require an explicit confirmation. Resending rotates the token. Verified requests await approval; approved applicants and invitation recipients receive subscriber accounts and WordPress password-setup emails. No pending account can log in or appear in the directory because no WordPress user exists yet.

Owners can approve, reject, revoke, and resend according to the record's state. Public recovery does not disclose whether an address has an account or a request. Email attempts are rate-limited per recipient, and joining submissions are rate-limited per client address. Owners must configure a working WordPress mail service; acceptance by the mail service does not prove inbox delivery.

After membership settings are saved, core public WordPress registration directs applicants through AssemblyPress instead of bypassing its approval policy. Other plugins that create users require separate integration review. Existing WordPress accounts retain access and are not retroactively required to verify email.

Directories and profiles are public by default. Profiles restricted to signed-in members also restrict directory access. Directory role exclusion does not make direct profile URLs private. Field visibility continues to apply, and public media URLs are not made private by these settings. Members cannot independently override owner-defined visibility in this release.
