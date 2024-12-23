<?php

namespace App\Http\Requests;

use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Http\FormRequest;

class StoreRingotelConnectionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'org_id' => 'present',
            'connection_name' => 'required|string|max:100',
            'protocol' => 'required|string',
            'domain' => 'required|string',
            'port' => 'nullable|numeric',
            'dont_verify_server_certificate' => 'present',
            'disable_srtp' => 'present',
            'multitenant' => 'present',
            'proxy' => 'nullable|string',
            'g711u_enabled' => 'present',
            'g711a_enabled' => 'present',
            'g729_enabled' => 'present',
            'opus_enabled' => 'present',
            'registration_ttl' => 'required|numeric',
            'max_registrations' => 'required|numeric',
            'app_opus_codec' => 'present',
            'one_push' => 'present',
            'show_call_settings' => 'present',
            'allow_call_recording' => 'present',
        ];
    }


    public function prepareForValidation(): void
    {
        // Check if 'region' is missing or empty and set it to null
        if (!$this->has('protocol') || $this->input('protocol') === 'NULL') {
            $this->merge(['protocol' => null]);
        }

        // if ($this->has('dont_send_user_credentials')) {
        //     $this->merge([
        //         'dont_send_user_credentials' => $this->dont_send_user_credentials ? 'true' : 'false',
        //     ]);
        // }

    }

    /**
     * Sanitize the input field to prevent XSS and remove unwanted characters.
     *
     * @param string $input
     * @return string
     */
    protected function sanitizeInput(string $input): string
    {
        // Trim whitespace
        $input = trim($input);

        // Strip HTML tags
        $input = strip_tags($input);

        // Escape special characters
        $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');

        // Remove any non-ASCII characters if necessary (optional)
        $input = preg_replace('/[^\x20-\x7E]/', '', $input);

        return $input;
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array
     */
    public function attributes(): array
    {
        return [
            'voicemail_id' => 'voicemail extension',
            'voicemail_password' => 'voicemail password',
            'greeting_id' => 'extension number',
            'voicemail_mail_to' => 'email address',
            'voicemail_enabled' => 'enabled',
            'voicemail_description' => 'description',
            'voicemail_alternate_greet_id' => 'value',
        ];
    }
}
