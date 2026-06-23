# Copot Architecture

## Architecture Overview

copot consists of three primary layers:

```text
Core
↓
Modules
↓
Themes
```

---

## Core Layer

The Core layer provides infrastructure services.

Responsibilities:

* Application Bootstrap
* Configuration Management
* Routing
* Authentication
* Permissions
* Database Access
* Module Loading
* Theme Loading
* Request Lifecycle Management

Example:

```text
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
```

---

## Module Layer

Modules provide business functionality.

Examples:

```text
Articles
Catalog
Workflow
Assets
Store
```

Each module should remain as self-contained as possible.

A module may contain:

```text
module.json
routes.php

Controllers/
Models/
Views/
Services/
Assets/
Migrations/
```

---

## Theme Layer

Themes provide presentation.

A theme may contain:

```text
theme.json

layouts/
partials/
assets/
```

Themes are responsible for:

* Layouts
* Templates
* Styling
* Assets

Themes are not responsible for:

* Database Access
* Business Logic
* Authentication Logic

---

## Namespace Strategy

Initial namespace structure:

```text
Copot\Core
Copot\Modules
Copot\Themes
```

Example:

```php
Copot\Core\Application
Copot\Core\Router
Copot\Core\Database
```

---

## Database Strategy

Initial implementation:

```text
PDO
```

The framework should provide a lightweight database layer.

Initial versions should not include:

* ORM
* Active Record
* Repository Framework

These may be evaluated in future milestones.

---

## Configuration Strategy

Configuration should be separated from code.

Planned locations:

```text
config/
.env
```

The framework should support environment-specific configuration.

---

## Deployment Strategy

Primary Target:

* Shared Hosting
* cPanel Hosting

Secondary Target:

* VPS
* Cloud Infrastructure

All architectural decisions should consider compatibility with the primary deployment target first.

---

## Future Expansion Areas

Future milestones may introduce:

* Event System
* Queue System
* API Layer
* Background Jobs
* Package Ecosystem

These features are not part of M1.1.
