<?php

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Fixtures\DataClasses;

use RomegaSoftware\LaravelSchemaGenerator\Attributes\ValidationSchema;
use RomegaSoftware\LaravelSchemaGenerator\Tests\Fixtures\Enums\TestStatusEnum;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\MergeValidationRules;
use Spatie\LaravelData\Attributes\Validation\BooleanType;
use Spatie\LaravelData\Attributes\Validation\IntegerType;
use Spatie\LaravelData\Attributes\Validation\Max;
use Spatie\LaravelData\Attributes\Validation\Min;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Numeric;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;

#[ValidationSchema(name: 'UnifiedDataSchema')]
#[MergeValidationRules]
class UnifiedData extends Data
{
    public function __construct(
        #[MapName('account_details')]
        public UnifiedProfileData $profile,

        #[DataCollectionOf(UnifiedProjectData::class)]
        public array $projects,

        #[Nullable, StringType, Max(500)]
        public ?string $notes = null,
    ) {}

    public static function rules($context = null): array
    {
        return [
            'projects' => ['required', 'array', 'min:1'],
            'projects.*.metrics' => ['required', 'array', 'min:1'],
            'projects.*.metrics.*.value' => ['required', 'numeric', 'min:0'],
        ];
    }
}

#[ValidationSchema]
#[MergeValidationRules]
class UnifiedProfileData extends Data
{
    public function __construct(
        #[Max(120)]
        public string $name,

        #[Nullable, StringType, Max(255)]
        public ?string $email,

        #[Nullable, StringType, Max(50)]
        public ?string $timezone,

        public TestStatusEnum $status,

        #[IntegerType, Min(1), Max(10)]
        public int $priority,

        public UnifiedAddressData $address,

        #[Nullable]
        public ?UnifiedPreferenceData $preferences,
    ) {}

    public static function rules($context = null): array
    {
        return [
            'status' => ['required', 'in:pending,active,inactive,deleted'],
            'address.postal_code' => ['required', 'regex:/^[0-9]{5}$/'],
            'address.country' => ['required', 'string', 'size:2'],
        ];
    }
}

#[ValidationSchema]
class UnifiedAddressData extends Data
{
    public function __construct(
        #[StringType, Max(120)]
        public string $street,

        #[StringType, Max(80)]
        public string $city,

        #[StringType, Max(2)]
        public string $state,

        #[StringType]
        public string $postal_code,

        #[StringType]
        public string $country,
    ) {}
}

#[ValidationSchema]
#[MergeValidationRules]
class UnifiedPreferenceData extends Data
{
    public function __construct(
        #[BooleanType]
        public bool $marketing_opt_in,

        #[Nullable, DataCollectionOf(UnifiedContactData::class)]
        public ?array $contacts,
    ) {}

    public static function rules($context = null): array
    {
        return [
            'contacts' => ['nullable', 'array'],
            'contacts.*.label' => ['required', 'string', 'in:primary,backup'],
            'contacts.*.email' => ['required', 'email'],
        ];
    }
}

#[ValidationSchema]
class UnifiedContactData extends Data
{
    public function __construct(
        public string $label,
        public string $email,
        #[Nullable, StringType, Max(20)]
        public ?string $phone,
    ) {}
}

#[ValidationSchema]
#[MergeValidationRules]
class UnifiedProjectData extends Data
{
    public function __construct(
        #[Max(150)]
        public string $title,

        #[Nullable, StringType, Max(500)]
        public ?string $summary,

        #[MapName('status_state')]
        public TestStatusEnum $status,

        #[DataCollectionOf(UnifiedMetricData::class)]
        public array $metrics,

        #[Nullable]
        public ?UnifiedScheduleData $schedule,
    ) {}

    public static function rules($context = null): array
    {
        return [
            'metrics' => ['required', 'array', 'min:1'],
            'metrics.*.key' => ['required', 'string'],
            'metrics.*.trend' => ['nullable', 'in:up,down,flat'],
            'schedule.starts_at' => ['required', 'date'],
            'schedule.ends_at' => ['nullable', 'date', 'after_or_equal:schedule.starts_at'],
        ];
    }
}

#[ValidationSchema]
class UnifiedMetricData extends Data
{
    public function __construct(
        public string $key,
        #[Numeric]
        public float $value,
        #[Nullable, StringType, Max(10)]
        public ?string $trend,
    ) {}
}

#[ValidationSchema]
class UnifiedScheduleData extends Data
{
    public function __construct(
        public string $starts_at,
        #[Nullable]
        public ?string $ends_at,
    ) {}
}
