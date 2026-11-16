<?php

declare(strict_types=1);

namespace App\Actions\Shop\Student;

use App\Data\Shop\Webhooks\PorslineSurveySubmissionData;
use App\Enums\Product\ProductableEnum;
use App\Models\Enrollment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class CompleteEnrollmentSurveyAction
{
    public function __construct(
        private ForgetStudentQuizCacheAction $forgetStudentQuizCache,
        private EnrollmentSurveyPolicyAction $surveyPolicy,
    ) {}

    public function handle(PorslineSurveySubmissionData $submission): void
    {
        $tokenEntry = collect($submission->variables)
            ->first(fn (array $variable): bool => ($variable['name'] ?? null) === 'enrollment_token');
        $token = is_array($tokenEntry) ? ($tokenEntry['value'] ?? null) : null;

        if (! is_string($token)) {
            throw ValidationException::withMessages([
                'variables' => __('messages.enrollments.survey_token_invalid'),
            ]);
        }

        $digest = hash('sha256', $token);

        $customerId = DB::transaction(function () use ($digest): int|false {
            $enrollment = Enrollment::query()
                ->where('survey_token_digest', $digest)
                ->whereHas('productDeliveryOption.product', fn (Builder $query): Builder => $query
                    ->whereIn('productable_type', [
                        ProductableEnum::COURSE->value,
                        ProductableEnum::SEMINAR->value,
                    ]))
                ->lockForUpdate()
                ->first();

            if (! $enrollment) {
                throw ValidationException::withMessages([
                    'variables' => __('messages.enrollments.survey_token_invalid'),
                ]);
            }

            if ($enrollment->survey_completed_at !== null) {
                return false;
            }

            if (! $this->surveyPolicy->allowsSurveyCompletion($enrollment)) {
                throw ValidationException::withMessages([
                    'variables' => __('messages.enrollments.survey_token_invalid'),
                ]);
            }

            $enrollment->forceFill(['survey_completed_at' => now()])->save();

            return $enrollment->customer_id;
        });

        if ($customerId !== false) {
            $this->forgetStudentQuizCache->handle($customerId);
        }
    }
}
