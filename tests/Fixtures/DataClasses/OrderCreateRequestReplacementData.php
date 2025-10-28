<?php

declare(strict_types=1);

namespace RomegaSoftware\LaravelSchemaGenerator\Tests\Fixtures\DataClasses;

use Closure;
use RomegaSoftware\LaravelSchemaGenerator\Attributes\InheritValidationFrom;
use RomegaSoftware\LaravelSchemaGenerator\Attributes\ValidationSchema;
use RomegaSoftware\LaravelSchemaGenerator\Support\SchemaRule;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Support\Validation\ValidationContext;

#[ValidationSchema]
class OrderCreateRequestReplacementData extends Data
{
    public function __construct(
        #[Required]
        #[DataCollectionOf(OrderItemRequestData::class), InheritValidationFrom(OrderItemRequestData::class)]
        public DataCollection $items,
    ) {}

    public static function rules(?ValidationContext $validationContext = null): array
    {
        return [
            'items' => [
                'required',
                'array',
                SchemaRule::make(
                    static function ($attribute, $value, Closure $fail, string $message): void {
                        if (collect($value)->sum('quantity') < 12) {
                            $fail($message);
                        }
                    }
                )->replace(static function (string $encoded) {
                    return <<<ZOD
                    z.array(z.object({ quantity: z.number() })).superRefine((items, ctx) => {
                        const total = items.reduce((sum, item) => sum + item.quantity, 0);
                        if (total < 12) {
                            ctx.addIssue({ code: 'custom', message: {$encoded} });
                        }
                    })
                    ZOD;
                })->failWith('You must order at least 12 total units.'),
            ],
        ];
    }
}
