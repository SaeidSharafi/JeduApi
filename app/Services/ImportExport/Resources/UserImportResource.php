<?php

declare(strict_types=1);

namespace App\Services\ImportExport\Resources;

use App\Contracts\ImportExport\ImportResourceContract;
use App\Data\ImportExport\ImportRowResult;
use App\Data\ImportExport\SpreadsheetColumnDefinition;
use App\Enums\ImportExport\ImportIdentityKeyEnum;
use App\Enums\ImportExport\SpreadsheetResourceEnum;
use App\Enums\ProvisioningProviderEnum;
use App\Enums\User\CivilIdTypeEnum;
use App\Enums\User\EducationLevelEnum;
use App\Enums\User\EducationStatusEnum;
use App\Enums\User\GenderEnum;
use App\Helpers\JalaliDateHelper;
use App\Helpers\PersianDigitHelper;
use App\Helpers\PhoneNumberHelper;
use App\Models\User;
use App\Rules\CivilIdRule;
use App\Rules\IranMobilePhoneRule;
use App\Rules\UniqueCivilIdRule;
use App\Rules\ValidNormalizedJalaliDateRule;
use App\Services\ImportExport\HeadingNormalizer;
use BackedEnum;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

/**
 * User import contract.
 *
 * Owns every User specific column, rule, identity decision and provider
 * capability; the central engine never sees a User field.
 */
final readonly class UserImportResource implements ImportResourceContract
{
    /** Columns mapped to User attributes. */
    private const array FIELD_KEYS = [
        'phone', 'email', 'first_name', 'last_name', 'phone2', 'civil_id', 'civil_id_type',
        'date_of_birth', 'father_name', 'gender', 'education_level', 'field_of_study', 'education_status',
    ];

    private const array TRUE_VALUES = ['1', 'true', 'yes', 'y', 'on', 'بله', 'آری', 'اره'];

    private const array FALSE_VALUES = ['0', 'false', 'no', 'n', 'off', 'خیر', 'نه'];

    /** Stable row error codes the frontend branches on. */
    private const string CODE_REQUIRED = 'required';

    private const string CODE_CONFLICT = 'identity_conflict';

    private const string CODE_INVALID = 'invalid';

    public function __construct(
        private HeadingNormalizer $headingNormalizer,
    ) {}

    public function resource(): SpreadsheetResourceEnum
    {
        return SpreadsheetResourceEnum::USERS;
    }

    /**
     * @return list<SpreadsheetColumnDefinition>
     */
    public function columns(): array
    {
        return [
            new SpreadsheetColumnDefinition(
                key: 'phone',
                headingKey: 'imports.columns.phone',
                aliases: ['mobile', 'mobile number', 'mobile phone', 'phone number', 'تلفن', 'موبایل', 'همراه', 'شماره تلفن', 'شماره موبایل', 'تلفن همراه'],
                required: true,
                example: '09123456789',
                guidanceKey: 'imports.guidance.phone',
            ),
            new SpreadsheetColumnDefinition(
                key: 'email',
                headingKey: 'imports.columns.email',
                aliases: ['email address', 'mail', 'ایمیل', 'رایانامه', 'پست الکترونیک', 'پست الکترونیکی'],
                required: false,
                example: 'user@example.com',
                guidanceKey: 'imports.guidance.email',
            ),
            new SpreadsheetColumnDefinition(
                key: 'first_name',
                headingKey: 'imports.columns.first_name',
                aliases: ['firstname', 'given name', 'name', 'نام', 'نام کوچک'],
                required: true,
                example: 'علی',
                guidanceKey: 'imports.guidance.first_name',
            ),
            new SpreadsheetColumnDefinition(
                key: 'last_name',
                headingKey: 'imports.columns.last_name',
                aliases: ['family', 'family name', 'lastname', 'surname', 'فامیل', 'نام خانوادگی'],
                required: true,
                example: 'محمدی',
                guidanceKey: 'imports.guidance.last_name',
            ),
            new SpreadsheetColumnDefinition(
                key: 'phone2',
                headingKey: 'imports.columns.phone2',
                aliases: ['phone 2', 'secondary phone', 'تلفن دوم', 'موبایل دوم', 'تلفن همراه دوم'],
                required: false,
                example: '09120000000',
                guidanceKey: 'imports.guidance.phone2',
            ),
            new SpreadsheetColumnDefinition(
                key: 'civil_id',
                headingKey: 'imports.columns.civil_id',
                aliases: ['civil id', 'national id', 'national code', 'کد ملی', 'کد شناسایی', 'شماره ملی'],
                required: true,
                example: '0000000000',
                guidanceKey: 'imports.guidance.civil_id',
            ),
            new SpreadsheetColumnDefinition(
                key: 'civil_id_type',
                headingKey: 'imports.columns.civil_id_type',
                aliases: ['civil id type', 'id type', 'نوع شناسه', 'نوع کد شناسایی', 'نوع کد ملی'],
                required: true,
                example: CivilIdTypeEnum::NATIONAL_CODE->value,
                guidanceKey: 'imports.guidance.civil_id_type',
            ),
            new SpreadsheetColumnDefinition(
                key: 'date_of_birth',
                headingKey: 'imports.columns.date_of_birth',
                aliases: ['birth date', 'birthdate', 'birthday', 'dob', 'تاریخ تولد', 'تاریخ تولد شمسی'],
                required: true,
                example: '1370-01-01',
                guidanceKey: 'imports.guidance.date_of_birth',
            ),
            new SpreadsheetColumnDefinition(
                key: 'father_name',
                headingKey: 'imports.columns.father_name',
                aliases: ['father', 'نام پدر'],
                required: true,
                example: 'حسن',
                guidanceKey: 'imports.guidance.father_name',
            ),
            new SpreadsheetColumnDefinition(
                key: 'gender',
                headingKey: 'imports.columns.gender',
                aliases: ['sex', 'جنس', 'جنسیت'],
                required: true,
                example: GenderEnum::MALE->value,
                guidanceKey: 'imports.guidance.gender',
            ),
            new SpreadsheetColumnDefinition(
                key: 'education_level',
                headingKey: 'imports.columns.education_level',
                aliases: ['education', 'مقطع تحصیلی', 'سطح تحصیلات', 'مقطع'],
                required: false,
                example: EducationLevelEnum::BACHELOR->value,
                guidanceKey: 'imports.guidance.education_level',
            ),
            new SpreadsheetColumnDefinition(
                key: 'field_of_study',
                headingKey: 'imports.columns.field_of_study',
                aliases: ['field', 'رشته تحصیلی', 'رشته'],
                required: false,
                example: 'مهندسی کامپیوتر',
                guidanceKey: 'imports.guidance.field_of_study',
            ),
            new SpreadsheetColumnDefinition(
                key: 'education_status',
                headingKey: 'imports.columns.education_status',
                aliases: ['education state', 'وضعیت تحصیلی', 'وضعیت تحصیل'],
                required: false,
                example: EducationStatusEnum::GRADUATED->value,
                guidanceKey: 'imports.guidance.education_status',
            ),
            new SpreadsheetColumnDefinition(
                key: 'password',
                headingKey: 'imports.columns.password',
                aliases: ['pass', 'رمز', 'رمز عبور', 'گذرواژه'],
                required: false,
                example: null,
                guidanceKey: 'imports.guidance.password',
            ),
            new SpreadsheetColumnDefinition(
                key: 'provision_moodle',
                headingKey: 'imports.columns.provision_moodle',
                aliases: ['moodle', 'moodle account', 'moodle provision', 'ساخت حساب مودل', 'مودل'],
                required: false,
                example: 'true',
                guidanceKey: 'imports.guidance.provision_moodle',
            ),
            new SpreadsheetColumnDefinition(
                key: 'provision_ims',
                headingKey: 'imports.columns.provision_ims',
                aliases: ['ims', 'ims account', 'ims provision', 'آی ام اس', 'ساخت حساب آی ام اس'],
                required: false,
                example: 'true',
                guidanceKey: 'imports.guidance.provision_ims',
            ),
            new SpreadsheetColumnDefinition(
                key: 'provision_spotplayer',
                headingKey: 'imports.columns.provision_spotplayer',
                aliases: ['spotplayer', 'spot player', 'spotplayer account', 'اسپات پلیر', 'ساخت حساب اسپات پلیر'],
                required: false,
                example: 'true',
                guidanceKey: 'imports.guidance.provision_spotplayer',
            ),
        ];
    }

    /**
     * @return array<string, string|null>
     */
    public function exampleRow(): array
    {
        $row = [];

        foreach ($this->columns() as $column) {
            $row[$column->key] = $column->example;
        }

        return $row;
    }

    /**
     * @return list<ProvisioningProviderEnum>
     */
    public function providerCapabilities(): array
    {
        return [
            ProvisioningProviderEnum::MOODLE,
            ProvisioningProviderEnum::IMS,
            ProvisioningProviderEnum::SPOTPLAYER,
        ];
    }

    public function validateRow(array $values, ImportIdentityKeyEnum $identityKey): ImportRowResult
    {
        $values   = $this->normalizeValues($values);
        $identity = $this->identityValue($identityKey, $values[$identityKey->value] ?? null);
        $existing = $identity === null ? null : $this->findExistingUser($identityKey, $identity);
        $isCreate = $existing === null;

        $data   = $this->buildRowData($values, $isCreate);
        $errors = $this->validateValues($values, $identityKey, $existing, $isCreate);

        if ($errors !== []) {
            return ImportRowResult::invalid($identity, $data, $errors);
        }

        return $isCreate
            ? ImportRowResult::create((string) $identity, $data)
            : ImportRowResult::update((string) $identity, $data);
    }

    /**
     * Trim every cell, fold Persian digits and canonicalize Jalali dates.
     *
     * Empty cells become null so "not supplied" and "supplied empty" behave
     * identically for update rows.
     *
     * @param  array<string, string|null>  $values
     * @return array<string, string|null>
     */
    private function normalizeValues(array $values): array
    {
        $normalized = [];

        foreach ($values as $key => $value) {
            $value = $this->cleanCell($value);

            if ($value === null) {
                $normalized[$key] = null;

                continue;
            }

            $normalized[$key] = match ($key) {
                'civil_id_type'    => $this->normalizeEnumValue($value, CivilIdTypeEnum::cases()),
                'gender'           => $this->normalizeEnumValue($value, GenderEnum::cases()),
                'education_level'  => $this->normalizeEnumValue($value, EducationLevelEnum::cases()),
                'education_status' => $this->normalizeEnumValue($value, EducationStatusEnum::cases()),
                'date_of_birth'    => $this->normalizeJalaliDate($value),
                default            => $value,
            };
        }

        return $normalized;
    }

    /**
     * Cell text with Persian digits folded and surrounding whitespace removed.
     * Empty cells become null so "not supplied" and "supplied empty" behave
     * identically for update rows.
     */
    private function cleanCell(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = mb_trim(PersianDigitHelper::toAscii((string) $value));

        return $value === '' ? null : $value;
    }

    /**
     * Normalized row values exposed under rows[].data.
     *
     * Create rows always carry every field, update rows only carry supplied
     * cells so omissions never erase stored values. Passwords are never part
     * of a preview.
     *
     * @param  array<string, string|null>  $values
     * @return array<string, mixed>
     */
    private function buildRowData(array $values, bool $isCreate): array
    {
        $data = [];

        foreach (self::FIELD_KEYS as $key) {
            $value = $values[$key] ?? null;

            if ($value === null && ! $isCreate) {
                continue;
            }

            $data[$key] = $value;
        }

        foreach ($this->providerCapabilities() as $provider) {
            $key  = 'provision_'.$provider->value;
            $flag = $this->parseBoolean($values[$key] ?? null);

            if ($flag === null && ! $isCreate) {
                continue;
            }

            $data[$key] = $flag ?? false;
        }

        return $data;
    }

    /**
     * @param  array<string, string|null>  $values
     * @return list<array{field: string, code: string, message: string}>
     */
    private function validateValues(
        array $values,
        ImportIdentityKeyEnum $identityKey,
        ?User $existing,
        bool $isCreate,
    ): array {
        $validator = Validator::make(
            $values,
            $this->rules($identityKey, $existing, $isCreate),
            [],
            $this->attributeNames(),
        );

        $messages = $validator->errors()->messages();
        $failed   = $validator->failed();

        $errors = [];

        foreach ($messages as $field => $fieldMessages) {
            $code = $this->errorCode($failed[$field] ?? []);

            foreach ($fieldMessages as $message) {
                $errors[] = ['field' => $field, 'code' => $code, 'message' => $message];
            }
        }

        foreach ($this->providerCapabilities() as $provider) {
            $key = 'provision_'.$provider->value;

            if (($values[$key] ?? null) !== null && $this->parseBoolean($values[$key]) === null) {
                $errors[] = [
                    'field'   => $key,
                    'code'    => self::CODE_INVALID,
                    'message' => (string) __('imports.errors.invalid_boolean', [
                        'column' => (string) __("imports.columns.{$key}"),
                    ]),
                ];
            }
        }

        return $errors;
    }

    /**
     * Only the two cases the API contract names are distinguished; every other
     * failure is an invalid value.
     *
     * Laravel keys string rules by their studly name (`Required`, `Unique`) and
     * object rules by their class name, hence both spellings.
     *
     * @param  array<string, mixed>  $failedRules  Keyed by rule name or rule class.
     */
    private function errorCode(array $failedRules): string
    {
        foreach (array_keys($failedRules) as $rule) {
            if ($rule === 'Required') {
                return self::CODE_REQUIRED;
            }

            if ($rule === 'Unique' || $rule === Unique::class || $rule === UniqueCivilIdRule::class) {
                return self::CODE_CONFLICT;
            }
        }

        return self::CODE_INVALID;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function rules(ImportIdentityKeyEnum $identityKey, ?User $existing, bool $isCreate): array
    {
        $userId = $existing?->getKey();

        return [
            'phone' => [
                ...$this->presenceRule($isCreate || $identityKey === ImportIdentityKeyEnum::PHONE, $isCreate),
                'string', 'max:15', new IranMobilePhoneRule(mobileOnly: true),
                Rule::unique('users', 'phone')->ignore($userId),
            ],
            'email' => [
                ...$this->presenceRule($isCreate || $identityKey === ImportIdentityKeyEnum::EMAIL, $isCreate),
                'email', 'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'first_name' => [
                ...$this->presenceRule(true, $isCreate), 'string', 'max:100',
            ],
            'last_name' => [
                ...$this->presenceRule(true, $isCreate), 'string', 'max:100',
            ],
            'phone2' => [
                ...$this->presenceRule(false, $isCreate),
                'string', 'max:15', new IranMobilePhoneRule(),
            ],
            'civil_id' => [
                ...$this->presenceRule(true, $isCreate),
                'string', 'max:20', new CivilIdRule(), new UniqueCivilIdRule($userId),
            ],
            'civil_id_type' => [
                ...$this->presenceRule(true, $isCreate),
                'string', Rule::enum(CivilIdTypeEnum::class),
            ],
            'date_of_birth' => [
                ...($isCreate
                    ? ['bail', 'required']
                    : ['sometimes', 'nullable', 'bail']),
                new ValidNormalizedJalaliDateRule(), 'date_format:Y-m-d',
            ],
            'father_name' => [
                ...$this->presenceRule(true, $isCreate), 'string', 'max:100',
            ],
            'gender' => [
                ...$this->presenceRule(true, $isCreate),
                'string', Rule::enum(GenderEnum::class),
            ],
            'education_level' => [
                ...$this->presenceRule(false, $isCreate),
                'string', Rule::enum(EducationLevelEnum::class),
            ],
            'field_of_study' => [
                ...$this->presenceRule(false, $isCreate), 'string', 'max:100',
            ],
            'education_status' => [
                ...$this->presenceRule(false, $isCreate),
                'string', Rule::enum(EducationStatusEnum::class),
            ],
            'password' => [
                ...$this->presenceRule(false, $isCreate), 'string', 'min:8',
            ],
        ];
    }

    /**
     * Required columns are mandatory on create; on update every omitted or
     * empty cell means "keep the stored value".
     *
     * @return list<string>
     */
    private function presenceRule(bool $requiredOnCreate, bool $isCreate): array
    {
        if (! $isCreate) {
            return ['sometimes', 'nullable'];
        }

        return $requiredOnCreate ? ['required'] : ['nullable'];
    }

    /**
     * @return array<string, string>
     */
    private function attributeNames(): array
    {
        $attributes = [];

        foreach ($this->columns() as $column) {
            $attributes[$column->key] = $column->heading();
        }

        return $attributes;
    }

    /**
     * Canonical form of the value rows are matched on: Persian digits folded,
     * phone numbers reduced to their lookup form, emails lowercased.
     */
    private function identityValue(ImportIdentityKeyEnum $identityKey, mixed $value): ?string
    {
        $value = $this->cleanCell($value);

        if ($value === null) {
            return null;
        }

        return match ($identityKey) {
            ImportIdentityKeyEnum::PHONE => PhoneNumberHelper::normalize($value),
            ImportIdentityKeyEnum::EMAIL => mb_strtolower($value),
        };
    }

    private function findExistingUser(ImportIdentityKeyEnum $identityKey, string $identity): ?User
    {
        return match ($identityKey) {
            ImportIdentityKeyEnum::PHONE => User::query()
                ->whereIn('phone', PhoneNumberHelper::lookupVariants($identity))
                ->first(),
            ImportIdentityKeyEnum::EMAIL => User::query()
                ->whereRaw('lower(email) = ?', [$identity])
                ->first(),
        };
    }

    /**
     * @param  list<BackedEnum>  $cases
     */
    private function normalizeEnumValue(string $value, array $cases): string
    {
        $lookup = [];

        foreach ($cases as $case) {
            $caseValue = (string) $case->value;

            $lookup[$this->headingNormalizer->normalize($caseValue)]          = $caseValue;
            $lookup[$this->headingNormalizer->normalize($this->label($case))] = $caseValue;
        }

        return $lookup[$this->headingNormalizer->normalize($value)] ?? $value;
    }

    private function label(BackedEnum $case): string
    {
        return method_exists($case, 'translate') ? $case->translate() : (string) $case->value;
    }

    private function normalizeJalaliDate(string $value): string
    {
        $value = str_replace('/', '-', $value);

        return $this->toGregorian($value);
    }

    /**
     * Gregorian form of a Jalali input, falling back to the raw input so the
     * validation rules can report the malformed value.
     */
    private function toGregorian(string $value): string
    {
        $converted = JalaliDateHelper::toGregorian(['date_of_birth' => $value], ['date_of_birth']);

        $converted = $converted['date_of_birth'] ?? $value;

        return is_string($converted) ? $converted : $value;
    }

    private function parseBoolean(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $value = $this->cleanCell($value);

        if ($value === null) {
            return null;
        }

        $value = mb_strtolower($value);

        if (in_array($value, self::TRUE_VALUES, true)) {
            return true;
        }

        if (in_array($value, self::FALSE_VALUES, true)) {
            return false;
        }

        return null;
    }
}
