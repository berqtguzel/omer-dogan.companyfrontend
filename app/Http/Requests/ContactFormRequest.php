<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ContactFormRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'company' => 'nullable|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'serviceType' => 'nullable|string|max:255',
            'message' => 'required|string|max:5000',
            'acceptTerms' => 'required|accepted'
        ];
    }

    public function messages()
    {
        return [
            'name.required' => 'Bitte geben Sie Ihren Namen ein.',
            'name.max' => 'Der Name darf maximal 255 Zeichen lang sein.',
            'email.required' => 'Bitte geben Sie Ihre E-Mail-Adresse ein.',
            'email.email' => 'Bitte geben Sie eine gültige E-Mail-Adresse ein.',
            'email.max' => 'Die E-Mail-Adresse darf maximal 255 Zeichen lang sein.',
            'message.required' => 'Bitte geben Sie Ihre Nachricht ein.',
            'message.max' => 'Die Nachricht darf maximal 5000 Zeichen lang sein.',
            'acceptTerms.required' => 'Bitte akzeptieren Sie die Datenschutzerklärung.',
            'acceptTerms.accepted' => 'Sie müssen die Datenschutzerklärung akzeptieren.'
        ];
    }
}
