# Vision

copot is a modular PHP-based website framework designed to support websites, reusable platform capabilities, core management modules, business applications, commerce, and automation use cases.

The framework should be able to grow from a lightweight website foundation into a broader application platform without forcing every installation to include every capability or module.

## Supported Directions

copot may support use cases such as:

* Company Profile
* Blog
* News Portal
* Knowledge Base
* Catalog
* Property
* Booking
* CRM
* Inventory
* Project Management
* Workflow-driven applications
* Commerce
* Digital asset workflows
* Internal management applications

These are possible use cases, not mandatory built-in features.

## Architecture Direction

copot evolves through distinct architectural layers:

```text
Core Infrastructure
+
Platform Capabilities
+
Core Modules
+
Business / Application Modules
+
Themes
```

### Core Infrastructure

Provides the minimum runtime foundation, including bootstrap, configuration, routing, persistence access, security primitives, module lifecycle, and theme lifecycle.

### Platform Capabilities

Provide reusable services, contracts, registries, adapters, processing, and extension points.

Examples include:

* Admin UI Foundation
* Extensibility Foundation
* Editor Framework
* Media Foundation
* Image Service
* Navigation Foundation
* Search Foundation
* Notification Foundation
* Workflow / Automation Foundation

### Core Modules

Provide reusable first-party administrative and management functionality without representing one specific business domain.

Examples include:

* Users & Access
* Settings Manager
* Media Library
* Theme Manager
* Content Manager / Workspace
* Taxonomy Manager
* Navigation Manager
* Internal Dashboard

### Business / Application Modules

Provide domain-specific functionality that is installed only when required.

Examples include:

* Catalog
* Property
* Booking
* CRM
* Inventory
* Project Management
* Physical or Business Asset Management

### Themes

Provide frontend presentation without owning business logic, persistence, authentication, or module lifecycle behavior.

## Deployment Direction

The framework should remain deployable on:

* Shared Hosting
* VPS
* Cloud Infrastructure

Shared hosting remains the primary compatibility baseline unless an approved milestone explicitly changes that direction.

## Long-Term Goal

Create a flexible modular platform that can evolve from a simple website into production and commercial applications while preserving clear boundaries between infrastructure, reusable capabilities, management modules, business domains, commerce, and ecosystem concerns.
