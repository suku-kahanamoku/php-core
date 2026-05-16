<?php

declare(strict_types=1);

namespace App\Modules\Mailer;

use App\Modules\Router\Request;
use App\Modules\Router\Response;
use App\Modules\Router\Router;

class MailerApi
{
    private MailerService $service;

    public function __construct()
    {
        $this->service = new MailerService();
    }

    public function registerRoutes(Router $router): void
    {
        $router->get('/', fn(Request $req) => $this->send($req));
        $router->get('/test', fn(Request $req) => $this->sendTest($req));
    }

    private function send(Request $request): void
    {
        $requiredFields = [
            'to',
            'subject',
            'template',
            'fromEmail',
            'fromName',
            'fromPhone',
        ];

        $data = [];
        foreach ($requiredFields as $field) {
            $data[$field] = trim((string) $request->get($field, ''));
        }

        VALIDATOR($data)
            ->required($requiredFields)
            ->email('to')
            ->email('fromEmail')
            ->validate();

        $sent = $this->service->sendMail(
            to: $data['to'],
            subject: $data['subject'],
            template: $data['template'],
            templateData: [
                'fromEmail' => $data['fromEmail'],
                'fromName'  => $data['fromName'],
                'fromPhone' => $data['fromPhone'],
            ],
        );

        if (!$sent) {
            Response::error('Failed to send email.', 500);
        }

        Response::success($data, 'Email sent.');
    }

    private function sendTest(Request $request): void
    {
        $email = trim((string) $request->get('email', ''));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::error(
                'Parameter email is required and must be a valid email address.',
                400
            );
        }

        $sent = $this->service->sendTestMail($email);

        if (!$sent) {
            Response::error('Failed to send email.', 500);
        }

        Response::success(['email' => $email], 'Test email sent.');
    }
}
