<?php

namespace App\Services;

use Illuminate\Support\Facades\Mail;
use RuntimeException;

class EmailService
{
    public function sendWelcomeEmail(string $toEmail, string $toName, string $phone, string $password): void
    {
        $transport = config('mail.mailers.'.config('mail.default').'.transport');
        if (in_array($transport, ['log', 'array'], true)) {
            throw new RuntimeException('O serviço de email está configurado apenas para registo ou testes.');
        }

        $appName = e(config('app.name'));
        $appUrl = config('app.frontend_url', config('app.url'));
        $safeName = e($toName);
        $safePhone = e($phone);
        $safePassword = e($password);
        $year = date('Y');

        $html = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <div style='background: #1e3a8a; padding: 30px; text-align: center;'>
                    <h1 style='color: white; margin: 0;'>{$appName}</h1>
                </div>
                <div style='padding: 30px; background: #f9f9f9;'>
                    <h2 style='color: #333;'>Olá, {$safeName}!</h2>
                    <p style='color: #555;'>A sua conta foi criada com sucesso. Aqui estão as suas credenciais de acesso:</p>
                    <div style='background: white; border-left: 4px solid #1e3a8a; padding: 20px; margin: 20px 0;'>
                        <p style='margin: 5px 0;'><strong>Celular (login):</strong> {$safePhone}</p>
                        <p style='margin: 5px 0;'><strong>Password:</strong> {$safePassword}</p>
                    </div>
                    <div style='text-align: center; margin: 20px 0;'>
                        <a href='{$appUrl}' style='background: #1e3a8a; color: white; padding: 12px 30px; border-radius: 8px; text-decoration: none; font-weight: 600; display: inline-block;'>
                            Aceder à aplicação
                        </a>
                    </div>
                    <p style='color: #e74c3c; font-size: 13px;'>Por razões de segurança, recomendamos que altere a sua password após o primeiro login.</p>
                </div>
                <div style='background: #333; padding: 15px; text-align: center;'>
                    <p style='color: #aaa; font-size: 12px; margin: 0;'>© {$year} {$appName}. Todos os direitos reservados.</p>
                </div>
            </div>
        ";

        $sentMessage = Mail::html($html, function ($message) use ($toEmail, $toName, $appName) {
            $message->to($toEmail, $toName)
                ->subject("Bem-vindo ao {$appName} - As suas credenciais de acesso");
        });

        if ($sentMessage === null) {
            throw new RuntimeException('O serviço de email não confirmou o envio das credenciais.');
        }
    }
}
