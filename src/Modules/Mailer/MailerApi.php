<?php

declare(strict_types=1);

namespace App\Modules\Mailer;

use App\Modules\Router\Request;
use App\Modules\Router\Response;
use App\Modules\Router\Router;

class MailerApi
{
    private MailerService $_service;
    private string $_code;

    public function __construct(string $franchiseCode = '')
    {
        $this->_code    = $franchiseCode;
        $this->_service       = new MailerService($franchiseCode);
    }

    public function registerRoutes(Router $router): void
    {
        $router->get('/', fn(Request $req) => $this->send($req));
        $router->get('/test', fn(Request $req) => $this->sendTest($req));
        $router->get('/list', fn(Request $req) => $this->listTemplates($req));
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
        $data['logoPath'] = trim((string) $request->get('logoPath', ''));

        VALIDATOR($data)
            ->required($requiredFields)
            ->email('to')
            ->email('fromEmail')
            ->validate();

        $sent = $this->_service->sendMail(
            to: $data['to'],
            subject: $data['subject'],
            template: $data['template'],
            templateData: [
                'fromEmail' => $data['fromEmail'],
                'fromName'  => $data['fromName'],
                'fromPhone' => $data['fromPhone'],
                'logoPath'  => $data['logoPath'],
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

        $sent = $this->_service->sendTestMail($email);

        if (!$sent) {
            Response::error('Failed to send email.', 500);
        }

        Response::success(['email' => $email], 'Test email sent.');
    }

    private function listTemplates(Request $request): void
    {
        $dir       = dirname(__DIR__, 3) . '/emails/' . $this->_code;
        $templates = [];

        if (is_dir($dir)) {
            foreach (glob($dir . '/*.php') ?: [] as $file) {
                $name        = basename($file, '.php');
                $templates[] = ['template' => $name];
            }
        }

        Response::success($templates, 'Templates listed.');
    }
}
