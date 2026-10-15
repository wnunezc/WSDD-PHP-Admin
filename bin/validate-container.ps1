param(
    [Parameter(Mandatory)][ValidatePattern('^\d+\.\d+$')][string]$PhpVersion,
    [Parameter(Mandatory)][string]$DevelopmentPassword,
    [ValidateSet('Smoke','Full')][string]$Mode = 'Smoke'
)

$ErrorActionPreference = 'Stop'
$policy = Get-Content -Raw -LiteralPath (Join-Path $PSScriptRoot '../config/php-compatibility.json') | ConvertFrom-Json
if ($PhpVersion -notin $policy.targets.PSObject.Properties.Name) { throw 'Unknown target in compatibility policy.' }
$id = $PhpVersion.Replace('.', '')
$container = $policy.container_template.Replace('{minor}', $PhpVersion)
$root = $policy.dev_root_overrides.$PhpVersion
if (!$root) { $root = $policy.dev_root_template.Replace('{minor_id}', $id) }

$inspection = docker inspect $container | ConvertFrom-Json
if ($LASTEXITCODE -ne 0 -or !$inspection[0].State.Running) { throw 'Target container is not running.' }
$stateMount = @($inspection[0].Mounts | Where-Object {$_.Destination -eq '/var/lib/wsdd-php-admin' -and $_.RW})
$logMount = @($inspection[0].Mounts | Where-Object {$_.Destination -eq '/var/log/wsdd-php-admin' -and $_.RW})
if ($stateMount.Count -ne 1 -or $logMount.Count -ne 1 -or $stateMount[0].Source -eq $logMount[0].Source) {
    throw 'Independent writable state/log mounts are required.'
}
function RuntimeProcesses {
    $lines = docker exec $container ps -C php -o user=,pid=,args=
    if ($LASTEXITCODE -ne 0) { throw 'Unable to inspect PHP processes.' }
    $processes = @($lines | ForEach-Object {
        $parts = $_.Trim() -split '\s+', 3
        [pscustomobject]@{User=$parts[0];ProcessId=$parts[1];Command=$parts[2]}
    })
    $agents = @($processes | Where-Object {$_.Command.EndsWith("$root/bin/wsdd-php-admin agent")})
    $web = @($processes | Where-Object {$_.Command.EndsWith("-S 127.0.0.1:8989 -t $root/public")})
    if ($agents.Count -ne 1 -or $web.Count -ne 1 -or $agents[0].User -ne 'root' -or $web[0].User -ne 'www-data') {
        throw 'Expected one root agent and one www-data web process using the selected root.'
    }
    [pscustomobject]@{Agent=$agents[0].ProcessId; Web=$web[0].ProcessId}
}
$beforeProcesses = RuntimeProcesses

function InvokeCli([string[]]$Arguments) {
    $output = docker exec $container php @Arguments
    if ($LASTEXITCODE -ne 0) { throw "PHP check failed for $PhpVersion : $($Arguments -join ' ')" }
    $output
}

$detected = (InvokeCli @("$root/bin/wsdd-php-admin", 'detect')) | ConvertFrom-Json
if (!$detected.php.version.StartsWith("$PhpVersion.")) { throw 'Detected runtime differs from requested target.' }
foreach ($command in 'permissions','profile:status','reconcile') { InvokeCli @("$root/bin/wsdd-php-admin", $command) }
foreach ($check in 'php-compatibility','profile-bootstrap','agent-safety','agent-response','security-hardening','runtime-observation','extension-catalog','reconciliation-failures','runtime-startup') {
    InvokeCli @("$root/bin/validate-$check")
}

$completed = [System.Collections.Generic.List[string]]::new()
$preserved = [System.Collections.Generic.List[string]]::new()
if ($Mode -eq 'Full') {
    $baseline = (InvokeCli @("$root/bin/wsdd-php-admin", 'profile:status')) | ConvertFrom-Json
    foreach ($extension in 'calendar','bcmath','pdo_pgsql') {
        $state = $baseline.profile.extensions.$extension.desired_state
        if (($state -and $state -ne 'removed') -or $detected.extensions.loaded -contains $extension) {
            $preserved.Add($extension)
            Write-Output "Preserving pre-existing extension state: $extension"
            continue
        }
        & (Join-Path $PSScriptRoot 'validate-runtime-integration.ps1') -PhpVersion $PhpVersion -DevelopmentPassword $DevelopmentPassword -Extension $extension
        if ($LASTEXITCODE -ne 0) { throw "Extension integration failed: $extension" }
        $completed.Add($extension)
    }
    InvokeCli @("$root/bin/validate-managed-reconciliation")
}

$engine = 'D:/OpsZone/DevWorkspace/Engines/Playwright'
$previousPassword = $env:WSDD_TEST_PASSWORD
$previousVersions = $env:WSDD_TEST_PHP_VERSIONS
$previousCA = $env:NODE_EXTRA_CA_CERTS
try {
    $env:WSDD_TEST_PASSWORD = $DevelopmentPassword
    $env:WSDD_TEST_PHP_VERSIONS = $PhpVersion
    if (!$env:NODE_EXTRA_CA_CERTS) { $env:NODE_EXTRA_CA_CERTS = 'C:/WSDD-Environment/Docker-Structure/ssl/wsdd-mkcert-rootCA.crt' }
    $grep = if ($Mode -eq 'Full' -and $completed.Contains('calendar')) { 'catalog selects|post-rebuild|live overview' } else { 'catalog selects|post-rebuild' }
    node (Join-Path $engine 'scripts/run-project-tests.js') (Split-Path $PSScriptRoot) --suite app --grep $grep
    if ($LASTEXITCODE -ne 0) { throw 'Browser verification failed.' }
} finally {
    $env:WSDD_TEST_PASSWORD = $previousPassword
    $env:WSDD_TEST_PHP_VERSIONS = $previousVersions
    $env:NODE_EXTRA_CA_CERTS = $previousCA
}
InvokeCli @("$root/bin/wsdd-php-admin", 'reconcile')
$rebuildExtension = $null
if ($Mode -eq 'Full') {
    $finalProfile = (InvokeCli @("$root/bin/wsdd-php-admin", 'profile:status')) | ConvertFrom-Json
    $alreadyEnabled = @('bcmath','calendar','pdo_pgsql' | Where-Object { $finalProfile.profile.extensions.$_.desired_state -eq 'enabled' })
    $rebuildExtension = if ($alreadyEnabled.Count) { $alreadyEnabled[0] } elseif ($completed.Contains('bcmath')) { 'bcmath' } elseif ($completed.Count) { $completed[0] } else { $null }
    if (!$rebuildExtension) { throw 'No safe reconstruction candidate: existing extension states were preserved.' }
    & (Join-Path $PSScriptRoot 'validate-runtime-integration.ps1') -PhpVersion $PhpVersion -DevelopmentPassword $DevelopmentPassword -Extension $rebuildExtension -Mode PrepareRebuild
    if ($LASTEXITCODE -ne 0) { throw 'Reconstruction preparation failed.' }
}
$afterProcesses = RuntimeProcesses
if ($afterProcesses.Web -ne $beforeProcesses.Web) { throw 'The internal web restarted during validation.' }
[pscustomobject]@{PHP=$detected.php.version; Mode=$Mode; CompletedCycles=@($completed.ToArray()); Preserved=@($preserved.ToArray()); RebuildReadyExtension=$rebuildExtension; Status='passed'} | ConvertTo-Json -Compress
