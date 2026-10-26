<?php
namespace Modules\Member\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
class DiscountMemberPaymentRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return ['reason'=>['required','string','max:1000'],'discount_amount'=>['required','numeric','gt:0']]; }
}
