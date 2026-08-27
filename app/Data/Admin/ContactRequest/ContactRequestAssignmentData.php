<?php

declare(strict_types=1);

namespace App\Data\Admin\ContactRequest;

use App\Models\Staff;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

final class ContactRequestAssignmentData extends Data
{
    public function __construct(public ?int $staff_id) {}

    public static function rules(): array
    {
        return ['staff_id' => ['present', 'nullable', 'integer', Rule::exists((new Staff)->getTable(), 'id')->where('is_banned', 'false')]];
    }
}
