<?php
namespace Modules\Member\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
class StoreMemberPaymentItemRateRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return [
        'amount'=>['required','numeric','min:0'], 'currency'=>['required','string','size:3'], 'effective_from'=>['required','date'], 'effective_to'=>['nullable','date','after_or_equal:effective_from'],
        'allocations'=>['required','array','min:1'], 'allocations.*.scope_type'=>['required','in:organization,unit'], 'allocations.*.percentage'=>['required','numeric','min:0','max:100'],
    ]; }
}
