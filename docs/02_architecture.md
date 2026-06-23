# Copot Architecture

## Architecture Overview

copot consists of:

Core

↓

Modules

↓

Themes

---

## Core Responsibilities

The Core System manages:

* Routing
* Configuration
* Authentication
* Permissions
* Database Access
* Module Loading
* Theme Loading

---

## Module Responsibilities

Modules provide business functionality.

Examples:

* Articles
* Catalog
* Workflow
* Assets
* Store

Each module may contain:

* Controllers
* Models
* Views
* Routes
* Services
* Assets

---

## Theme Responsibilities

Themes control presentation only.

Themes may contain:

* Layouts
* Partials
* Assets

Themes must not contain business logic.

---

## Request Lifecycle

Request

↓

Router

↓

Controller

↓

Service

↓

View

↓

Response

---

## Initial Namespace Strategy

Copot\Core

Copot\Modules

Copot\Themes

---

## Initial Database Strategy

PDO-based database layer.

No ORM in initial versions.

---

## Initial Deployment Strategy

Primary Target:

* Shared Hosting

Secondary Target:

* VPS
* Cloud Infrastructure
