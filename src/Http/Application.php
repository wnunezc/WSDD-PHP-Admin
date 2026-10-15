<?php

namespace Wsdd\PhpAdmin\Http;

use Wsdd\PhpAdmin\Audit\AuditLogger;
use Wsdd\PhpAdmin\Extensions\ExtensionCatalog;
use Wsdd\PhpAdmin\Permissions\PermissionChecker;
use Wsdd\PhpAdmin\Permissions\PermissionReport;
use Wsdd\PhpAdmin\Operations\OperationRepository;
use Wsdd\PhpAdmin\Operations\OperationService;
use Wsdd\PhpAdmin\Operations\OperationType;
use Wsdd\PhpAdmin\Profile\ProfileLoadResult;
use Wsdd\PhpAdmin\Profile\ProfileRepository;
use Wsdd\PhpAdmin\Reconciliation\ReadOnlyReconciler;
use Wsdd\PhpAdmin\Reconciliation\ReconciliationReport;
use Wsdd\PhpAdmin\Runtime\RuntimeDetector;
use Wsdd\PhpAdmin\Security\HttpSecurity;
use Wsdd\PhpAdmin\Support\AppConfig;
final class Application
{
    /**
     * @var array<string, string>
     */
    const VIEWS = ['overview' => 'Overview', 'runtime' => 'Runtime', 'extensions' => 'Extensions', 'profile' => 'Profile', 'operations' => 'Operations', 'permissions' => 'Permissions', 'reconciliation' => 'Reconciliation'];
    /**
     * @return void
     */
    public function handle()
    {
        $config = AppConfig::loadDefault();
        $audit = new AuditLogger($config);
        $security = new HttpSecurity($config, $audit);
        if (isset($_GET['api']) && $_GET['api'] === 'login') {
            $security->handleLogin();
        }
        if (isset($_GET['api']) && $_GET['api'] === 'change_password') {
            $security->handlePasswordChange();
        }
        if (isset($_GET['api']) && $_GET['api'] === 'logout') {
            $security->handleLogout();
        }
        $security->requireAuthenticated();
        $runtime = (new RuntimeDetector())->detect();
        $detection = $runtime->toArray();
        $permissions = (new PermissionChecker($config))->check();
        $profile = (new ProfileRepository($config))->load();
        $reconciliation = (new ReadOnlyReconciler())->reconcile($profile, $runtime);
        $operations = new OperationRepository($config);
        if (isset($_GET['api']) && is_string($_GET['api'])) {
            $this->handleApi($_GET['api'], $detection, $permissions->toArray(), $profile->toArray(), $reconciliation->toArray(), $operations, $security, $audit);
            return;
        }
        $view = $this->activeView();
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html>';
        echo '<html lang="en">';
        echo '<head>';
        echo '<meta charset="utf-8">';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<title>WSDD-PHP-Admin</title>';
        echo '<style>' . $this->styles() . '</style>';
        echo '</head>';
        echo '<body>';
        echo '<div class="app-shell">';
        echo '<aside class="sidebar">';
        echo '<div class="brand"><a href="?view=overview">WSDD-PHP-Admin</a><span>PHP container control</span></div>';
        echo '<nav class="nav" aria-label="Primary navigation">';
        foreach (self::VIEWS as $key => $label) {
            echo '<a class="' . (($key === $view) ? 'active' : '') . '" href="?view=' . $this->escape($key) . '">' . $this->escape($label) . '</a>';
        }
        echo '</nav>';
        echo '<div class="side-note">Managed changes are queued for the root agent. The web process does not edit PHP configuration directly.</div>';
        echo '</aside>';
        echo '<main class="content">';
        echo '<header class="page-header">';
        echo '<div>';
        echo '<h1>' . $this->escape(self::VIEWS[$view]) . '</h1>';
        echo '<p>' . $this->escape($this->viewDescription($view)) . '</p>';
        echo '</div>';
        echo '<div class="status-stack">';
        echo $this->statusPill('PHP ' . $detection['php']['version'], 'neutral');
        echo $this->statusPill((string) $detection['php']['runtime'], 'neutral');
        echo $this->statusPill($profile->isLoaded() ? 'Profile loaded' : 'Profile missing', $profile->isLoaded() ? 'ok' : 'warn');
        echo $this->statusPill('Security: ' . $security->securityModeLabel(), 'neutral');
        echo '<a class="button secondary compact" href="?api=logout">Logout</a>';
        echo '</div>';
        echo '</header>';
        echo $this->renderView($view, $config, $detection, $permissions, $profile, $reconciliation, $operations, $security);
        echo '</main>';
        echo '</div>';
        echo '<script>' . $this->scripts() . '</script>';
        echo '</body>';
        echo '</html>';
    }
    /**
     * @return string
     */
    private function activeView()
    {
        $view = isset($_GET['view']) ? $_GET['view'] : 'overview';
        if (!is_string($view) || !array_key_exists($view, self::VIEWS)) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check('overview', 'string');
        }
        return \Wsdd\PhpAdmin\Support\TypeGuard::check($view, 'string');
    }
    /**
     * @param string $view
     * @return string
     */
    private function viewDescription($view)
    {
        $view = \Wsdd\PhpAdmin\Support\TypeGuard::check($view, 'string');
        $descriptions = ['runtime' => 'Detected PHP runtime, configuration files, and container tools.', 'extensions' => 'Loaded PHP extensions observed in the current runtime.', 'profile' => 'Persistent desired-state profile used by reconciliation.', 'operations' => 'Persistent operation queue consumed by the root agent.', 'permissions' => 'Write/read capability checks before sensitive operations.', 'reconciliation' => 'Read-only comparison between desired profile and observed runtime.'];
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(isset($descriptions[$view]) ? $descriptions[$view] : 'Current health of this PHP container admin installation.', 'string');
    }
    /**
     * @param array<string, mixed> $detection
     * @param string $view
     * @return string
     */
    private function renderView($view, AppConfig $config, array $detection, PermissionReport $permissions, ProfileLoadResult $profile, ReconciliationReport $reconciliation, OperationRepository $operations, HttpSecurity $security)
    {
        $view = \Wsdd\PhpAdmin\Support\TypeGuard::check($view, 'string');
        switch ($view) {
            case 'runtime':
                return \Wsdd\PhpAdmin\Support\TypeGuard::check($this->runtimeView($config, $detection), 'string');
            case 'extensions':
                return \Wsdd\PhpAdmin\Support\TypeGuard::check($this->extensionsView($detection), 'string');
            case 'profile':
                return \Wsdd\PhpAdmin\Support\TypeGuard::check($this->profileView($profile), 'string');
            case 'operations':
                return \Wsdd\PhpAdmin\Support\TypeGuard::check($this->operationsView($operations, $detection, $permissions, $security), 'string');
            case 'permissions':
                return \Wsdd\PhpAdmin\Support\TypeGuard::check($this->permissionsView($permissions), 'string');
            case 'reconciliation':
                return \Wsdd\PhpAdmin\Support\TypeGuard::check($this->reconciliationView($reconciliation), 'string');
            default:
                return \Wsdd\PhpAdmin\Support\TypeGuard::check($this->overviewView($config, $detection, $permissions, $profile, $reconciliation), 'string');
        }
    }
    /**
     * @param array<string, mixed> $detection
     * @return string
     */
    private function overviewView(AppConfig $config, array $detection, PermissionReport $permissions, ProfileLoadResult $profile, ReconciliationReport $reconciliation)
    {
        $html = '<section class="summary-grid">';
        $html .= $this->metricCard('PHP version', $detection['php']['version'], 'Runtime target observed.');
        $html .= $this->metricCard('Runtime', $detection['php']['runtime'] . ' / ' . $detection['php']['sapi'], 'Current execution mode.');
        $html .= $this->metricCard('Extensions', (string) $detection['extensions']['loaded_count'], 'Loaded modules.');
        $html .= $this->metricCard('Effective user', isset($detection['system']['effective_user']) ? $detection['system']['effective_user'] : 'unknown', 'Actor used by the web process.');
        $html .= $this->metricCard('Profile', $profile->isLoaded() ? 'loaded' : ($profile->exists ? 'error' : 'missing'), 'Persistent desired state.');
        $html .= $this->metricCard('Reconciliation', $reconciliation->issueCount() . ' issue(s)', 'Read-only drift check.');
        $html .= '</section>';
        $hasPermissionIssue = !$permissions->canUsePersistentProfile() || !$permissions->canUseOperationsQueue() || !$permissions->agentAvailable;
        $needsAttention = $hasPermissionIssue || $reconciliation->hasActionableIssues();
        $html .= '<section class="panel ' . ($needsAttention ? 'attention' : 'healthy') . '">';
        $html .= '<div><h2>' . ($needsAttention ? 'Needs attention' : 'Ready') . '</h2><p>' . $this->escape($this->overviewAttentionMessage($permissions, $reconciliation)) . '</p></div>';
        $html .= '<div class="action-row">';
        $html .= '<a class="button" href="?view=permissions">Review permissions</a>';
        $html .= '<a class="button secondary" href="?view=reconciliation">Check reconciliation</a>';
        $html .= '</div>';
        $html .= '</section>';
        $html .= '<section class="overview-columns">';
        $html .= '<div class="panel"><h2>Runtime snapshot</h2><table>';
        $html .= $this->row('PHP binary', $detection['php']['binary']);
        $html .= $this->row('php.ini', isset($detection['php']['ini_file']) ? $detection['php']['ini_file'] : 'none detected');
        $html .= $this->row('Managed INI prefix', $config->managedIniPrefix());
        $html .= $this->row('Tools', $this->toolsSummary($detection), false);
        $html .= '</table></div>';
        $html .= '<div class="panel"><h2>Blocking checks</h2><table>';
        foreach ($permissions->paths as $path) {
            if ($path->label === 'Managed INI directory' && !$path->canWriteNowOrCreate() && $permissions->agentAvailable) {
                $status = '<span class="badge warn">agent-only</span>';
            } else {
                $status = $path->canWriteNowOrCreate() ? '<span class="badge ok">ok</span>' : '<span class="badge bad">blocked</span>';
            }
            $html .= $this->row($path->label, $status, false);
        }
        $html .= '</table></div>';
        $html .= '</section>';
        $html .= '<section class="route-grid">';
        foreach (self::VIEWS as $key => $label) {
            if ($key === 'overview') {
                continue;
            }
            $html .= '<a class="route-card" href="?view=' . $this->escape($key) . '">';
            $html .= '<strong>' . $this->escape($label) . '</strong>';
            $html .= '<span>' . $this->escape($this->viewDescription($key)) . '</span>';
            $html .= '</a>';
        }
        $html .= '</section>';
        return \Wsdd\PhpAdmin\Support\TypeGuard::check($html, 'string');
    }
    /**
     * @return string
     */
    private function overviewAttentionMessage(PermissionReport $permissions, ReconciliationReport $reconciliation)
    {
        if (!$permissions->canUsePersistentProfile()) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check('Persistent profile is not writable.', 'string');
        }
        if (!$permissions->canUseOperationsQueue()) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check('Operations queue is not writable.', 'string');
        }
        if (!$permissions->agentAvailable) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check('Agent is not available to apply managed changes.', 'string');
        }
        if ($reconciliation->hasActionableIssues()) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check('Profile drift requires review.', 'string');
        }
        return \Wsdd\PhpAdmin\Support\TypeGuard::check('Profile, operations queue, agent and tracked extensions are aligned.', 'string');
    }
    /**
     * @param array<string, mixed> $detection
     * @return string
     */
    private function toolsSummary(array $detection)
    {
        $available = [];
        foreach (array_merge($detection['system']['tools'], $detection['system']['package_managers']) as $tool => $isAvailable) {
            if ($isAvailable) {
                $available[] = $tool;
            }
        }
        return \Wsdd\PhpAdmin\Support\TypeGuard::check($this->escape(implode(', ', $available)), 'string');
    }
    /**
     * @param array<string, mixed> $detection
     * @return string
     */
    private function runtimeView(AppConfig $config, array $detection)
    {
        $html = '<section class="panel"><h2>PHP runtime</h2><table>';
        $html .= $this->row('PHP binary', $detection['php']['binary']);
        $html .= $this->row('php.ini', isset($detection['php']['ini_file']) ? $detection['php']['ini_file'] : 'none detected');
        $html .= $this->row('INI scan dir', isset($detection['php']['ini_scan_dir']) ? $detection['php']['ini_scan_dir'] : 'none detected');
        $html .= $this->row('Managed INI dir', $config->managedIniDir());
        $html .= $this->row('Managed INI prefix', $config->managedIniPrefix());
        $html .= $this->row('OS family', $detection['system']['os_family']);
        $html .= '</table></section>';
        $html .= '<section class="panel"><h2>Container tools</h2><table>';
        foreach ($detection['system']['tools'] as $tool => $available) {
            $html .= $this->row($tool, $this->availability($available), false);
        }
        foreach ($detection['system']['package_managers'] as $tool => $available) {
            $html .= $this->row($tool, $this->availability($available), false);
        }
        $html .= '</table></section>';
        $catalog = new ExtensionCatalog();
        $html .= '<section class="panel"><div class="section-title"><h2>Compatibility matrix</h2>';
        $html .= '<span>catalog status: ' . $this->escape($catalog->catalogStatusForPhpVersion((string) $detection['php']['version'])) . '</span></div><table>';
        foreach ($catalog->compatibilityMatrix() as $version => $row) {
            $html .= $this->row('PHP ' . $version, 'runtime: ' . $this->escape($row['app_runtime']) . '<br>catalog: ' . $this->escape($row['install_catalog']) . '<br>validation: ' . $this->escape($row['validation']), false);
        }
        $html .= '</table></section>';
        return \Wsdd\PhpAdmin\Support\TypeGuard::check($html, 'string');
    }
    /**
     * @param array<string, mixed> $detection
     * @return string
     */
    private function extensionsView(array $detection)
    {
        $html = '<section class="panel"><div class="section-title"><h2>Loaded extensions</h2>';
        $html .= '<span>' . $this->escape((string) $detection['extensions']['loaded_count']) . ' detected</span></div>';
        $html .= '<div class="extension-list">';
        foreach ($detection['extensions']['loaded'] as $extension) {
            $html .= '<span>' . $this->escape($extension) . '</span>';
        }
        $html .= '</div></section>';
        $catalog = (new ExtensionCatalog())->status(is_array(isset($detection['extensions']['loaded']) ? $detection['extensions']['loaded'] : null) ? array_values($detection['extensions']['loaded']) : []);
        $html .= '<section class="panel"><div class="section-title"><h2>Extension catalog - PHP ' . $this->escape((string) $detection['php']['version']) . '</h2>';
        $recipeCount = count(array_filter($catalog, static function (array $entry) {
            return \Wsdd\PhpAdmin\Support\TypeGuard::check($entry['recipe_available'], 'bool');
        }));
        $html .= '<span>' . $recipeCount . ' reviewed recipes / ' . count($catalog) . ' entries</span></div><table>';
        foreach ($catalog as $extension => $entry) {
            $state = $entry['loaded'] ? '<span class="badge ok">loaded</span>' : ($entry['available'] ? '<span class="badge warn">available</span>' : ($entry['installable'] ? '<span class="badge warn">installable</span>' : '<span class="badge bad">unavailable</span>'));
            $packages = (is_array($entry['packages']) && $entry['packages'] !== []) ? implode(', ', $entry['packages']) : 'none';
            $html .= $this->row($extension, $state . '<br><span class="code">' . $this->escape((string) (isset($entry['install_method']) ? $entry['install_method'] : 'No reviewed installation method')) . '</span><br>packages: ' . $this->escape($packages) . (($entry['reason'] === null) ? '' : ('<br>' . $this->escape($entry['reason']))), false);
        }
        $html .= '</table></section>';
        return \Wsdd\PhpAdmin\Support\TypeGuard::check($html, 'string');
    }
    /**
     * @return string
     */
    private function profileView(ProfileLoadResult $profile)
    {
        $html = '<section class="panel"><h2>Persistent profile</h2><table>';
        $html .= $this->row('Profile path', $profile->path);
        $html .= $this->row('Exists', $profile->exists ? 'yes' : 'no');
        $html .= $this->row('Loaded', $profile->isLoaded() ? 'yes' : 'no');
        $html .= $this->row('Error', isset($profile->error) ? $profile->error : 'none');
        $html .= '</table></section>';
        return \Wsdd\PhpAdmin\Support\TypeGuard::check($html, 'string');
    }
    /**
     * @param array<string, mixed> $detection
     * @return string
     */
    private function operationsView(OperationRepository $operations, array $detection, PermissionReport $permissions, HttpSecurity $security)
    {
        $listing = $operations->listing();
        $items = is_array(isset($listing['items']) ? $listing['items'] : null) ? $listing['items'] : [];
        $extensionOptions = $this->extensionOptions($detection);
        $encodedOptions = json_encode($extensionOptions, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES);
        $canQueueOperations = $permissions->canUseOperationsQueue();
        if (!is_string($encodedOptions)) {
            $encodedOptions = '{}';
        }
        $html = '<section class="panel" data-operations-root><div class="section-title"><h2>Operation queue</h2>';
        $html .= '<span data-operations-count>' . $this->escape((string) count($items)) . ' item(s)</span></div><table>';
        $html .= $this->row('Operations dir', (string) (isset($listing['operations_dir']) ? $listing['operations_dir'] : 'unknown'));
        $html .= $this->row('Execution model', 'Web creates operation JSON; root agent applies supported changes.');
        $html .= '</table></section>';
        $html .= '<section class="panel"><div class="section-title"><h2>Create operation</h2><span>queued only</span></div>';
        if (!$canQueueOperations) {
            $html .= '<p class="inline-warning">Operations cannot be queued until /var/lib/wsdd-php-admin/operations exists and is writable by the web process.</p>';
        }
        $html .= '<form class="operation-form" method="post" action="?api=operations_create" data-operation-form data-extension-options=\'' . $encodedOptions . '\' data-queue-enabled="' . ($canQueueOperations ? '1' : '0') . '">';
        $html .= '<input type="hidden" name="csrf_token" value="' . $this->escape($security->csrfToken()) . '">';
        $html .= '<label>Type<select name="type">';
        foreach (OperationType::all() as $type) {
            $html .= '<option value="' . $this->escape($type) . '">' . $this->escape($type) . '</option>';
        }
        $html .= '</select></label>';
        $html .= '<label>Extension<select name="extension" data-extension-select></select></label>';
        $html .= '<button class="button" type="submit">Queue operation</button>';
        $html .= '<span class="form-status" data-operation-form-status></span>';
        $html .= '</form></section>';
        $html .= '<section class="panel"><h2>Items</h2><table><tbody data-operations-items>';
        if ($items === []) {
            $html .= $this->row('Queue', 'No operations found.');
        }
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $operation = is_array(isset($item['operation']) ? $item['operation'] : null) ? $item['operation'] : [];
            $result = is_array(isset($item['result']) ? $item['result'] : null) ? $item['result'] : null;
            $label = (string) (isset($operation['type']) ? $operation['type'] : 'unknown') . ' / ' . (string) (isset($operation['extension']) ? $operation['extension'] : 'none');
            $statusValue = ($result === null) ? 'pending' : (string) (isset($result['status']) ? $result['status'] : 'done');
            $statusTone = $this->operationStatusTone($statusValue);
            $status = '<span class="badge ' . $statusTone . '">' . $this->escape($statusValue) . '</span>';
            $message = ($result === null) ? 'Waiting for agent.' : (string) (isset($result['message']) ? $result['message'] : 'Result recorded.');
            $html .= $this->row($label, $status . '<br>' . $this->escape($message), false);
        }
        $html .= '</tbody></table></section>';
        return \Wsdd\PhpAdmin\Support\TypeGuard::check($html, 'string');
    }
    /**
     * @return string
     */
    private function permissionsView(PermissionReport $permissions)
    {
        $html = '<section class="panel"><div class="section-title"><h2>Managed paths</h2>';
        $html .= '<span>' . ($permissions->canUsePersistentProfile() ? 'Profile writable' : 'Profile blocked') . '</span></div><table>';
        foreach ($permissions->paths as $path) {
            $status = $path->canWriteNowOrCreate() ? '<span class="badge ok">write/create ok</span>' : '<span class="badge bad">not writable</span>';
            $html .= $this->row($path->label, '<span class="code">' . $this->escape($path->path) . '</span><br>' . $status, false);
        }
        $html .= '</table></section>';
        return \Wsdd\PhpAdmin\Support\TypeGuard::check($html, 'string');
    }
    /**
     * @return string
     */
    private function reconciliationView(ReconciliationReport $reconciliation)
    {
        $html = '<section class="panel"><div class="section-title"><h2>Read-only reconciliation</h2>';
        $html .= '<span>' . $this->escape($reconciliation->issueCount() . ' issue(s)') . '</span></div><table>';
        foreach ($reconciliation->issues as $issue) {
            $html .= $this->row($issue->severity . ' / ' . $issue->subject, $issue->message);
        }
        $html .= '</table></section>';
        return \Wsdd\PhpAdmin\Support\TypeGuard::check($html, 'string');
    }
    /**
     * @param string $status
     * @return string
     */
    private function operationStatusTone($status)
    {
        $status = \Wsdd\PhpAdmin\Support\TypeGuard::check($status, 'string');
        $tones = ['success' => 'ok', 'failed' => 'bad'];
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(isset($tones[$status]) ? $tones[$status] : 'warn', 'string');
    }
    /**
     * @param array<string, mixed> $detection
     * @return array<string, list<string>>
     */
    private function extensionOptions(array $detection)
    {
        $loaded = isset($detection['extensions']['loaded']) ? $detection['extensions']['loaded'] : [];
        return \Wsdd\PhpAdmin\Support\TypeGuard::check((new ExtensionCatalog())->operationOptions(is_array($loaded) ? array_values($loaded) : []), 'array');
    }
    /**
     * @param string $label
     * @param string $value
     * @param string $hint
     * @return string
     */
    private function metricCard($label, $value, $hint)
    {
        $label = \Wsdd\PhpAdmin\Support\TypeGuard::check($label, 'string');
        $value = \Wsdd\PhpAdmin\Support\TypeGuard::check($value, 'string');
        $hint = \Wsdd\PhpAdmin\Support\TypeGuard::check($hint, 'string');
        return \Wsdd\PhpAdmin\Support\TypeGuard::check('<article class="metric"><span>' . $this->escape($label) . '</span><strong>' . $this->escape($value) . '</strong><small>' . $this->escape($hint) . '</small></article>', 'string');
    }
    /**
     * @param string $label
     * @param string $tone
     * @return string
     */
    private function statusPill($label, $tone)
    {
        $label = \Wsdd\PhpAdmin\Support\TypeGuard::check($label, 'string');
        $tone = \Wsdd\PhpAdmin\Support\TypeGuard::check($tone, 'string');
        return \Wsdd\PhpAdmin\Support\TypeGuard::check('<span class="pill ' . $this->escape($tone) . '">' . $this->escape($label) . '</span>', 'string');
    }
    /**
     * @param bool $available
     * @return string
     */
    private function availability($available)
    {
        $available = \Wsdd\PhpAdmin\Support\TypeGuard::check($available, 'bool');
        return \Wsdd\PhpAdmin\Support\TypeGuard::check($available ? '<span class="badge ok">available</span>' : '<span class="badge warn">missing</span>', 'string');
    }
    /**
     * @param string $label
     * @param string $value
     * @param bool $escapeValue
     * @return string
     */
    private function row($label, $value, $escapeValue = true)
    {
        $label = \Wsdd\PhpAdmin\Support\TypeGuard::check($label, 'string');
        $value = \Wsdd\PhpAdmin\Support\TypeGuard::check($value, 'string');
        $escapeValue = \Wsdd\PhpAdmin\Support\TypeGuard::check($escapeValue, 'bool');
        return \Wsdd\PhpAdmin\Support\TypeGuard::check('<tr><th>' . $this->escape($label) . '</th><td>' . ($escapeValue ? $this->escape($value) : $value) . '</td></tr>', 'string');
    }
    /**
     * @param string $value
     * @return string
     */
    private function escape($value)
    {
        $value = \Wsdd\PhpAdmin\Support\TypeGuard::check($value, 'string');
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(htmlspecialchars($value, ENT_QUOTES, 'UTF-8'), 'string');
    }
    /**
     * @return string
     */
    private function styles()
    {
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(<<<'CSS'
:root{--bg:#f2f4f7;--surface:#fff;--ink:#111827;--muted:#4b5563;--line:#cbd5e1;--line-soft:#e5e7eb;--nav:#1f2937;--nav-muted:#d1d5db;--accent:#1d4ed8;--ok:#166534;--warn:#92400e;--bad:#991b1b}
*{box-sizing:border-box}
body{font-family:Segoe UI,Arial,sans-serif;margin:0;background:var(--bg);color:var(--ink);font-size:14px}
a{color:inherit;text-decoration:none}
.app-shell{display:grid;grid-template-columns:236px minmax(0,1fr);min-height:100vh}
.sidebar{background:var(--nav);color:#fff;padding:16px 12px;display:flex;flex-direction:column;gap:14px;position:sticky;top:0;height:100vh}
.brand a{display:block;font-size:18px;font-weight:800;line-height:1.1}
.brand span{display:block;color:var(--nav-muted);font-size:12px;margin-top:4px}
.nav{display:flex;flex-direction:column;gap:3px}
.nav a{border:1px solid transparent;color:var(--nav-muted);font-weight:700;padding:8px 9px}
.nav a:hover,.nav a.active{background:#374151;border-color:#4b5563;color:#fff}
.side-note{margin-top:auto;border-top:1px solid #4b5563;color:var(--nav-muted);font-size:12px;line-height:1.35;padding-top:10px}
.content{padding:18px;max-width:1440px;width:100%}
.page-header{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;margin-bottom:14px;border-bottom:1px solid var(--line);padding-bottom:12px}
h1{font-size:24px;line-height:1.1;margin:0 0 4px}
h2{font-size:16px;margin:0 0 10px}
p{color:var(--muted);line-height:1.35;margin:0}
.status-stack{display:flex;flex-wrap:wrap;justify-content:flex-end;gap:6px;min-width:240px}
.pill,.badge{display:inline-flex;align-items:center;border:1px solid currentColor;font-size:12px;font-weight:800;line-height:1;padding:4px 7px}
.pill.neutral,.badge.warn{background:#f8fafc;color:#374151}
.pill.ok,.badge.ok{background:#f0fdf4;color:var(--ok)}
.pill.warn{background:#fffbeb;color:var(--warn)}
.badge.bad{background:#fef2f2;color:var(--bad)}
.summary-grid{display:grid;grid-template-columns:repeat(6,minmax(130px,1fr));gap:8px}
.route-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:8px}
.overview-columns{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:10px}
.metric,.panel,.route-card{background:var(--surface);border:1px solid var(--line);border-radius:0}
.metric{padding:10px}
.metric span{display:block;color:var(--muted);font-size:12px;font-weight:800;margin-bottom:4px}
.metric strong{display:block;font-size:18px;line-height:1.1;margin-bottom:4px}
.metric small{color:var(--muted);font-size:12px;line-height:1.25}
.panel{margin-top:10px;padding:12px}
.attention{display:flex;justify-content:space-between;gap:14px;align-items:center;border-left:4px solid var(--accent)}
.healthy{display:flex;justify-content:space-between;gap:14px;align-items:center;border-left:4px solid var(--ok)}
.action-row{display:flex;flex-wrap:wrap;gap:8px}
.button{background:var(--accent);border:1px solid var(--accent);color:#fff;font-weight:800;padding:8px 10px}
.button:disabled{background:#9ca3af;border-color:#9ca3af;cursor:not-allowed}
.button.secondary{background:#fff;color:var(--accent)}
.button.compact{padding:4px 7px;font-size:12px;line-height:1}
.route-card{display:block;padding:10px}
.route-card strong{display:block;margin-bottom:4px}
.route-card span{color:var(--muted);display:block;font-size:13px;line-height:1.3}
.section-title{display:flex;justify-content:space-between;gap:12px;align-items:baseline}
.section-title span{color:var(--muted);font-size:12px;font-weight:800}
table{width:100%;border-collapse:collapse}
th,td{text-align:left;padding:7px 9px;border-bottom:1px solid var(--line-soft);vertical-align:top}
th{width:250px;background:#f3f4f6;font-weight:800}
tr:last-child th,tr:last-child td{border-bottom:0}
.code{font-family:Consolas,ui-monospace,monospace;font-size:.92em}
.extension-list{display:grid;grid-template-columns:repeat(auto-fit,minmax(135px,1fr));gap:5px}
.extension-list span{background:#f8fafc;border:1px solid var(--line-soft);padding:5px 7px}
.operation-form{display:grid;grid-template-columns:220px 230px minmax(260px,1fr);column-gap:8px;row-gap:6px;align-items:end}
.operation-form label{display:grid;gap:4px;color:var(--muted);font-size:12px;font-weight:800}
.operation-form input,.operation-form select{border:1px solid var(--line);font:inherit;padding:7px 8px;background:#fff;color:var(--ink)}
.operation-form select:disabled{background:#f3f4f6;color:#6b7280}
.operation-form .button{width:100%;min-height:37px}
.form-status{grid-column:1/-1;color:var(--muted);font-size:12px;font-weight:800}
.inline-warning{border-left:4px solid var(--warn);background:#fffbeb;color:var(--warn);font-weight:700;margin:0 0 10px;padding:8px 10px}
@media(max-width:860px){
  .app-shell{display:block}
  .sidebar{height:auto;position:relative;padding:12px}
  .nav{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:4px}
  .side-note{display:none}
  .content{padding:12px}
  .page-header{display:block}
  .status-stack{justify-content:flex-start;margin-top:8px;min-width:0}
  .summary-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
  .overview-columns{grid-template-columns:1fr}
  .attention{display:block}
  .action-row{margin-top:10px}
  .operation-form{grid-template-columns:1fr}
  th{width:auto}
  th,td{display:block}
}
CSS
, 'string');
    }
    /**
     * @return string
     */
    private function scripts()
    {
        return \Wsdd\PhpAdmin\Support\TypeGuard::check(<<<'JS'
(() => {
  const root = document.querySelector('[data-operations-root]');
  if (!root) {
    const refreshView = async () => {
      try {
        if (!document.hidden) {
          const response = await fetch(window.location.href, { cache: 'no-store' });
          if (!response.ok) throw new Error(`HTTP ${response.status}`);
          const page = new DOMParser().parseFromString(await response.text(), 'text/html');
          const content = page.querySelector('main.content');
          if (!content) throw new Error('Authenticated runtime view unavailable');
          document.querySelector('main.content').replaceWith(content);
        }
      } catch (error) {
        const stack = document.querySelector('.status-stack');
        if (stack && !stack.querySelector('[data-runtime-refresh-error]')) {
          const warning = document.createElement('span');
          warning.className = 'pill bad';
          warning.dataset.runtimeRefreshError = '1';
          warning.textContent = 'Runtime update unavailable';
          stack.append(warning);
        }
      } finally {
        window.setTimeout(refreshView, 5000);
      }
    };
    window.setTimeout(refreshView, 5000);
    return;
  }

  const form = document.querySelector('[data-operation-form]');
  const typeSelect = form?.querySelector('select[name="type"]');
  const extensionSelect = form?.querySelector('[data-extension-select]');
  const submitButton = form?.querySelector('button[type="submit"]');
  const status = document.querySelector('[data-operation-form-status]');
  const count = document.querySelector('[data-operations-count]');
  const items = document.querySelector('[data-operations-items]');
  const normalPollMs = 2000;
  const maxBackoffMs = 10000;
  const reconnectTimeoutMs = 90000;
  const queueEnabled = form?.dataset.queueEnabled === '1';
  let nextPollMs = normalPollMs;
  let failedSince = null;
  let timer = null;
  let extensionOptions = {};

  try {
    extensionOptions = JSON.parse(form?.dataset.extensionOptions || '{}');
  } catch (error) {
    extensionOptions = {};
  }

  const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;'
  }[char]));

  const render = (payload) => {
    const rows = Array.isArray(payload.items) ? payload.items : [];
    count.textContent = `${rows.length} item(s)`;
    failedSince = null;
    nextPollMs = normalPollMs;
    if (status?.dataset.state === 'reconnecting') {
      status.textContent = 'Connected.';
      status.dataset.state = '';
    }

    if (rows.length === 0) {
      items.innerHTML = '<tr><th>Queue</th><td>No operations found.</td></tr>';
      return;
    }

    items.innerHTML = rows.map((item) => {
      const operation = item.operation || {};
      const result = item.result || null;
      const label = `${operation.type || 'unknown'} / ${operation.extension || 'none'}`;
      const tone = result ? (result.status === 'failed' ? 'bad' : (result.status === 'success' ? 'ok' : 'warn')) : 'warn';
      const text = result ? (result.status || 'done') : 'pending';
      const message = result ? (result.message || 'Result recorded.') : 'Waiting for agent.';

      return `<tr><th>${escapeHtml(label)}</th><td><span class="badge ${tone}">${escapeHtml(text)}</span><br>${escapeHtml(message)}</td></tr>`;
    }).join('');
  };

  const scheduleRefresh = (delay = nextPollMs) => {
    window.clearTimeout(timer);
    timer = window.setTimeout(refresh, delay);
  };

  const markRefreshFailure = () => {
    const now = Date.now();
    failedSince = failedSince || now;
    nextPollMs = Math.min(nextPollMs * 2, maxBackoffMs);

    if (status) {
      status.dataset.state = 'reconnecting';
      status.textContent = now - failedSince > reconnectTimeoutMs
        ? 'Connection still unavailable. Waiting for PHP admin to return...'
        : 'Reconnecting after service restart...';
    }
  };

  const refresh = async () => {
    try {
      const response = await fetch('?api=state', { headers: { Accept: 'application/json' }, cache: 'no-store' });
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }
      const payload = await response.json();
      render(payload.operations);
      extensionOptions = payload.operation_options || {};
      updateExtensionSelect();
    } catch (error) {
      markRefreshFailure();
    } finally {
      scheduleRefresh();
    }
  };

  const updateExtensionSelect = () => {
    if (!typeSelect || !extensionSelect) return;

    const type = typeSelect.value;
    const options = Array.isArray(extensionOptions[type]) ? extensionOptions[type] : [];
    const selectedExtension = extensionSelect.value;
    extensionSelect.innerHTML = '';
    extensionSelect.disabled = !queueEnabled || type === 'reconcile' || options.length === 0;
    if (submitButton) {
      submitButton.disabled = !queueEnabled || (type !== 'reconcile' && options.length === 0);
    }

    if (!queueEnabled) {
      extensionSelect.append(new Option('Operations queue is not writable', ''));
      if (status && status.dataset.state !== 'reconnecting') status.textContent = 'Queue unavailable.';
      return;
    }

    if (type === 'reconcile') {
      extensionSelect.append(new Option('Not required', ''));
      if (status && status.dataset.state !== 'reconnecting') status.textContent = '';
      return;
    }

    if (options.length === 0) {
      extensionSelect.append(new Option('No extensions available for this action', ''));
      if (status && status.dataset.state !== 'reconnecting') status.textContent = 'No controlled extension options for this action.';
      return;
    }

    if (status && status.dataset.state !== 'reconnecting') status.textContent = '';
    options.forEach((extension) => extensionSelect.append(new Option(extension, extension)));
    if (options.includes(selectedExtension)) extensionSelect.value = selectedExtension;
  };

  typeSelect?.addEventListener('change', updateExtensionSelect);
  updateExtensionSelect();

  form?.addEventListener('submit', async (event) => {
    event.preventDefault();

    if (!queueEnabled) {
      status.textContent = 'Operations queue is not writable.';
      return;
    }

    status.textContent = 'Queueing...';

    try {
      const response = await fetch(form.action, {
        method: 'POST',
        headers: { Accept: 'application/json' },
        body: new FormData(form)
      });
      const payload = await response.json().catch(() => ({ error: 'Operation could not be queued.' }));

      if (!response.ok || payload.error) {
        status.textContent = payload.error || 'Operation could not be queued.';
        return;
      }
    } catch (error) {
      status.textContent = 'Operation could not be queued.';
      return;
    }

    status.textContent = 'Queued. Waiting for agent...';
    form.reset();
    updateExtensionSelect();
    await refresh();
  });

  refresh();
})();
JS
, 'string');
    }
    /**
     * @param array<string, mixed> $runtime
     * @param array<string, mixed> $permissions
     * @param array<string, mixed> $profile
     * @param array<string, mixed> $reconciliation
     * @param string $resource
     * @return void
     */
    private function handleApi($resource, array $runtime, array $permissions, array $profile, array $reconciliation, OperationRepository $operations, HttpSecurity $security, AuditLogger $audit)
    {
        $resource = \Wsdd\PhpAdmin\Support\TypeGuard::check($resource, 'string');
        if ($resource === 'operations_create') {
            $security->requireValidCsrf();
            $this->handleOperationCreate($operations, $security, $audit);
            return;
        }
        switch ($resource) {
            case 'runtime':
                $payload = $runtime;
                break;
            case 'permissions':
                $payload = $permissions;
                break;
            case 'profile':
                $payload = $profile;
                break;
            case 'reconcile':
                $payload = $reconciliation;
                break;
            case 'operations':
                $payload = $operations->listing();
                break;
            case 'catalog':
                $payload = (new ExtensionCatalog())->status(is_array(isset($runtime['extensions']['loaded']) ? $runtime['extensions']['loaded'] : null) ? array_values($runtime['extensions']['loaded']) : []);
                break;
            case 'compatibility':
                $payload = (new ExtensionCatalog())->compatibilityMatrix();
                break;
            case 'state':
                $payload = ['operation_options' => $this->extensionOptions($runtime), 'runtime' => $runtime, 'permissions' => $permissions, 'profile' => $profile, 'reconciliation' => $reconciliation, 'operations' => $operations->listing(), 'catalog' => (new ExtensionCatalog())->status(is_array(isset($runtime['extensions']['loaded']) ? $runtime['extensions']['loaded'] : null) ? array_values($runtime['extensions']['loaded']) : []), 'compatibility' => (new ExtensionCatalog())->compatibilityMatrix()];
                break;
            default:
                $payload = ['error' => 'Unknown API resource.', 'available' => ['runtime', 'permissions', 'profile', 'reconcile', 'operations', 'catalog', 'compatibility', 'state']];
        }
        http_response_code(in_array($resource, ['runtime', 'permissions', 'profile', 'reconcile', 'operations', 'catalog', 'compatibility', 'state'], true) ? 200 : 404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
    /**
     * @return void
     */
    private function handleOperationCreate(OperationRepository $operations, HttpSecurity $security, AuditLogger $audit)
    {
        $type = isset($_POST['type']) ? $_POST['type'] : null;
        $extension = isset($_POST['extension']) ? $_POST['extension'] : null;
        try {
            $operation = (new OperationService($operations))->create(is_string($type) ? $type : '', (is_string($extension) && trim($extension) !== '') ? trim($extension) : null, $security->actor());
        } catch (\Exception $exception) {
            $audit->record($security->actor(), 'operation_create', is_string($extension) ? $extension : 'none', 'failed', ['type' => is_string($type) ? $type : null, 'message' => $exception->getMessage()]);
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => $exception->getMessage()], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            return;
        } catch (\Throwable $exception) {
            $audit->record($security->actor(), 'operation_create', is_string($extension) ? $extension : 'none', 'failed', ['type' => is_string($type) ? $type : null, 'message' => $exception->getMessage()]);
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => $exception->getMessage()], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            return;
        }
        $audit->record($security->actor(), 'operation_create', isset($operation->extension) ? $operation->extension : 'runtime', 'success', ['operation_id' => $operation->id, 'type' => $operation->type]);
        if (strpos(isset($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : '', 'text/html') !== false) {
            header('Location: ?view=operations', true, 303);
            return;
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($operation->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
