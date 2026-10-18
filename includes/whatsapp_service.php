<?php
/**
 * LabMaster - WhatsApp Integration Service
 * Supports Meta WhatsApp Cloud API and Direct WhatsApp Web click-to-chat fallback
 */

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

class WhatsAppService
{
    /**
     * Build Direct Click-to-Chat URL
     */
    public static function buildChatUrl(?string $phone = null, ?string $message = null): string
    {
        $rawPhone = $phone ?: Settings::get('whatsapp_floating_phone', '919876543210');
        // Clean non-digits
        $cleanPhone = preg_replace('/[^\d]/', '', (string)$rawPhone);

        $defaultMsg = $message ?: Settings::get(
            'whatsapp_floating_text',
            'Hello LabMaster Laboratory, I would like to enquire about test availability and home sample collection.'
        );

        return 'https://api.whatsapp.com/send?phone=' . urlencode($cleanPhone) . '&text=' . urlencode($defaultMsg);
    }

    /**
     * Send or Dispatch Patient Report Link
     */
    public static function sendReportNotification(
        string $phone,
        string $patientName,
        string $reportNumber,
        string $verificationToken,
        ?int $patientId = null,
        ?int $reportId = null
    ): array {
        $cleanPhone = preg_replace('/[^\d]/', '', $phone);
        // Ensure country code if 10 digits (India +91)
        if (strlen($cleanPhone) === 10) {
            $cleanPhone = '91' . $cleanPhone;
        }

        $labName = Settings::get('lab_name', 'LabMaster Diagnostic Center');
        $secureLink = BASE_URL . '/verify-report.php?token=' . urlencode($verificationToken);

        $messageBody = "Dear {$patientName},\n\nYour verified pathology report ({$reportNumber}) from *{$labName}* is now ready.\n\nYou can view and download your signed PDF report securely here:\n{$secureLink}\n\nFor assistance or home collection, contact our helpline at " . Settings::get('lab_phone', '+91 98765 43210') . ".\n\n_Thank you for choosing LabMaster._";

        $apiEnabled = (int)Settings::get('whatsapp_api_enabled', '0') === 1;
        $phoneNumberId = Settings::get('whatsapp_cloud_phone_number_id', '');
        $accessToken = Settings::get('whatsapp_cloud_access_token', '');

        $status = 'Not Sent';
        $apiResponse = null;

        if ($apiEnabled && !empty($phoneNumberId) && !empty($accessToken)) {
            // Attempt Meta WhatsApp Cloud API call
            $endpoint = "https://graph.facebook.com/v19.0/{$phoneNumberId}/messages";

            $payload = [
                'messaging_product' => 'whatsapp',
                'recipient_type'    => 'individual',
                'to'                => $cleanPhone,
                'type'              => 'text',
                'text'              => [
                    'preview_url' => true,
                    'body'        => $messageBody
                ]
            ];

            $ch = curl_init($endpoint);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => json_encode($payload),
                CURLOPT_HTTPHEADER     => [
                    'Authorization: Bearer ' . $accessToken,
                    'Content-Type: application/json'
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_SSL_VERIFYPEER => true
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($httpCode >= 200 && $httpCode < 300) {
                $status = 'Sent';
                $apiResponse = $response;
            } else {
                $status = 'Failed';
                $apiResponse = $curlError ?: "HTTP Error {$httpCode}: {$response}";
            }
        } else {
            // Simulated / Click-to-chat fallback
            $status = 'Sent';
            $apiResponse = 'Cloud API inactive. Generated direct web dispatch link.';
        }

        // Record in whatsapp_logs
        $userId = Auth::id();
        Database::execute(
            "INSERT INTO `whatsapp_logs` (`recipient_phone`, `patient_id`, `report_id`, `message_type`, `message_body`, `status`, `api_response`, `sent_by`)
             VALUES (?, ?, ?, 'Report Link', ?, ?, ?, ?)",
            [$cleanPhone, $patientId, $reportId, $messageBody, $status, $apiResponse, $userId]
        );

        $logId = Database::lastInsertId();

        // Return result with direct URL for convenient operator click
        $directUrl = 'https://api.whatsapp.com/send?phone=' . urlencode($cleanPhone) . '&text=' . urlencode($messageBody);

        return [
            'success'      => $status === 'Sent',
            'status'       => $status,
            'log_id'       => $logId,
            'direct_url'   => $directUrl,
            'message_body' => $messageBody,
            'phone'        => $cleanPhone
        ];
    }
}
