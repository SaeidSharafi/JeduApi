# Jalali Dates in Request DTOs

Glob: `app/Data/**`

Every date supplied by an API client is Jalali. This applies to mutation, query, filter, and report request DTOs; there are no Gregorian request-date exceptions. Persist and compare dates internally as Gregorian, and return dates to clients as Jalali.

## Mutation input pipeline

- In create, update, store, and delete Data classes, convert Jalali input to Gregorian in `prepareForPipeline()` with `JalaliDateHelper::toGregorian()` before validation.
- Declare every converted field explicitly beside the DTO. Use dot notation for nested fields and map fields to their input format when it is not `Y-m-d`.
- Pair each converted field with `bail`, `ValidNormalizedJalaliDateRule`, and Laravel's Gregorian `date_format` rule. This preserves distinct errors for malformed input and impossible Jalali calendar dates.
- Use Laravel's data-aware comparison rules on the normalized field names: `after`, `after_or_equal`, `before`, `before_or_equal`, and `date_equals`. Reference the other field by name; do not concatenate request values into rule strings.
- When a property remains typed as `Carbon`, cast the normalized value with Spatie's `DateTimeInterfaceCast` using the Gregorian format expected by the property.
- Query, filter, and report DTOs must also document and validate every client date as Jalali, even when their existing conversion mechanism differs from mutation DTOs.
- Keep Gregorian-to-Jalali response transformation in response DTOs. Request normalization does not belong in response casts.

## Delivery-option detail dates

`ProductDeliveryOptionDateNormalizer` is the single list of top-level and `details.*` date fields normalized by both `ProductDeliveryOptionCreateData` and `ProductDeliveryOptionUpdateData`.

When adding, renaming, or removing a delivery-option detail date:

1. Update both `ProductDeliveryOptionDateNormalizer::FIELDS` and `ProductDeliveryOptionDateNormalizer::rules()` in the same change.
2. Add the corresponding Gregorian-to-Jalali cast/transformer to the response detail DTO.
3. Cover create and update round trips: the database JSON stores Gregorian and the API response returns Jalali.

The change is complete when valid Jalali input survives the full round trip, invalid Jalali calendar dates receive `validation.invalid_jalali_date`, malformed values receive the format message, and comparisons operate on Gregorian values.
