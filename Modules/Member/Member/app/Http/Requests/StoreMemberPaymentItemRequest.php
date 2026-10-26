<?php
namespace Modules\Member\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Member\Enums\MemberPaymentRecurrenceBy;
use Modules\Member\Enums\MemberPaymentRecurrencePeriod;
class StoreMemberPaymentItemRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return [
        'membership_type_id'=>['nullable','integer','exists:membership_types,id'],
        'title'=>['required','string','max:255'], 'slug'=>['required','string','max:120','regex:/^[a-z0-9][a-z0-9._-]*$/'],
        'description'=>['nullable','string'], 'is_mandatory'=>['sometimes','boolean'], 'is_recurring'=>['sometimes','boolean'],
        'recurring_period'=>['nullable',Rule::in(array_column(MemberPaymentRecurrencePeriod::cases(),'value'))],
        'recurring_by'=>['nullable',Rule::in(array_column(MemberPaymentRecurrenceBy::cases(),'value'))],
        'recurring_date'=>['nullable','string','max:10'], 'is_active'=>['sometimes','boolean'],
        'reminder_days_before_due'=>['nullable','integer','min:0'], 'reminder_days_after_due'=>['nullable','integer','min:0'], 'metadata'=>['sometimes','array'],
        'rate'=>['required','array'], 'rate.amount'=>['required','numeric','min:0'], 'rate.currency'=>['required','string','size:3'],
        'rate.effective_from'=>['required','date'], 'rate.effective_to'=>['nullable','date','after_or_equal:rate.effective_from'],
        'rate.allocations'=>['required','array','min:1'], 'rate.allocations.*.scope_type'=>['required',Rule::in(['organization','unit'])], 'rate.allocations.*.percentage'=>['required','numeric','min:0','max:100'],
    ]; }
}
