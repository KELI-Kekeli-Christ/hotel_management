<?php

namespace App\Http\Controllers;

use App\Mail\ContactFormSubmitted;
use App\Http\Requests\ContactFormRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Config;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class ContactController extends Controller
{
    /**
     * Envoie le formulaire de contact.
     * Utilise ContactFormRequest pour la validation.
     */
    public function send(ContactFormRequest $request)
    {
        $data = $request->validated();

        // Métadonnées utiles (IP / UA) pour debug/support
        $meta = [
            'ip' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
        ];

        // Adresse admin depuis .env (fallback)
        $adminEmail = env('MAIL_ADMIN_ADDRESS', config('mail.from.address'));

        if (!$adminEmail) {
            Log::error('ContactController: admin email not configured', ['meta'=>$meta]);
            return response()->json(['message' => 'Le serveur n\'est pas configuré pour recevoir des messages.'], 500);
        }

        // Optionnel : enregistrer en base (audit / support)
        try {
            DB::beginTransaction();
            // Si tu as une table contact_messages, tu peux la remplir ici.
            // Example:
            // $contact = \App\Models\ContactMessage::create(array_merge($data, $meta, ['status' => 'new']));
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::warning('ContactController: could not persist contact (continuing to email)', ['err'=>$e->getMessage(), 'meta'=>$meta]);
            // On continue quand même pour envoyer l'email
        }

        try {
            // Envoi asynchrone => nécessite queue worker configuré (QUEUE_CONNECTION=database/redis)
            Mail::to($adminEmail)->queue(new ContactFormSubmitted($data));

            // Si tu veux stocker le message sur SQS/db etc, mets le code ici

            Log::info('Contact form submitted (queued mail)', ['email_to' => $adminEmail, 'meta' => $meta, 'subject' => $data['subject']]);

            return response()->json(['message' => 'Votre message a été envoyé avec succès. Nous vous répondrons bientôt.'], 200);
        } catch (\Throwable $e) {
            Log::error('ContactController: mail send failed', ['err' => $e->getMessage(), 'meta' => $meta]);
            return response()->json(['message' => 'Une erreur est survenue lors de l\'envoi du message. Veuillez réessayer plus tard.'], 500);
        }
    }
}
