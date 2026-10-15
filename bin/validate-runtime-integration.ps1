param(
    [Parameter(Mandatory)][string]$DevelopmentPassword,
    [Parameter(Mandatory)][ValidatePattern('^\d+\.\d+$')][string]$PhpVersion,
    [ValidateSet('calendar','bcmath','pdo_pgsql')][string]$Extension = 'calendar',
    [ValidateSet('Cycle','PrepareRebuild')][string]$Mode = 'Cycle'
)

$ErrorActionPreference = 'Stop'
$policy = Get-Content -Raw -LiteralPath (Join-Path $PSScriptRoot '../config/php-compatibility.json') | ConvertFrom-Json
if ($PhpVersion -notin $policy.targets.PSObject.Properties.Name) { throw 'Unknown PHP target.' }
$versionId = $PhpVersion.Replace('.', '')
$base = $policy.admin_url_template.Replace('{minor_id}', $versionId)
$container = $policy.container_template.Replace('{minor}', $PhpVersion)
$null = Invoke-WebRequest -Uri $base -SessionVariable session -TimeoutSec 15
$failures = [System.Collections.Generic.List[string]]::new()

function Request84($Path, $Body = $null) {
    $arguments = @{ Uri = ($base + $Path); WebSession = $session; TimeoutSec = 15; SkipHttpErrorCheck = $true }
    if ($null -ne $Body) { $arguments.Method = 'POST'; $arguments.Body = $Body }
    Invoke-WebRequest @arguments
}

function Api84($Name) {
    $response = Request84 "?api=$Name"
    if ($response.StatusCode -ne 200) { throw "API $Name returned $($response.StatusCode)" }
    $response.Content | ConvertFrom-Json
}

function Operation84($Type, [switch]$AllowSkipped) {
    $page = Request84 '?view=operations'
    $token = [regex]::Match($page.Content, 'name="csrf_token" value="([^"]+)"').Groups[1].Value
    if (!$token) { throw 'No CSRF token in authenticated operations page.' }
    $response = Request84 '?api=operations_create' @{type=$Type; extension=$Extension; csrf_token=$token}
    if ($response.StatusCode -ne 200) { throw "Operation submission failed: $($response.StatusCode)" }
    $operation = $response.Content | ConvertFrom-Json
    $deadline = (Get-Date).AddSeconds(90)
    do {
        Start-Sleep -Seconds 2
        $item = (Api84 'operations').items | Where-Object { $_.operation.id -eq $operation.id }
        if ($item.result -and $item.result.status -ne 'processing') {
            $state = Api84 'state'
            $fresh = docker exec $container php -r "echo extension_loaded('$Extension') ? 'loaded' : 'unloaded';"
            [pscustomobject]@{
                type=$Type; id=$operation.id; status=$item.result.status; message=$item.result.message
                extension=$Extension; fresh_cli=$fresh; internal_web_loaded=($state.runtime.extensions.loaded -contains $Extension)
                desired=$state.profile.profile.extensions.$Extension.desired_state
                web_issues=$state.reconciliation.issue_count; reload=$item.result.details.reload.status
                verification=$item.result.details.verification.status; source=$state.runtime.observations.source
                apache_loaded=$item.result.details.verification.observed.apache.extensions.$Extension
            } | ConvertTo-Json -Compress | Write-Output
            if ($AllowSkipped -and $item.result.status -eq 'skipped') { return }
            if ($item.result.status -ne 'success') { throw "$Type returned $($item.result.status): $($item.result.message)" }
            if ($item.result.details.verification.status -ne 'success') { throw "$Type did not verify both runtimes" }
            if ($state.runtime.observations.source -ne 'apache-probe' -or $state.reconciliation.issue_count -ne 0) {
                $failures.Add("$Type did not produce a verified Apache snapshot with zero drift")
            }
            if (($state.runtime.extensions.loaded -contains $Extension) -ne ($fresh -eq 'loaded')) {
                $failures.Add("${Type}: internal web extension state differs from fresh CLI")
            }
            if ($failures.Count) { throw ($failures -join '; ') }
            return
        }
    } while ((Get-Date) -lt $deadline)
    throw "Operation timed out: $($operation.id)"
}

$anonymous = Request84 '?api=state'
if ($anonymous.StatusCode -ne 401) { throw 'Anonymous API access was not rejected.' }
Write-Output 'PASS anonymous API returns 401'
$login = Request84 '?api=login' @{username='admin'; password=$DevelopmentPassword}
if ($login.Content -match 'Invalid username or password') {
    $login = Request84 '?api=login' @{username='admin'; password='admin'}
}
if ($login.Content -match '<title>Change Password</title>') {
    $token = [regex]::Match($login.Content, 'name="csrf_token" value="([^"]+)"').Groups[1].Value
    $login = Request84 '?api=change_password' @{username='admin'; password=$DevelopmentPassword; password_confirm=$DevelopmentPassword; csrf_token=$token}
    Write-Output 'PASS first-login password change completed with supplied development password'
}
if ($login.Content -notmatch 'class="app-shell"') { throw 'Authenticated application was not reached.' }
if ($Mode -eq 'PrepareRebuild') {
    $before = Api84 'state'
    if (!$before.runtime.php.version.StartsWith("$PhpVersion.")) { throw 'Unexpected target runtime.' }
    if (@($before.operations.items | Where-Object { !$_.result -or $_.result.status -eq 'processing' }).Count) {
        throw 'Cannot prepare reconstruction while operations are pending.'
    }
    $desired = $before.profile.profile.extensions.$Extension.desired_state
    if ($desired -eq 'disabled' -or (($before.runtime.extensions.loaded -contains $Extension) -and $desired -ne 'enabled')) {
        throw 'Preparation will not override a disabled or unmanaged loaded extension.'
    }
    if ($desired -ne 'enabled' -or $before.runtime.extensions.loaded -notcontains $Extension) {
        if (!$before.catalog.$Extension.installable) { throw 'No executable recipe for the reconstruction test.' }
        $action = if ($before.catalog.$Extension.available) { 'enable_extension' } else { 'install_extension' }
        Operation84 $action
    }
    if ($failures.Count) { throw ($failures -join '; ') }
    $ready = Api84 'state'
    $root = $policy.dev_root_overrides.$PhpVersion
    if (!$root) { $root = $policy.dev_root_template.Replace('{minor_id}', $versionId) }
    $code = 'require ' + "'$root/src/Support/bootstrap.php'; " +
        '$c=\Wsdd\PhpAdmin\Support\AppConfig::loadDefault(); echo json_encode(["loaded"=>extension_loaded("' + $Extension +
        '"),"managed_ini"=>is_file($c->managedIniDir()."/".$c->managedIniPrefix()."' + $Extension + '.ini")]);'
    $cli = (docker exec $container php -r $code) | ConvertFrom-Json
    if ($LASTEXITCODE -ne 0 -or !$cli.loaded -or !$cli.managed_ini -or
        $ready.profile.profile.extensions.$Extension.desired_state -ne 'enabled' -or
        $ready.runtime.extensions.loaded -notcontains $Extension -or
        $ready.runtime.observations.apache.extensions -notcontains $Extension -or
        $ready.runtime.observations.source -ne 'apache-probe' -or
        @($ready.runtime.observations.errors).Count -ne 0 -or $ready.reconciliation.issue_count -ne 0) {
        throw 'Reconstruction is NOT ready: enabled profile, managed INI, CLI and Apache must agree.'
    }
    $inspection = (docker inspect $container | ConvertFrom-Json)[0]
    $mount = @($inspection.Mounts | Where-Object { $_.Destination -eq '/var/lib/wsdd-php-admin' -and $_.Type -eq 'bind' -and $_.RW })
    if ($mount.Count -ne 1) { throw 'Expected one writable Windows state bind mount.' }
    $hostRoot = $mount[0].Source
    if ($hostRoot -match '^/run/desktop/mnt/host/([a-z])/(.*)$') {
        $hostRoot = $Matches[1].ToUpperInvariant() + ':/' + $Matches[2]
    }
    $hostProfile = Join-Path $hostRoot 'profile.json'
    $hostHash = (Get-FileHash -LiteralPath $hostProfile -Algorithm SHA256).Hash.ToLowerInvariant()
    $containerHash = ((docker exec $container sha256sum /var/lib/wsdd-php-admin/profile.json) -split '\s+')[0]
    if ($LASTEXITCODE -ne 0 -or $hostHash -ne $containerHash) { throw 'Persistent profile differs from Windows.' }
    [pscustomobject]@{PHP=$PhpVersion;RebuildReadyExtension=$Extension;Desired='enabled';CLI=$cli.loaded;Apache=$true;ManagedINI=$cli.managed_ini;HostProfileMatches=$true;Status='ready-for-user-rebuild'} | ConvertTo-Json -Compress
    return
}
foreach ($view in 'overview','runtime','extensions','profile','operations','permissions','reconciliation') {
    $page = Request84 "?view=$view"
    if ($page.StatusCode -ne 200 -or $page.Content -notmatch 'class="app-shell"') { throw "View failed: $view" }
    Write-Output "PASS authenticated view $view"
}
foreach ($api in 'runtime','permissions','profile','reconcile','operations','catalog','compatibility','state') {
    $null = Api84 $api
    Write-Output "PASS API $api"
}
$csrf = Request84 '?api=operations_create' @{type='reconcile'}
if ($csrf.StatusCode -ne 419) { throw 'Missing CSRF was not rejected.' }
Write-Output 'PASS missing CSRF returns 419'
$before = Api84 'state'
if (!$before.runtime.php.version.StartsWith("$PhpVersion.") -or $before.runtime.extensions.loaded -contains $Extension -or ($before.profile.profile.extensions.$Extension -and $before.profile.profile.extensions.$Extension.desired_state -ne 'removed')) {
    throw 'Unexpected baseline: test extension must be unloaded and untracked or removed in the selected runtime.'
}
$initialAction = if ($before.catalog.$Extension.available) { 'enable_extension' } else { 'install_extension' }
if (!$before.catalog.$Extension.installable) { throw 'Test extension has no executable recipe in the selected environment.' }
Write-Output "Test extension $Extension starts with $initialAction"
try {
    foreach ($type in $initialAction,'disable_extension','rollback_extension','disable_extension','enable_extension','reconcile') {
        Operation84 $type
    }
} finally {
    Operation84 'remove_extension' -AllowSkipped
}
Write-Output "Integration cycle complete; $Extension binary retained, managed INI removed, desired state removed."
if ($failures.Count -gt 0) {
    $failures | ForEach-Object { Write-Output "FAIL $_" }
    exit 1
}
