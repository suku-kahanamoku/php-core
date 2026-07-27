<?php

declare(strict_types=1);

namespace App\Modules\Mailer;

use App\Modules\Router\Request;
use App\Modules\Router\Response;
use App\Modules\Router\Router;

class MailerApi
{
    private MailerService $_service;
    private string        $_code;

    public function __construct(string $franchiseCode = '')
    {
        $this->_code    = $franchiseCode;
        $this->_service = new MailerService($franchiseCode);
    }

    public function registerRoutes(Router $router): void
    {
        $router->get('/', fn(Request $req) => $this->send($req));
        $router->post('/', fn(Request $req) => $this->sendContactForm($req));
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
        $bcc              = trim((string) $request->get('bcc', ''));

        VALIDATOR($data)
            ->required($requiredFields)
            ->email('to')
            ->email('fromEmail')
            ->validate();

        // Base template data (always present)
        $templateData = [
            'fromEmail' => $data['fromEmail'],
            'fromName'  => $data['fromName'],
            'fromPhone' => $data['fromPhone'],
            'logoPath'  => $data['logoPath'],
        ];

        // Merge in any extra template-specific query params
        $reservedKeys = array_merge($requiredFields, ['logoPath', 'bcc', 'attachments']);
        foreach ($request->all() as $key => $value) {
            if (!in_array($key, $reservedKeys, true)) {
                $templateData[$key] = trim((string) $value);
            }
        }

        // Resolve file attachments from absolute paths (comma-separated)
        $attachments      = [];
        $attachmentsParam = trim((string) $request->get('attachments', ''));
        if ($attachmentsParam !== '') {
            $fileRoot = rtrim($_ENV['FILE_ROOT'] ?? dirname(__DIR__, 3), '/');
            foreach (explode(',', $attachmentsParam) as $path) {
                $path = trim($path);
                if ($path === '') {
                    continue;
                }
                // Relative paths get FILE_ROOT prefix
                if ($path[0] !== '/') {
                    $path = $fileRoot . '/' . $path;
                }
                if (file_exists($path)) {
                    $attachments[] = $path;
                }
            }
        }

        $sent = $this->_service->sendMail(
            to: $data['to'],
            subject: $data['subject'],
            template: $data['template'],
            templateData: $templateData,
            attachments: $attachments,
            bcc: $bcc !== '' ? $bcc : null,
        );

        if (!$sent) {
            Response::error('Failed to send email.', 500);
        }

        Response::success($data, 'Email sent.');
    }

    private function sendContactForm(Request $request): void
    {
        $name    = trim((string) $request->get('name', ''));
        $email   = trim((string) $request->get('email', ''));
        $project = trim((string) $request->get('project', ''));
        $message = trim((string) $request->get('message', ''));
        $phone   = trim((string) $request->get('phone', ''));

        $envPrefix = $this->_code !== ''
            ? trim(preg_replace('/[^A-Z0-9]+/', '_', strtoupper($this->_code)), '_') . '_'
            : '';

        $adminEmail = trim((string) $request->get(
            'adminEmail',
            $_ENV["{$envPrefix}MAILER_ADMIN_EMAIL"]
                ?? $_ENV['MAILER_ADMIN_EMAIL']
                ?? $_ENV["{$envPrefix}MAILER_FROM"]
                ?? $_ENV['MAILER_FROM']
                ?? '',
        ));
        $adminName = trim((string) (
            $_ENV["{$envPrefix}MAILER_ADMIN_NAME"]
            ?? $_ENV['MAILER_ADMIN_NAME']
            ?? $_ENV["{$envPrefix}MAILER_FROM_NAME"]
            ?? $_ENV['MAILER_FROM_NAME']
            ?? ''
        ));

        $data = [
            'name'    => $name,
            'email'   => $email,
            'project' => $project,
            'message' => $message,
        ];

        VALIDATOR($data)
            ->required(['name', 'email', 'project', 'message'])
            ->email('email')
            ->validate();

        if ($adminEmail === '' || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            Response::error('Admin email is not configured or invalid.', 500);
        }

        $templatePrefix = $this->_code !== '' ? $this->_code . '/' : '';

        $adminSent = $this->_service->sendMail(
            to: $adminEmail,
            subject: 'New msg from contact form',
            template: $templatePrefix . 'contact-form-admin',
            templateData: [
                'fromEmail' => $email,
                'fromName'  => $name,
                'fromPhone' => $phone,
                'name'      => $name,
                'email'     => $email,
                'project'   => $project,
                'msg'       => $message,
                'phone'     => $phone,
            ],
        );

        if (!$adminSent) {
            Response::error(json_encode([
                'to'           => $adminEmail,
                'subject'      => 'New msg from contact form',
                'template'     => $templatePrefix . 'contact-form-admin',
                'templateData' => [
                    'fromEmail' => $email,
                    'fromName'  => $name,
                    'fromPhone' => $phone,
                    'name'      => $name,
                    'email'     => $email,
                    'project'   => $project,
                    'msg'       => $message,
                    'phone'     => $phone,
                ],
            ]), 500);
        }

        $userSent = $this->_service->sendMail(
            to: $email,
            subject: 'Confirmation of your message receipt',
            template: $templatePrefix . 'contact-form',
            templateData: [
                'fromEmail' => $adminEmail,
                'fromName'  => $adminName,
                'fromPhone' => $phone,
                'name'      => $name,
                'project'   => $project,
                'msg'       => $message,
            ],
        );

        if (!$userSent) {
            Response::error('Failed to send confirmation email to user.', 500);
        }

        Response::success(
            [
                'adminEmail' => $adminEmail,
                'recipient'  => $email,
                'project'    => $project,
            ],
            'Contact form emails sent.',
        );
    }

    private function sendTest(Request $request): void
    {
        $email = trim((string) $request->get('email', ''));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::error(
                'Parameter email is required and must be a valid email address.',
                400,
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
