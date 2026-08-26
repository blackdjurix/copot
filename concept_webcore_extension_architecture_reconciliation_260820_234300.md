# Copot Webcore & Extension Architecture Reconciliation

Date version: 2026-08-20 23:43:00 WIB
Status: PROVISIONAL CONCEPT / DISCUSSION-LOCKED / NOT YET PROMOTED
Supersedes: `concept_webcore_extension_architecture_reconciliation_260820_120500.md`

## 1. Purpose

This Concept records the reconciled architecture direction required before MR.2 may continue beyond accepted WU1-WU2.

This work is a Post-M3 architecture workstream, not MR.2 UI refinement. It changes Webcore capability ownership, Bundled Module disposition, extension relationships, installation assumptions, presentation fallback behavior, and baseline-versus-advanced capability boundaries.

MR.2 WU3 onward remains on HARD HOLD until this architecture workstream closes.

## 2. Core Architecture Principles

> Webcore owns complete minimum viability and survival.
>
> Modules add optional capability, depth, specialization, scale, interoperability, or domain behavior.
>
> Platform provides reusable machinery.
>
> Integration may share contracts and compose capabilities, but architecture ownership remains singular.
>
> The Webcore baseline must not be intentionally crippled merely to justify an advanced Module.

A richer UI alone does not justify a Module if it merely takes over a Webcore-owned capability.

## 3. Architecture Vocabulary

### 3.1 Webcore

The complete built-in product baseline that remains useful with zero optional Modules and zero Themes.

### 3.2 Platform

Reusable internal machinery used by Webcore and extensions. Platform is architecture vocabulary, not necessarily a user-facing Admin surface.

### 3.3 Extension

Umbrella term for installable or contributable expansion.

### 3.4 Module

Primary executable capability extension.

### 3.5 Bundled Module

A Module distributed with COPOT.

Bundled does not imply mandatory, installed, or enabled.

### 3.6 Theme

Optional frontend presentation extension. A Theme may replace presentation behavior through the controlled presentation contract but does not own business logic, database querying, authentication, permissions, or application lifecycle.

### 3.7 Contribution

Bounded participation in a capability owned by Webcore or another extension through an explicit contract, registry, provider interface, event, or extension point.

### 3.8 Resource Package

Possible non-executable package type for resource-oriented capability such as a Language Pack.

### 3.9 Plugin

No separate first-class Plugin runtime/package type is currently justified.

## 4. Extension Relationship Model

Supported relationship semantics:

- `EXTENDS`
- `PROVIDES`
- `CONSUMES`
- `ENHANCES`
- `CONTRIBUTES`
- `INTEROPERATES`
- `AGGREGATES / COORDINATES`

Relationship semantics do not imply shared ownership.

Avoid implicit takeover, private-schema coupling, circular hard dependencies, provider-specific coupling where a capability contract suffices, and generic global registries without concrete consumers.

Generic provider arbitration is not adopted by this Concept. Narrow/domain-owned registries remain preferred until multiple concrete domains prove a generic need.

## 5. Webcore Minimum Viability

With zero optional Modules installed and zero Themes installed or active, COPOT remains a usable site/admin system.

Target Admin Shell baseline:

1. Dashboard
2. Content
3. Media
4. Navigation
5. System

Current User Profile remains outside main navigation and is reached from the profile/avatar surface.

## 6. Built-in Public View and Theme Boundary

Webcore does not own a Theme.

Webcore owns the **Built-in Public View**, an always-available public presentation fallback.

Built-in Public View baseline:

- responsive;
- homepage;
- general page presentation;
- fixed/unmodifiable structural presentation;
- navigation/header/body/footer;
- bounded optional structural regions where justified;
- baseline Color Scheme and Font;
- consumes Webcore Site Identity including Logo and Favicon.

Presentation resolution:

- compatible active Theme available -> Theme presentation;
- no active Theme -> Built-in Public View;
- safe controlled Theme unavailability -> Built-in Public View fallback where technically valid.

Theme Manager remains a Bundled Module that manages optional Theme extensions.

## 7. Webcore Capability Baseline

### 7.1 Dashboard

Webcore-owned baseline operational overview.

### 7.2 Content

Webcore owns baseline Content state and capability:

- Page / Article baseline;
- content persistence;
- slug/public identity;
- draft/published/archived lifecycle;
- basic authorship;
- basic Admin list/create/edit/publish/archive;
- basic Media reference;
- public Content delivery contract;
- normalized render data.

Public presentation is not owned by Content.

Flow:

Request -> Webcore Routing -> Webcore Content delivery -> normalized render data -> Presentation Resolver -> Built-in Public View or Theme.

Content Manager is optional and `EXTENDS` Webcore Content. Rich text/editor functionality is a valid future Content Manager advancement but is not implementation scope of this architecture extraction workstream.

### 7.3 Media

Webcore owns baseline Media capability:

- upload;
- validation;
- safe storage;
- stable identity/reference;
- basic metadata;
- controlled delivery;
- simple selection;
- usage/reference awareness;
- safe deletion.

Media Manager `EXTENDS` Webcore Media with advanced library, image processing, variants, crop/resize/rotate, and richer management.

### 7.4 Navigation

Webcore owns one complete primary navigation capability:

- ordered items;
- bounded hierarchy;
- custom URL targets;
- Webcore Content targets;
- Built-in Public View consumption.

Navigation Manager `EXTENDS` Webcore Navigation with multiple menus, Theme locations, assignment, broader target providers, richer visibility, and advanced management.

Baseline hierarchy must not be intentionally crippled merely to justify Navigation Manager.

### 7.5 Routing, Slugs, and Redirects

These are distinct concerns.

- Routing: Webcore internal request-dispatch infrastructure.
- Slug/public URL identity: owned by the applicable domain, e.g. Content.
- Redirects: Webcore native capability for explicit source-address to target-address redirection.

`Addressing` is not retained as a required umbrella product term.

Redirect Manager is retired as a standalone package identity.

### 7.6 Taxonomy

Webcore has no taxonomy system by default.

Taxonomy semantics and state are Module-owned by default.

Examples:

- Content Manager owns Content category/tag semantics and state.
- Catalog owns Catalog classification semantics and state.

Structural similarity does not imply shared taxonomy identity.

Platform may provide reusable taxonomy primitives only where implementation evidence proves meaningful reuse. Shared taxonomy identity is an explicit exception requiring a concrete cross-module semantic-identity need.

Taxonomy Manager is a retirement candidate with high confidence. Cross-domain inventory/reconciliation/consolidation alone is not sufficient independently valuable capability to justify survival.

### 7.7 Identity and Current User

Webcore provides first-administrator/current-user baseline.

Initial installation may require only username and password. Email is optional for initial viability.

Current User Profile remains separate from Users & Access.

### 7.8 Settings Platform

Settings definitions, typed values, registry, persistence, validation, and transactional writes remain Platform machinery.

Settings Manager standalone Module is retired.

### 7.9 Modules

Module lifecycle is a permanent Webcore/System Manager responsibility.

Candidate operations include discover/install, enable, disable, update, repair, uninstall, dependency/conflict/compatibility state.

Module Manager standalone Module is retired.

### 7.10 System Health

System Health remains Webcore/System Manager-owned. Optional Modules may contribute bounded evidence through explicit contracts.

## 8. System Manager

Product-facing name remains **System Manager**.

Candidate tabs:

1. System
2. Site
3. Security
4. Email
5. Modules
6. System Health

`Site` is a subsection, not the parent manager identity.

Logo and Favicon belong to Webcore Site Identity.

## 9. Bundled Module Disposition

Current candidate final dispositions:

- Module Manager -> RETIRE.
- Settings Manager -> RETIRE; Settings Platform remains.
- Redirect Manager -> RETIRE; Redirects become Webcore-native.
- Taxonomy Manager -> RETIRE candidate with high confidence.
- Content Manager -> RETAIN; `EXTENDS` Webcore Content.
- Media Manager -> RETAIN; `EXTENDS` Webcore Media.
- Navigation Manager -> RETAIN; `EXTENDS` Webcore Navigation.
- Theme Manager -> RETAIN.
- Users & Access -> RETAIN.
- Form Manager -> RETAIN.

## 10. Independent Capability Axes

Retained advanced Bundled Modules remain independently installable where practical.

Required-valid examples:

- basic Content + basic Media;
- advanced Content + basic Media;
- basic Content + advanced Media;
- advanced Content + advanced Media;
- baseline Navigation without Navigation Manager;
- Built-in Public View without Theme or Theme Manager.

## 11. Installation Semantics

Fresh installation requires **zero mandatory optional Modules and zero mandatory Themes**.

This does not mean the installer must produce an empty extension set.

The installer may offer explicit choices to:

- not install a Bundled Module;
- install but leave disabled;
- install and enable;
- install/select a Theme when available.

Built-in Public View remains the guaranteed presentation fallback whenever no Theme is active.

Bundled does not imply installed or enabled.

## 12. Development-Stage Migration and Runtime Policy

COPOT has no live production site using the current development architecture.

For this Architecture Reconciliation workstream, explicitly scoped destructive cleanup of obsolete development schema/data is allowed when technically cleaner.

This development migration policy does not define future normal Module uninstall/delete semantics.

Package retirement and data cleanup are separate decisions.

The existing `C:\xampp\htdocs\copot.test` runtime is disposable development state.

At workstream closure, after required acceptance evidence is complete, the old runtime instance/state may be disposed. If the same path remains the main development runtime, recreate it as a clean installation from the final authoritative `main`.

## 13. MR.2 Relationship

MR.2 WU1 and WU2 remain accepted and closed.

MR.2 WU3 onward remains HARD HOLD.

After Architecture Reconciliation closure:

1. regenerate MR.2 WU3+ topology;
2. remove retired Manager refinement targets;
3. retain surviving Bundled Module refinement only against the reconciled ownership model;
4. place rich-text/editor advancement under Content Manager refinement unless later evidence proves a separate cross-cutting workstream is required;
5. reconcile the consolidated refinement Concept where necessary.

## 14. Candidate Work Unit Topology

### WU1 — Architecture Contract & Ownership Reconciliation

Lock Webcore minimum viability, Platform/Webcore boundaries, extension model, relationship semantics, Bundled Module dispositions, taxonomy rule, installer semantics, development-stage migration policy, and target ownership rules.

### WU2 — Built-in Public View & Theme Decoupling

Provide Built-in Public View, presentation resolution, Theme-optional public rendering, Site Identity/appearance baseline, and Theme fallback behavior.

### WU3 — Webcore Content Extraction

Move baseline Content authority to Webcore, establish baseline Admin and public delivery contracts, preserve presentation separation, and convert Content Manager into an optional extension over Webcore Content.

Rich-text/editor implementation is excluded.

### WU4 — Webcore Media Extraction

Move baseline Media authority to Webcore and retain advanced Media Manager processing/library behavior.

### WU5 — Webcore Navigation & Redirects Extraction

Move primary Navigation and Redirects baseline capability to Webcore and retain advanced Navigation Manager functionality.

### WU6 — Bundled Module & Installer Reconciliation

Retire obsolete package identities, reconcile retained Bundled Modules/contributions, permissions, Admin navigation, package/schema/files, and implement zero-mandatory-Module/Theme installer semantics.

### WU7 — Cross-Lifecycle Acceptance & Architecture Closure

Prove fresh baseline viability, Built-in Public View fallback, independent retained Module enable/disable behavior, lifecycle/update/repair coherence, ownership enforcement, documentation consistency, final development-runtime policy, and MR.2 continuation inputs.

## 15. Naming Reconciliation Policy

Current naming is provisionally sufficient.

Do not create a separate naming workstream now.

Perform focused naming reconciliation near NRP Candidate only if implementation evidence exposes misleading, conflicting, or obsolete vocabulary.

## 16. Promotion Boundary

This file is a planning Concept.

It does not itself authorize:

- repository mutation;
- Codex implementation;
- architecture promotion into repository authority;
- installer mutation;
- destructive runtime/database action;
- Module retirement execution;
- release/tag/publication.

Promotion and each execution slice remain subject to normal governance and explicit authorization boundaries.