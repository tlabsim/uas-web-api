# NSTU Web API Database Schema Overview

This document catalogs the current database schema for the **NSTU Web API** project (`nstu-web-api`). The Web API powers NSTU's public-facing websites and consumes authoritative data from the **IMS Core** platform. Many tables duplicate or reference records from the core system to drive website-specific features, caching, and presentation logic.

---

## Directory of Tables

- [Entity Websites](#entity-websites)
  - [`entity_profiles`](#entity_profiles)
  - [`entities_cache`](#entities_cache)
  - [`entity_web_settings`](#entity_web_settings)
  - [`entity_page_categories`](#entity_page_categories)
  - [`entity_page_subcategories`](#entity_page_subcategories)
  - [`entity_static_pages`](#entity_static_pages)
- [Personnel Directory](#personnel-directory)
  - [`personnel_profiles`](#personnel_profiles)
  - [`personnels_cache`](#personnels_cache)
  - [`personnel_web_settings`](#personnel_web_settings)
  - [`personnel_additional_data`](#personnel_additional_data)
  - [`personnel_educations`](#personnel_educations)
  - [`personnel_job_experiences`](#personnel_job_experiences)
  - [`personnel_achievements`](#personnel_achievements)
  - [`personnel_professional_profiles`](#personnel_professional_profiles)
  - [`seminar_workshop_trainings`](#seminar_workshop_trainings)
- [Posts & Media](#posts--media)
  - [`posts`](#posts)
  - [`postmeta`](#postmeta)
  - [`post_attachments`](#post_attachments)
  - [`post_tagged_entities`](#post_tagged_entities)
- [Publications & Research](#publications--research)
  - [`publications`](#publications)
  - [`publication_authors`](#publication_authors)
  - [`publication_meta`](#publication_meta)
  - [`researchers`](#researchers)
  - [`researcher_external_profiles`](#researcher_external_profiles)
  - [`researches`](#researches)
  - [`research_peoples`](#research_peoples)
  - [`research_publications`](#research_publications)
- [Reusable Snippets](#reusable-snippets)
  - [`snippets`](#snippets)
  - [`snippetmeta`](#snippetmeta)
- [Known Migration Issues](#known-migration-issues)

---

## Entity Websites

### `entity_profiles`
- **Purpose:** Seed record for each organizational unit website. Holds per-entity web profile metadata while referencing the authoritative entity in IMS Core (`entities.id`).
- **Key Columns:**
  - `entity_id` *(PK)* – matches the core entity ID; deletes cascade through dependent tables.
  - `head_designation` – references a role in the core `entity_roles` table.
  - `establishment_date`, `slug` – web-specific metadata.
- **Usage Notes:** Acts as the canonical parent key for all entity-facing tables in the Web API.

### `entities_cache`
- **Purpose:** Denormalized snapshot of selected attributes from the core `entities` table for faster reads in the Web API.
- **Key Columns:** Capture names, localized variations, classification, hierarchy, logos, and ordering.
- **Relationships:** `entity_id` FK to `entity_profiles` (cascades).
- **Usage Notes:** Refreshed via background sync from IMS Core.

### `entity_web_settings`
- **Purpose:** Key-value configuration store scoped by entity (branding, theme options, contact info, etc.).
- **Key Columns:**
  - `entity_id` FK to `entity_profiles`.
  - `key_group`, `setting_key`, `value`, `value_type` supporting typed values.

### `entity_page_categories`
- **Purpose:** Defines top-level navigation and content groupings per entity site.
- **Key Columns:**
  - `entity_id` FK to `entity_profiles`.
  - `category_name`, `category_slug` – unique per entity.
  - Menu metadata for building navigation trees.

### `entity_page_subcategories`
- **Purpose:** Optional second-level navigation linked to categories.
- **Key Columns:**
  - `category_id` FK to `entity_page_categories`.
  - `subcategory_name`, `subcategory_slug` – unique within a category.
  - Additional menu metadata mirroring category structure.

### `entity_static_pages`
- **Purpose:** Stores static content pages (About, History, Facilities, etc.) owned by an entity.
- **Key Columns:**
  - `entity_id` FK to `entity_profiles`.
  - `page_slug`, `page_title`, `page_content`, localized metadata, and publication status fields.
  - Optional links to category/subcategory IDs stored as unsigned big integers.
- **Usage Notes:** Soft deletes enabled for archival. Supports publication timestamps and view counts.

---

## Personnel Directory

### `personnel_profiles`
- **Purpose:** Web-facing profile record for each personnel member pulled from IMS Core (`personnels.id`).
- **Key Columns:**
  - `personnel_id` *(PK)* – matches ULID from core.
  - Display information (`display_name`, `display_designation`, bios).
  - Optional link to a `researcher_id` to align web profiles with the research module.

### `personnels_cache`
- **Purpose:** Denormalized cache of personnel details from IMS Core for fast public rendering.
- **Key Columns:** Titles, localized names, designation, status, contact info, employment metadata, and profile photo URL.
- **Relationships:** `personnel_id` FK to `personnel_profiles` (cascades).
- **Usage Notes:** Updated via sync jobs to reflect the core system.

### `personnel_web_settings`
- **Purpose:** Personnel-specific key-value settings (e.g., visibility toggles, custom sections).
- **Key Columns:** `personnel_id` FK to `personnel_profiles`; `value_type` enables typed storage.

### `personnel_additional_data`
- **Purpose:** Flexible free-form data store (structured JSON blobs, lists, etc.) for profile enrichment.
- **Key Columns:** `data_group`, `data_key`, `value` with typed enforcement similar to web settings.

### `personnel_educations`
- **Purpose:** Academic history for personnel profiles.
- **Key Columns:** Degree metadata, institution, awarding body, time span (`start_month_year`, `end_month_year`) and `passing_year`.
- **Relationships:** `personnel_id` FK to `personnel_profiles`.

### `personnel_job_experiences`
- **Purpose:** Employment history records displayed on personnel pages.
- **Key Columns:** `job_title`, `role`, description, organization, and date range.

### `personnel_achievements`
- **Purpose:** Awards and recognitions displayed on profile pages.
- **Key Columns:** Type (`Award`/`Achievement`), title, awarding body, date, and excerpt.

### `personnel_professional_profiles`
- **Purpose:** External professional links (Google Scholar, ResearchGate, LinkedIn, etc.).
- **Key Columns:** `profile_type`, `profile_link` per personnel.

### `seminar_workshop_trainings`
- **Purpose:** Catalogs seminars, workshops, and trainings involving personnel.
- **Key Columns:**
  - `personnel_id` FK to `personnel_profiles`.
  - Event metadata: attendee role (`attendee_type` set), `type` (Seminar/Workshop/Training), descriptive fields, and date range.

---

## Posts & Media

### `posts`
- **Purpose:** Generic content items such as notices, news, and events for the public site.
- **Key Columns:**
  - `category` enumerations handled via string values (e.g., `Notice`).
  - `owner_entity_id` references the originating entity (intended to map to IMS Core `entities.id`).
  - Publication metadata, featured image, tags, view counts, and soft deletes.

### `postmeta`
- **Purpose:** Extensible metadata for posts (e.g., event schedules, attachments metadata, content flags).
- **Key Columns:** `post_id` FK to `posts`, typed `meta_value` with soft deletes.

### `post_attachments`
- **Purpose:** File attachments associated with posts.
- **Key Columns:** `post_id` FK, attachment title, and URI (file path or external link).

### `post_tagged_entities`
- **Purpose:** Many-to-many join linking posts to entities for contextual tagging and approval workflow.
- **Key Columns:**
  - `post_id` FK to `posts`.
  - `entity_id` FK to `entity_profiles`.
  - Status workflow (`Pending Approval`, `Approved`, `Withdrawn`) and approver tracking.

---

## Publications & Research

### `publications`
- **Purpose:** Bibliographic entries tied to NSTU personnel and research outputs.
- **Key Columns:** Title, excerpt, description, `publication_date`, type enumeration (journal article, thesis, etc.), link URL, keywords, soft deletes.

### `publication_authors`
- **Purpose:** Authors per publication with ordering and display flags.
- **Key Columns:**
  - `publication_id` FK to `publications`.
  - `author_name`, optional `internal_author_id` (expected to map to `personnel_profiles` or IMS core), external profile link, ordering (`sl`), editor flags.

### `publication_meta`
- **Purpose:** Additional metadata for publications (DOI, ISSN, indexing information).
- **Key Columns:** `publication_id` FK, `meta_key`, `meta_value`.

### `researchers`
- **Purpose:** Master records for researchers (may aggregate multiple personnel identities or aliases).
- **Key Columns:**
  - `rpid` – optional string identifier similar to ORCID.
  - Alternate author names and research interests.

### `researcher_external_profiles`
- **Purpose:** External identifiers for researchers (Scopus, Google Scholar, ORCID, etc.).
- **Key Columns:** `researcher_id` FK, `profile_type`, optional `profile_id`, and `profile_link`.

### `researches`
- **Purpose:** Individual research projects or initiatives featured on NSTU websites.
- **Key Columns:** Title, excerpt, description, featured image URI, keywords, status (`Ongoing`/`Completed`).

### `research_peoples`
- **Purpose:** Associates people with research projects.
- **Key Columns:**
  - `research_id` FK to `researches`.
  - `internal_researcher_id` optional FK to `researchers`.
  - Role metadata, ordering (`sl`), editor flags, external profile link.

### `research_publications`
- **Purpose:** Junction table connecting research projects to publications.
- **Key Columns:** Composite unique key on (`research_id`, `publication_id`), optional cached title and link, timestamped creation.

---

## Reusable Snippets

### `snippets`
- **Purpose:** Reusable content fragments (e.g., homepage hero, quick facts) that can belong to an entity or be global.
- **Key Columns:** `slug` (unique), optional `entity_id`, name, content body, tags, publication status, timestamps, and soft deletes.

### `snippetmeta`
- **Purpose:** Flexible metadata for snippets (e.g., layout hints, scheduling data).
- **Key Columns:** `snippet_id` FK, `meta_key`, typed `meta_value`, with soft deletes.

---

## Migration Notes

- `0006_create_entity_static_pages_table.php`, `0020_create_publication_authors_table.php`, and `0021_create_publication_meta_table.php` previously contained typos in their `foreignId` definitions. These have now been corrected in source control so fresh migrations will run without syntax errors.
- When adding new tables, keep foreign keys aligned with IMS Core identifiers to preserve referential integrity across the platform.

---

## Integration with IMS Core

- **Entity Sync:** `entity_profiles` and `entities_cache` mirror `entities` and related tables from the IMS Core to support lightweight read operations without joining across databases.
- **Personnel Sync:** `personnel_profiles` and `personnels_cache` mirror `personnels` records, enabling rich public profiles while respecting updates from the core system.
- **Role References:** Several fields store IDs from core tables (`head_designation`, `owner_entity_id`, `internal_author_id`), reinforcing the single source of truth in the IMS Core while allowing the Web API to layer presentation-specific metadata.

This overview should be kept in sync with future migration changes to maintain an accurate picture of the Web API schema.
