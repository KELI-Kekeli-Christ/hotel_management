<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReservationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * La Policy dans le contrôleur gère déjà la permission de base.
     * On retourne donc 'true' ici.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => 'nullable|exists:users,id', // Optionnel, sera défini à l'utilisateur connecté si absent
            'chambre_id' => 'required|exists:chambres,id',
            'date_arrive' => 'required|date|after_or_equal:today',
            'date_depart' => 'required|date|after:date_arrive',
            'phone_number' => 'nullable|string|max:20',
        ];
    }
}
