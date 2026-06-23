# Copot Principles

## Purpose

copot is a modular website framework designed to support multiple business and content use cases through a shared core system.

The framework should remain lightweight, extensible, and maintainable.

---

## Core Philosophy

The framework is built around three primary layers:

Core System

*

Modules

*

Themes

The Core System provides infrastructure.

Modules provide business functionality.

Themes provide presentation.

---

## Separation of Concerns

Business logic must never exist inside themes.

Themes must never directly access database resources.

Modules must remain independent whenever possible.

---

## Documentation First

Architecture decisions should be documented before implementation.

Documentation is considered part of the source code.

---

## Modular First

Every major feature should be implemented as a module whenever practical.

Examples:

* Articles
* Catalog
* Workflow
* Assets
* Store

---

## Shared Hosting First

The framework must be deployable on:

* Shared Hosting
* cPanel Hosting

without requiring advanced server configuration.

---

## Progressive Scalability

A project should be able to grow from:

Simple Website

to

Business Platform

without rebuilding the entire system.

---

## Technology Principles

Backend:

* PHP

Database:

* MySQL
* MariaDB

Frontend:

* Bootstrap

---

## Long-Term Goal

Provide a flexible platform that can support websites, portals, automation systems, and business applications from a single architecture.
