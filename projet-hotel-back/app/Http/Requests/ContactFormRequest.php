<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ContactFormRequest extends FormRequest
{
    public function authorize()
    {
        return true; // adapt si nécessaire (ex: seulement users)
    }

    public function rules()
    {
        return [
            'name'    => 'required|string|max:255',
            'email'   => 'required|email|max:255',
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:2000',
            // 'g-recaptcha-response' => 'required_if:use_recaptcha,true|string', // optionnel
        ];
    }

    public function messages()
    {
        return [
            'name.required'    => 'Veuillez indiquer votre nom.',
            'email.required'   => 'Veuillez indiquer votre email.',
            'subject.required' => 'Veuillez indiquer un sujet.',
            'message.required' => 'Veuillez écrire un message.',
        ];
    }
}