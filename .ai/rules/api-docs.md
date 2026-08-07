# API Docs — Scribe
Glob: `app/Http/Controllers/Api/**`, `app/Data/**`

## Body vs query parameters — detection
Custom strategy `App\Scribe\Extracting\Strategies\BodyParameters\GetFromLaravelData` decides per Data class:
- No docblock on the Data class → treated as body parameters.
- Docblock contains "query parameters" (case-insensitive) OR the class defines a `queryParameters()` method → treated as query parameters, NOT body.
- Otherwise → body parameters.

So: a Data class meant for query params (GET requests) MUST either mention "Query parameters" in its class docblock or implement `queryParameters()`. Without one of those two, Scribe documents it as a body parameter even on a GET endpoint.

## When bodyParameters()/queryParameters() must be written manually
Scribe infers from `rules()` automatically in the simple case. That inference breaks down whenever the Data class's structure is too complex or irregular for it to reconstruct correctly — don't assume automatic inference works, verify the generated docs for anything non-trivial. Common triggers (not exhaustive, and the fix varies with the actual shape of the class):
- Fields are array-of-objects — Scribe can't generate examples for `field.*.subfield` on its own; every sub-field needs its own explicit entry.
- Rules or parameter definitions are pulled in from another Data class (nested Data object, composed/merged rule sets, conditional rule sets built at runtime, etc.) — however that composition happens in the given class, the automatic strategy generally can't see through it, so the resulting fields need to be written out explicitly.
- A field's meaning needs business context `rules()` can't express (enum semantics, valid ranges in prose, etc).

There's no single required shape for the manual method — write whatever `bodyParameters()`/`queryParameters()` array correctly documents that particular class's actual fields. Check sibling Data classes for how similar cases were handled before inventing a new pattern, and always verify against the generated Scribe output rather than assuming the method is correct once it compiles.

## Never duplicate URL-bound parameters
Do not add a route-bound parameter (anything already captured via `{param}` in the route) to `bodyParameters()` or `queryParameters()`. Scribe documents route parameters automatically — adding them manually creates duplicate entries in the generated docs.

## @responseFile — manual by necessity, not preference
Response examples are hand-written files, not factory/demo-data generated — the data model is too interconnected for factories to produce realistic example responses. This is why `@responseFile` is mandatory rather than left to Scribe's auto-generation.
- Path: `resources/responses/<scope>/<resource>/<action>.json`
  - `<scope>`: `admin` | `shop`
  - `<resource>`: lowercase folder matching the resource — never place files directly under `<scope>`
  - `<action>.json`: `index.json` for collections; `show.json` for store/show/update
- Delete methods (204 No Content) → no `@responseFile`.

## Docblocks
- Class: `@group <name>`. Add `@authenticated` if the endpoint requires auth.
- Method: short action description only. No `@bodyParam`/`@queryParam` — the Data class's `bodyParameters()`/`queryParameters()` (or automatic inference) is the single source of truth.
