# NSTU Web API

`nstu-web-api` is the content and presentation API for NSTU's public websites. It sits between:

- `nstu-dashboards`, where Web Curators manage entity-owned content
- `nstu-web`, the main university website
- `nstu-entities-web`, the websites for faculties, departments, institutes, and other entities

The API consumes authoritative identity and structural data from IMS, then stores website-specific content such as static pages, posts, snippets, media assets, and galleries.

## Web Curator Scope

Web Curator write routes are protected by:

- `ims.logged_in_and_role_selected:web_curator`

That middleware resolves the currently selected IMS DB role and injects:

- `current_role_scope`

The scope is treated as the owning entity ID for editor operations.

## Gallery And Media Backend

The media gallery backend is DB-backed and entity-scoped. It separates:

- `media_folders`: curator-facing folder organization
- `media_items`: uploaded reusable media assets
- `galleries`: public-facing published collections
- `gallery_items`: ordered membership of assets inside galleries

Folders are for internal organization. Galleries are for public presentation.

## Public Gallery Endpoints

- `GET /api/galleries?entity_id={id}`
- `GET /api/galleries?entity_id={id}&is_featured=1`
- `GET /api/galleries?entity_id={id}&include_items=1`
- `GET /api/gallery?id={galleryId}`
- `GET /api/gallery?entity_id={id}&slug={gallerySlug}`

Only published galleries are returned publicly.

## Public Entity Site Endpoints

- `GET /api/teachers?entity_id={id}`
- `GET /api/teachers?entity_id={id}&search={term}`
- `GET /api/teachers?entity_id={id}&primary_only=1`
- `GET /api/teachers?entity_id={id}&affiliation_type=Permanent,Contractual`
- `GET /api/teacher?personnel_id={id}`
- `GET /api/teacher?personnel_id={id}&entity_id={entityId}`
- `GET /api/search?entity_id={id}&q={term}`

### Teacher Directory Cache Behavior

The teacher directory is served from cache-first data in:

- `personnels_cache`
- `personnel_affiliations_cache`

When `GET /api/teachers` is requested, the API attempts an on-demand IMS refresh only if the entity's teacher cache is stale. If IMS is temporarily unavailable but cached rows already exist for that entity, the API serves the cached directory instead of failing the request.

The `meta.cache` object in the `GET /api/teachers` response indicates whether the request:

- refreshed from IMS
- was served from cache
- fell back to stale cache because IMS refresh failed

### Entity Search Scope

`GET /api/search` is entity-scoped and returns grouped results for:

- published static pages
- published posts
- people affiliated with the entity

People results are sourced from the affiliation cache and personnel cache, so entity websites can search faculty/staff without querying IMS directly.

## Editor Media And Gallery Endpoints

Protected Web Curator endpoints:

- `GET /api/editor/media/folders`
- `POST /api/editor/media/folders`
- `GET /api/editor/media/folders/{id}`
- `PUT /api/editor/media/folders/{id}`
- `DELETE /api/editor/media/folders/{id}`
- `GET /api/editor/media/items`
- `POST /api/editor/media/items/upload`
- `GET /api/editor/media/items/{id}`
- `PUT /api/editor/media/items/{id}`
- `POST /api/editor/media/items/{id}/move`
- `DELETE /api/editor/media/items/{id}`
- `GET /api/editor/galleries`
- `POST /api/editor/galleries`
- `GET /api/editor/galleries/{id}`
- `PUT /api/editor/galleries/{id}`
- `DELETE /api/editor/galleries/{id}`
- `POST /api/editor/galleries/{galleryId}/items`
- `PUT /api/editor/galleries/{galleryId}/items/reorder`
- `PUT /api/editor/galleries/{galleryId}/items/{itemId}`
- `DELETE /api/editor/galleries/{galleryId}/items/{itemId}`

## Legacy Upload Compatibility

The existing Web Curator upload endpoints remain available:

- `POST /api/media/upload`
- `DELETE /api/media/delete`

They now create and remove `media_items` behind the scenes while preserving the existing response shape used by current dashboard code.

## Schema Notes

See:

- [database/schema_overview.md](database/schema_overview.md)

for the high-level table inventory, including the media and gallery tables.
