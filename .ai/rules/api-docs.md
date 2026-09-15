# API Docs — Scribe
Glob: `app/Http/Controllers/Api/**`, `app/Data/**`

## Body vs query parameters — detection
Custom strategy `App\Scribe\Extracting\Strategies\BodyParameters\GetFromLaravelData` decides per Data class:
- No docblock on the Data class → treated as body parameters.
- Docblock contains "query parameters" (case-insensitive) OR the class defines a `queryParameters()` method → treated as query parameters, NOT body.
- Otherwise → body parameters.

So: a Data class meant for query params (GET requests) MUST be marked as such — either "Query parameters" in its class docblock or a `queryParameters()` method. Without that marker, Scribe documents it as a body parameter even on a GET endpoint. The marker only picks the strategy; the class still needs `queryParameters()` to emit any fields (see below).

## bodyParameters()/queryParameters() are required on every request Data class
Scribe's `GetFromLaravelData` strategies document a Data-class request **only when the class defines `bodyParameters()`** (or `queryParameters()` for GET requests) — the strategy bails out otherwise and the endpoint is documented with no fields at all, however simple `rules()` is. A verified empty example: a Data class with three required `rules()` and no `bodyParameters()` produced `bodyParameters: []`.

Once the method exists, `rules()` still supplies the field list plus type, requiredness and enum values, so a field present in `rules()` is documented even when the method omits its entry — but with no description. Write an entry for every field the request accepts. Common triggers that also need extra care (not exhaustive, and the fix varies with the actual shape of the class):
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
- Method: short action description, plus `@bodyParam`/`@queryParam` **only when no Data class carries the request**:
  - **The request is a Data class** (`public function __invoke(SomeData $data, ...)` / `public function store(BundleCreateData $data, ...)`) → no `@bodyParam`/`@queryParam` in the controller docblock. The Data class is the single source of truth: its `bodyParameters()`/`queryParameters()` documents the fields, so a request Data class **must** define that method. Never add the fields on the controller.
  - **There is no Data class** — typically an `index()` that reads `request()` directly, or a route/query-param-only endpoint → `@queryParam` (GET) / `@bodyParam` (write) on the method is required, because nothing else documents those parameters.

