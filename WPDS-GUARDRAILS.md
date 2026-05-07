# AssemblyPress WPDS Guardrails

## Product UI Authority

The actual AssemblyPress plugin UI must follow WordPress Design System practices.

Impeccable may shape flows, critique hierarchy, improve onboarding, clarify empty states, and help define the product experience. WPDS wins for product UI implementation.

Marketing pages may have their own brand system. Plugin UI may not inherit marketing styling unless it is expressed through WordPress-native patterns.

## Core Rule

Everything inside the product should feel completely core WordPress.

Use:

- WordPress components for admin and product UI
- DataViews for collections and management surfaces
- DataForm or WordPress component patterns for editing records and settings
- Gutenberg-native controls for block UI
- Site Editor-compatible templates, patterns, and blocks
- WordPress-native notices, permissions, loading states, empty states, and error states

Avoid:

- bespoke admin tables
- legacy `WP_List_Table` as the default product experience
- custom React component libraries that duplicate WordPress components
- marketing-style panels inside plugin admin UI
- shortcode-first product surfaces
- layout systems that bypass blocks and templates
- UI that treats Gutenberg as only an editor instead of the product platform

## Admin and DataViews

Admin and management surfaces are DataViews-native.

Use DataViews for structured collections, including members, spaces, discussions, moderation queues, reports, settings collections, and any browsable community records.

Use WordPress components for controls, buttons, notices, panels, menus, tabs, modals, confirmations, forms, filters, and layout primitives.

Use DataForm or established WordPress component patterns for record editing, settings editing, and detail views.

Admin screens should feel aligned with the Site Editor and the next-generation WordPress admin direction, not like old settings pages with custom tables.

## FSE and Blocks

Public and community surfaces are block-native.

Community pages should be assembled through:

- custom blocks
- block patterns
- block templates
- template parts
- theme-aware styling
- native inspector controls
- block supports where appropriate
- dynamic rendering when community data must stay fresh

A designer should be able to design a community experience in Figma and have developers translate it into WordPress-native blocks, patterns, templates, and Site Editor surfaces.

## Forums by Default

Forums are a default product primitive.

AssemblyPress should not require a separate forum plugin to deliver core community value. Forums should integrate with spaces, profiles, moderation, notifications, templates, and blocks as part of one coherent product.

## Block Development Rules

Blocks should:

- use `block.json`
- use `apiVersion: 3`
- register from metadata in PHP
- use `useBlockProps()` in the editor
- use `useBlockProps.save()` for static saved markup
- use `get_block_wrapper_attributes()` for dynamic PHP rendering
- support editor iframe compatibility
- prefer dynamic rendering for community data where saved markup would become stale
- preserve backward compatibility with deprecations when saved markup changes
- use native Gutenberg controls and block supports before custom controls

## Data and State Rules

Prefer WordPress-native data flows:

- REST-exposed entities where appropriate
- `@wordpress/core-data` for entity reads and writes
- block bindings for supported data connections
- DataViews controlled state for collection screens
- native permissions and capability checks
- nonces and capability checks for mutations
- sanitized input and escaped output

Do not create a separate JavaScript app data model when WordPress entities, REST APIs, and core-data can own the state.

## Review Checklist

Before shipping plugin UI, confirm:

- the surface feels WordPress-native
- WPDS components and tokens are used where available
- admin collections use DataViews unless there is a documented reason not to
- blocks work in the editor and frontend
- Site Editor customization is respected
- saved block markup does not become invalid
- admin screens support empty, loading, error, and permission states
- forums are integrated into the community model, not bolted on
- Impeccable-shaped UX decisions are expressed through WPDS and WordPress-native implementation patterns
