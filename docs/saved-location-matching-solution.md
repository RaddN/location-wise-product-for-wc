# Saved Location to Store Location Matching - Recommended Solution

## 1. Problem summary
Current `saved-location-item` selection is still text-dependent, so it breaks when users type:
- small spelling mistakes (`kandirpar` vs `kandir par`)
- alternate spelling (`housing estate` vs `housing-estate`)
- different script/language (`কান্দিরপাড়`)

### Where this currently fails
- `templates/shortcode-selector.php:1325` (`updateCurrentLocation`) splits saved address text and tries exact/contains checks against dropdown option value/text.
- `multi-location-product-and-inventory-management-pro.php:847` (`mulopimfwc_validate_location_slug`) validates exact slug only, so anything not mapped to a real slug is rejected.

Result: user intent is correct, but no store is selected.

## 2. Best solution (recommended architecture)
Use a **single resolver pipeline** that returns one store slug with confidence, instead of direct text comparison.

### Resolution order (important)
1. **Direct deterministic match**
- If input already equals a valid slug, use it.
- If input matches known aliases/synonyms for a store, use that store.

2. **Geo match (primary for saved-location-item)**
- If saved location has `lat/lng` (already available in `data-lat` and `data-lng`), select nearest active store by coordinates.
- Reuse the proximity logic pattern already present in:
  - `multi-location-product-and-inventory-management-pro.php:6124` (`assign_location_by_proximity`)
  - `multi-location-product-and-inventory-management-pro.php:6430` (`calculate_haversine_distance_km`)

3. **Normalized multilingual text match**
- Normalize: lowercase, trim, collapse spaces, remove punctuation/diacritics.
- Transliterate non-Latin scripts to Latin when possible.

4. **Fuzzy match with confidence**
- Score each store by alias/name/address similarity.
- Auto-select only when score is above threshold and clearly better than the second-best candidate.

5. **Ambiguous/low confidence fallback**
- Return top 3 candidates to UI.
- Ask user to pick once, then save that phrase as alias for future auto-match.

This is the best approach because it remains accurate, multilingual, typo-tolerant, and safe.

## 3. What to add in plugin

### A) Shared resolver service (backend)
Create a resolver class (for example `includes/location-resolver.php`) with a method like:

```php
resolve_store_location(array $input): array
// returns: [
//   'status' => 'matched'|'ambiguous'|'not_found',
//   'slug' => 'kandirpar'|null,
//   'confidence' => 0.0-1.0,
//   'candidates' => [...],
//   'reason' => 'geo'|'alias'|'fuzzy'|'none'
// ]
```

Input should accept:
- `lat`, `lng`
- `address`, `city`, `state`, `country`
- optional raw query string

### B) AJAX endpoint
Add endpoint: `mulopimfwc_resolve_store_location`.
- Called by saved-location click flow.
- Returns resolved slug + confidence + candidates.

### C) Alias metadata per store
Add term meta, e.g. `location_aliases` (array/json), including:
- official name
- common misspellings
- local-language names
- transliterated forms

Example for `kandirpar`:
- `kandirpar`
- `kandir par`
- `kandirpur`
- `কান্দিরপাড়`

### D) Frontend integration
In `templates/shortcode-selector.php`:
- Replace current text-token selection in `updateCurrentLocation` (`templates/shortcode-selector.php:1325`) with resolver AJAX call.
- For saved-location click (`templates/shortcode-selector.php:1449`), send:
  - `data-lat`, `data-lng`
  - `data-address`, `data-city`, `data-state`, `data-country`
- On success, set dropdown to returned slug, trigger change/submit.

## 4. Scoring and safety rules
Use strict guardrails to avoid wrong auto-selection.

Suggested defaults:
- `MIN_CONFIDENCE = 0.82`
- `MIN_MARGIN = 0.07` (top score - second score)
- optional `MAX_GEO_RADIUS_KM = 25` (or per-store service radius)

If score is below threshold or margin is too small:
- do not auto-select
- show suggestions

## 5. Reuse existing code (do not duplicate blindly)
You already have proven pieces:
- geocoding with cache: `multi-location-product-and-inventory-management-pro.php:6329`
- haversine distance: `multi-location-product-and-inventory-management-pro.php:6430`
- active location loading: `multi-location-product-and-inventory-management-pro.php:915`

Best move: extract reusable utility methods, then call from both:
- order assignment flow
- saved-location-item flow

## 6. Performance and reliability
- Cache normalized location index in object cache/transient.
- Cache failed geocode lookups (already done in current geocoder pattern).
- Rate-limit resolve endpoint per session to avoid spam.
- Log resolver decisions (`reason`, `confidence`, `input`) for tuning.

## 7. Rollout plan

### Phase 1 (quick win)
- Geo-first resolver for saved-location-item clicks using existing `lat/lng`.
- Keep current text logic only as emergency fallback.

### Phase 2 (quality)
- Add alias metadata + multilingual normalization + fuzzy scoring.
- Add confidence gating and candidate fallback UI.

### Phase 3 (self-improving)
- When user manually picks suggestion, save phrase as alias.
- Build admin report for unresolved/ambiguous phrases.

## 8. Test cases you should run
- `Housing Estate` -> `housing-estate` slug
- `housingestate` -> `housing-estate`
- `Kandirpar`, `Kandir Par`, `kandirpur` -> `kandirpar`
- `কান্দিরপাড়` -> `kandirpar`
- nearby GPS coordinate near Kandirpar -> `kandirpar`
- ambiguous phrase between two stores -> no auto-select, show suggestions
- invalid/noisy text -> no false match

## 9. Final recommendation
Implement **geo-first + alias + fuzzy + confidence guardrails** through one shared resolver service.

This removes exact-match fragility and gives the best long-term behavior for typo tolerance, language variance, and safe automation.
