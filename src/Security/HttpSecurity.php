<?php

namespace Wsdd\PhpAdmin\Security;

use Wsdd\PhpAdmin\Audit\AuditLogger;
use Wsdd\PhpAdmin\Support\AppConfig;
final class HttpSecurity
{
    /**
     * @var \Wsdd\PhpAdmin\Support\AppConfig
     */
    private $config;
    /**
     * @var \Wsdd\PhpAdmin\Audit\AuditLogger
     */
    private $audit;
    public function __construct(AppConfig $config, AuditLogger $audit)
    {
        $this->config = $config;
        $this->audit = $audit;
    }
    /**
     * @return void
     */
    public function startSession()
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_name($this->config->sessionName());
        ini_set('session.cookie_httponly', '1');
        ini_set('session.use_strict_mode', '1');
        if (PHP_VERSION_ID >= 70300) ini_set('session.cookie_samesite', 'Strict');
        if (!session_start()) throw new \RuntimeException('Unable to start the administration session.');
        $this->sendLegacySessionCookie();
    }

    private function sendLegacySessionCookie()
    {
        if (PHP_VERSION_ID >= 70300) return;
        $params = session_get_cookie_params();
        $cookie = session_name() . '=' . rawurlencode(session_id()) . '; Path=' . $params['path'] . '; HttpOnly; SameSite=Strict';
        if ($params['domain'] !== '') $cookie .= '; Domain=' . $params['domain'];
        if ($params['secure']) $cookie .= '; Secure';
        if ($params['lifetime'] > 0) $cookie .= '; Max-Age=' . (int) $params['lifetime'];
        header('Set-Cookie: ' . $cookie, false);
    }
    /**
     * @return bool
     */
    public function isAuthenticated()
    {
        $this->startSession();
        return \Wsdd\PhpAdmin\Support\TypeGuard::check((isset($_SESSION['authenticated']) ? $_SESSION['authenticated'] : false) === true, 'bool');
    }
    /**
     * @return void
     */
    public function requireAuthenticated()
    {
        if (!$this->isAuthenticated()) {
            $this->audit->record($this->actor(), 'http_denied', $this->requestPath(), 'failed', ['remote_addr' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null]);
            if ($this->expectsJson()) {
                http_response_code(401);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['error' => 'Authentication required.'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                exit;
            }
            $this->renderLogin(null);
            exit;
        }
        if ((isset($_SESSION['force_password_change']) ? $_SESSION['force_password_change'] : false) === true && !$this->isPasswordChangeRequest()) {
            if ($this->expectsJson()) {
                http_response_code(403);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['error' => 'Password change required.'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                exit;
            }
            $this->renderPasswordChange(null);
            exit;
        }
    }
    /**
     * @return void
     */
    public function handleLogin()
    {
        $this->startSession();
        $username = isset($_POST['username']) ? $_POST['username'] : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $credentials = new CredentialStore($this->config);
        if (!is_string($username) || !is_string($password) || !$credentials->verify($username, $password)) {
            $this->audit->record($this->actor(), 'login', 'http_session', 'failed');
            $this->renderLogin('Invalid username or password.');
            exit;
        }
        session_regenerate_id(true);
        $this->sendLegacySessionCookie();
        $_SESSION['authenticated'] = true;
        $_SESSION['username'] = $credentials->username();
        $_SESSION['force_password_change'] = $credentials->mustChangePassword();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $this->audit->record($this->actor(), 'login', 'http_session', 'success');
        header('Location: ?view=overview', true, 303);
        exit;
    }
    /**
     * @return void
     */
    public function handlePasswordChange()
    {
        $this->startSession();
        if (!$this->isAuthenticated()) {
            $this->renderLogin('Sign in before changing the password.');
            exit;
        }
        $this->requireValidCsrf();
        $username = isset($_POST['username']) ? $_POST['username'] : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        $confirm = isset($_POST['password_confirm']) ? $_POST['password_confirm'] : '';
        if (!is_string($username) || !is_string($password) || !is_string($confirm) || $password !== $confirm) {
            $this->renderPasswordChange('Password confirmation does not match.');
            exit;
        }
        try {
            (new CredentialStore($this->config))->updatePassword($username, $password);
        } catch (\Exception $exception) {
            $this->renderPasswordChange($exception->getMessage());
            exit;
        } catch (\Throwable $exception) {
            $this->renderPasswordChange($exception->getMessage());
            exit;
        }
        $_SESSION['username'] = $username;
        $_SESSION['force_password_change'] = false;
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $this->audit->record($this->actor(), 'password_change', 'http_session', 'success');
        header('Location: ?view=overview', true, 303);
        exit;
    }
    /**
     * @return void
     */
    public function handleLogout()
    {
        $this->startSession();
        $this->audit->record($this->actor(), 'logout', 'http_session', 'success');
        $_SESSION = [];
        session_destroy();
        header('Location: ?view=overview', true, 303);
        exit;
    }
    /**
     * @return string
     */
    public function csrfToken()
    {
        $this->startSession();
        if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return \Wsdd\PhpAdmin\Support\TypeGuard::check($_SESSION['csrf_token'], 'string');
    }
    /**
     * @return void
     */
    public function requireValidCsrf()
    {
        $this->startSession();
        $sent = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : (isset($_SERVER['HTTP_X_WSDD_CSRF_TOKEN']) ? $_SERVER['HTTP_X_WSDD_CSRF_TOKEN'] : '');
        $expected = isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '';
        if (is_string($sent) && is_string($expected) && $expected !== '' && hash_equals($expected, $sent)) {
            return;
        }
        $this->audit->record($this->actor(), 'csrf_rejected', $this->requestPath(), 'failed');
        http_response_code(419);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Invalid CSRF token.'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }
    /**
     * @return string
     */
    public function actor()
    {
        $this->startSession();
        if ((isset($_SESSION['authenticated']) ? $_SESSION['authenticated'] : false) === true) {
            $username = isset($_SESSION['username']) ? $_SESSION['username'] : 'admin';
            return \Wsdd\PhpAdmin\Support\TypeGuard::check('web:' . (is_string($username) ? $username : 'admin'), 'string');
        }
        return \Wsdd\PhpAdmin\Support\TypeGuard::check('web-anonymous', 'string');
    }
    /**
     * @return string
     */
    public function securityModeLabel()
    {
        return \Wsdd\PhpAdmin\Support\TypeGuard::check((new CredentialStore($this->config))->mustChangePassword() ? 'default password' : 'password auth', 'string');
    }
    /**
     * @return string
     */
    public function defaultUsername()
    {
        return \Wsdd\PhpAdmin\Support\TypeGuard::check($this->config->defaultUsername(), 'string');
    }
    /**
     * @return bool
     */
    private function isLoopbackRequest()
    {
        $remote = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(in_array($remote, ['127.0.0.1', '::1'], true), 'bool');
    }
    /**
     * @return bool
     */
    private function isTrustedDevelopmentRequest()
    {
        if ($this->isLoopbackRequest()) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check(true, 'bool');
        }
        $host = strtolower((string) (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : ''));
        $normalizedHost = preg_replace('/:\d+$/', '', $host);
        $host = ($normalizedHost !== null) ? $normalizedHost : $host;
        if (substr_compare($host, '.wsdd.dock', -strlen('.wsdd.dock')) !== 0) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check(false, 'bool');
        }
        return \Wsdd\PhpAdmin\Support\TypeGuard::check($this->isPrivateNetworkAddress((string) (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '')), 'bool');
    }
    /**
     * @param string $address
     * @return bool
     */
    private function isPrivateNetworkAddress($address)
    {
        $address = \Wsdd\PhpAdmin\Support\TypeGuard::check($address, 'string');
        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check(false, 'bool');
        }
        $long = ip2long($address);
        if ($long === false) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check(false, 'bool');
        }
        $long = sprintf('%u', $long);
        return \Wsdd\PhpAdmin\Support\TypeGuard::check($this->ipInRange($long, '10.0.0.0', 8) || $this->ipInRange($long, '172.16.0.0', 12) || $this->ipInRange($long, '192.168.0.0', 16), 'bool');
    }
    /**
     * @param string $address
     * @param string $network
     * @param int $prefix
     * @return bool
     */
    private function ipInRange($address, $network, $prefix)
    {
        $address = \Wsdd\PhpAdmin\Support\TypeGuard::check($address, 'string');
        $network = \Wsdd\PhpAdmin\Support\TypeGuard::check($network, 'string');
        $prefix = \Wsdd\PhpAdmin\Support\TypeGuard::check($prefix, 'int');
        $addressLong = (int) $address;
        $networkLong = ip2long($network);
        if ($networkLong === false) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check(false, 'bool');
        }
        $mask = -1 << 32 - $prefix;
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(($addressLong & $mask) === ($networkLong & $mask), 'bool');
    }
    /**
     * @return bool
     */
    private function expectsJson()
    {
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(isset($_GET['api']) || strpos(isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : '', 'application/json') !== false, 'bool');
    }
    /**
     * @return string
     */
    private function requestPath()
    {
        return \Wsdd\PhpAdmin\Support\TypeGuard::check((string) (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/'), 'string');
    }
    /**
     * @return bool
     */
    private function isPasswordChangeRequest()
    {
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(isset($_GET['api']) && $_GET['api'] === 'change_password', 'bool');
    }
    /**
     * @param string|null $error
     * @return void
     */
    private function renderLogin($error)
    {
        $error = \Wsdd\PhpAdmin\Support\TypeGuard::check($error, '?string');
        header('Content-Type: text/html; charset=utf-8');
        $message = ($error === null) ? '' : ('<p class="error">' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</p>');
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<title>WSDD-PHP-Admin Login</title><style>' . $this->authStyles() . '</style></head><body><main><h1>WSDD-PHP-Admin</h1><p>Authentication is required for this admin surface.</p>';
        echo $message;
        echo '<form method="post" action="?api=login">';
        echo '<label>Username<input type="text" name="username" value="' . htmlspecialchars($this->defaultUsername(), ENT_QUOTES, 'UTF-8') . '" autofocus autocomplete="username"></label>';
        echo '<label>Password<input type="password" name="password" autocomplete="current-password"></label>';
        echo '<button type="submit">Sign in</button></form>';
        echo '</main></body></html>';
    }
    /**
     * @param string|null $error
     * @return void
     */
    private function renderPasswordChange($error)
    {
        $error = \Wsdd\PhpAdmin\Support\TypeGuard::check($error, '?string');
        header('Content-Type: text/html; charset=utf-8');
        $message = ($error === null) ? '' : ('<p class="error">' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</p>');
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<title>Change Password</title><style>' . $this->authStyles() . '</style></head><body><main><h1>Change password</h1><p>The default admin password must be changed before using the admin surface.</p>';
        echo $message;
        echo '<form method="post" action="?api=change_password">';
        echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($this->csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
        echo '<label>Username<input type="text" name="username" value="' . htmlspecialchars((string) (isset($_SESSION['username']) ? $_SESSION['username'] : $this->defaultUsername()), ENT_QUOTES, 'UTF-8') . '" autocomplete="username"></label>';
        echo '<label>New password<input type="password" name="password" autocomplete="new-password"></label>';
        echo '<label>Confirm password<input type="password" name="password_confirm" autocomplete="new-password"></label>';
        echo '<button type="submit">Save password</button></form>';
        echo '</main></body></html>';
    }
    /**
     * @return string
     */
    private function authStyles()
    {
        return \Wsdd\PhpAdmin\Support\TypeGuard::check('body{font-family:Segoe UI,Arial,sans-serif;background:#f2f4f7;color:#111827;margin:0;display:grid;place-items:center;min-height:100vh}' . 'main{background:#fff;border:1px solid #cbd5e1;padding:20px;width:min(460px,calc(100vw - 32px))}h1{font-size:22px;margin:0 0 8px}' . 'p{color:#4b5563}.error{color:#991b1b;font-weight:700}label{display:grid;gap:6px;font-weight:800;color:#4b5563;margin-top:10px}' . 'input{font:inherit;border:1px solid #cbd5e1;padding:8px}button{margin-top:12px;width:100%;font:inherit;font-weight:800;background:#1d4ed8;color:#fff;border:1px solid #1d4ed8;padding:9px}', 'string');
    }
}
