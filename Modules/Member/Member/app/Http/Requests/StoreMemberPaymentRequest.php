<?php
namespace Modules\Member\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
class StoreMemberPaymentRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return [
        'obligation_ids'=>['required','array','min:1'], 'obligation_ids.*'=>['required','integer','distinct','exists:member_payment_obligations,id'],
        'payment_method'=>['nullable','string','max:50'], 'reference'=>['nullable','string','max:150'],
    ]; }
}
