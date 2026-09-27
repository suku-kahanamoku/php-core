<?php
declare(strict_types=1);
// A deliberately separate public mobile entrypoint. Other API modules still require InternalAuth.
require_once __DIR__ . "/../../bootstrap.php";
use App\Modules\Database\Database;
use App\Modules\Router\Request;
use App\Modules\Router\Response;
use App\Modules\Sry\{
    SryStore,
    SryAuth,
    SryService,
    SryError,
    CloudflareGateway,
};
use App\Modules\Mailer\MailerService;
use App\Utils\RateLimiter;
header("Cache-Control: no-store");
header("Referrer-Policy: no-referrer");
if (($_SERVER["REQUEST_METHOD"] ?? "") === "OPTIONS") {
    http_response_code(204);
    exit();
}
try {
    // Bound JSON input before the shared Request class parses it, including chunked requests.
    $raw = file_get_contents("php://input", false, null, 0, 32769);
    if (strlen($raw ?: "") > 32768) {
        throw new SryError("invalidInput", 413);
    }
    $request = new Request();
    if ($request->franchiseCode !== "sry") {
        throw new SryError("forbidden", 403);
    }
    if ((int) ($_SERVER["CONTENT_LENGTH"] ?? 0) > 32768) {
        throw new SryError("invalidInput", 413);
    }
    $db = Database::getInstance();
    $store = new SryStore($db->getPdo());
    $auth = new SryAuth($store);
    $service = new SryService(
        $store,
        new CloudflareGateway(
            $_ENV["SRY_CLOUDFLARE_URL"] ?? "",
            $_ENV["SRY_CLOUDFLARE_SECRET"] ?? "",
        ),
    );
    $path = $request->uri;
    $method = $request->method;
    $body = $request->body;
    $public = [
        "/auth/login",
        "/auth/signup",
        "/auth/join",
        "/auth/reset-password",
        "/auth/complete-reset",
    ];
    $limiter = new RateLimiter($db, "sry");
    if (in_array($path, $public, true) && $method === "POST") {
        $limiter->hit(
            "sry-" . $path,
            $_SERVER["REMOTE_ADDR"] ?? "unknown",
            20,
            900,
        );
        $result = match ($path) {
            "/auth/login" => $auth->login($body),
            "/auth/signup" => $auth->signup($body),
            "/auth/join" => $auth->join($body),
            "/auth/complete-reset" => $auth->completeReset($body),
            "/auth/reset-password" => $auth->reset(
                $body,
                str_starts_with($request->header("Accept-Language", "cs"), "en")
                    ? "en"
                    : "cs",
                static function ($email, $token, $language) {
                    $base = rtrim($_ENV["SRY_FRONTEND_URL"] ?? "", "/");
                    if (!str_starts_with($base, "https://")) {
                        throw new SryError("notConfigured", 503);
                    }
                    $sent = (new MailerService("sry"))->sendMail(
                        $email,
                        $language === "en"
                            ? "Reset your password"
                            : "Obnovení hesla",
                        "sry-reset-password",
                        [
                            "language" => $language,
                            "resetUrl" =>
                                $base .
                                "/reset-password?token=" .
                                rawurlencode($token),
                        ],
                    );
                    if (!$sent) {
                        error_log("SRY password reset delivery failed");
                    }
                },
            ),
        };
    } else {
        $authorization = $request->header("Authorization", "");
        if (!preg_match('/^Bearer ([a-f0-9]{64})$/', $authorization, $m)) {
            throw new SryError("unauthorized", 401);
        }
        $token = $m[1];
        $actor = $auth->identity($token);
        $limiter->hit("sry-api", (string) $actor["id"], 180, 60);
        $id = preg_match(
            '~^/(?:family|tasks|media|notifications)/(\d+)(?:/[^/]+)?$~',
            $path,
            $matches,
        )
            ? (int) $matches[1]
            : 0;
        $result = match (true) {
            $method === "GET" && $path === "/auth/me" => $actor,
            $method === "POST" && $path === "/auth/logout" => (function () use (
                $store,
                $auth,
                $actor,
                $token,
            ) {
                $store->execute(
                    "DELETE FROM sry_push_device WHERE member_id=?",
                    [$actor["id"]],
                );
                $auth->logout($token);
                return ["logged_out" => true];
            })(),
            $method === "GET" && $path === "/family" => $service->family(
                $actor,
            ),
            $method === "POST" && $path === "/family" => $service->addChild(
                $actor,
                $body,
            ),
            $method === "PATCH" && $path === "/family/$id"
                => $service->updateChild($actor, $id, $body),
            $method === "POST" && $path === "/invitations" => $auth->invitation(
                $actor,
                $body,
            ),
            $method === "GET" && $path === "/catalog" => $service->catalog(),
            $method === "GET" && $path === "/tasks" => $service->tasks($actor),
            $method === "POST" && $path === "/tasks" => $service->createTask(
                $actor,
                $body,
            ),
            $method === "GET" && $path === "/tasks/$id" => $service->detail(
                $actor,
                $id,
            ),
            $method === "POST" && $path === "/tasks/$id/submit"
                => $service->submit($actor, $id, $body),
            $method === "POST" && $path === "/tasks/$id/review"
                => $service->review($actor, $id, $body),
            $method === "POST" && $path === "/media" => $service->prepareMedia(
                $actor,
                $body,
            ),
            $method === "POST" && $path === "/media/$id/complete"
                => $service->completeMedia($actor, $id),
            $method === "GET" && $path === "/media/$id" => $service->mediaUrl(
                $actor,
                $id,
            ),
            $method === "GET" && $path === "/notifications"
                => $service->notifications($actor),
            $method === "POST" && $path === "/notifications/$id/read"
                => $service->markRead($actor, $id),
            $method === "POST" && $path === "/push" => $service->push(
                $actor,
                $body,
            ),
            $method === "DELETE" && $path === "/push" => $service->removePush(
                $actor,
                $body,
            ),
            $method === "GET" && $path === "/realtime" => $service->realtime(
                $actor,
            ),
            $method === "GET" && $path === "/chat" => $service->messages(
                $actor,
            ),
            $method === "POST" && $path === "/chat" => $service->sendMessage(
                $actor,
                $body,
            ),
            default => throw new SryError("notFound", 404),
        };
    }
    Response::success($result);
} catch (SryError $e) {
    Response::json(["success" => false, "code" => $e->errorCode], $e->status);
} catch (\PDOException $e) {
    error_log("SRY database error: " . $e->getCode());
    Response::json(
        [
            "success" => false,
            "code" => $e->getCode() === "23000" ? "conflict" : "failed",
        ],
        $e->getCode() === "23000" ? 409 : 500,
    );
} catch (\Throwable $e) {
    error_log("SRY request failed: " . get_class($e));
    Response::json(["success" => false, "code" => "failed"], 500);
}
