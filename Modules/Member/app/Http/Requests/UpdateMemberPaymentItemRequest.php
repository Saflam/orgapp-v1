<?php
namespace Modules\Member\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Member\Enums\MemberPaymentRecurrenceBy;
use Modules\Member\Enums\MemberPaymentRecurrencePeriod;
class UpdateMemberPaymentItemRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return [
        'membership_type_id'=>['sometimes','nullable','integer','exists:membership_types,id'], 'title'=>['sometimes','string','max:255'], 'slug'=>['sometimes','string','max:120','regex:/^[a-z0-9][a-z0-9._-]*$/'], 'description'=>['sometimes','nullable','string'],
        'is_mandatory'=>['sometimes','boolean'], 'is_recurring'=>['sometimes','boolean'], 'recurring_period'=>['sometimes','nullable',Rule::in(array_column(MemberPaymentRecurrencePeriod::cases(),'value'))], 'recurring_by'=>['sometimes','nullable',Rule::in(array_column(MemberPaymentRecurrenceBy::cases(),'value'))], 'recurring_date'=>['sometimes','nullable','string','max:10'], 'is_active'=>['sometimes','boolean'], 'reminder_days_before_due'=>['sometimes','nullable','integer','min:0'], 'reminder_days_after_due'=>['sometimes','nullable','integer','min:0'], 'metadata'=>['sometimes','array'],
    ]; }
}
