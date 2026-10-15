param(
    [string]$Ref = 'HEAD'
)

$ErrorActionPreference = 'Stop'
$root = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$commit = git -C $root rev-parse --verify "$Ref^{commit}"
if ($LASTEXITCODE -ne 0) { throw 'Release ref must resolve to an existing commit.' }
$version = git -C $root show "${commit}:VERSION"
if ($LASTEXITCODE -ne 0 -or $version -notmatch '^\d+\.\d+\.\d+$') { throw 'The committed VERSION must be a stable semantic version.' }
$status = git -C $root status --porcelain --untracked-files=all
if ($LASTEXITCODE -ne 0 -or $status) { throw 'Commit or preserve working changes before building a release.' }

$directory = Join-Path $root 'dist'
$name = "WSDD-PHP-Admin-$version"
$archive = Join-Path $directory "$name.zip"
if (Test-Path -LiteralPath $archive) { throw "Artifact already exists; it will not be overwritten: $archive" }
[System.IO.Directory]::CreateDirectory($directory) | Out-Null

# Export only a committed allowlist; never collect runtime data from the checkout.
$paths = @('VERSION', 'LICENSE', 'README.md', 'CHANGELOG.md', 'SECURITY.md', 'composer.json',
    'bin/wsdd-php-admin', 'config', 'public', 'src', 'docs/INSTALL.md', 'deploy/apache')
git -C $root archive --format=zip "--prefix=$name/" "--output=$archive" $commit -- @paths
if ($LASTEXITCODE -ne 0) { throw 'Git archive failed; do not publish its output.' }

Add-Type -AssemblyName System.IO.Compression.FileSystem
$zip = [System.IO.Compression.ZipFile]::OpenRead($archive)
try {
    $files = @($zip.Entries | Where-Object { $_.Name -ne '' } | ForEach-Object { $_.FullName })
    foreach ($required in 'VERSION','LICENSE','README.md','composer.json','bin/wsdd-php-admin',
        'public/index.php','public/runtime-health.php','public/runtime-probe.php',
        'src/Profile/ProfileMetadataRecorder.php','config/php-compatibility.json','docs/INSTALL.md') {
        if ("$name/$required" -notin $files) { throw "Required release file missing: $required" }
    }
    foreach ($file in $files) {
        $relative = $file.Substring($name.Length + 1)
        if ($relative -match '(^|/)(profile\.json|auth\.json|probe\.key|agent-status\.json|\.env[^/]*|\.gitkeep)$' -or
            $relative -match '(^|/)(tmp|var|state|logs|backups|test|vendor|node_modules|\.git|\.github)/' -or
            $relative -match '^bin/(validate-|build-release)' -or $relative -match '^deploy/container/' -or
            $relative -match '\.(key|pem|pfx|p12|log)$') {
            throw "Forbidden release content: $relative"
        }
    }
} finally {
    $zip.Dispose()
}

$hash = (Get-FileHash -LiteralPath $archive -Algorithm SHA256).Hash.ToLowerInvariant()
$checksumPath = Join-Path $directory "$name.sha256"
[System.IO.File]::WriteAllText($checksumPath, "$hash  $name.zip`n", [System.Text.Encoding]::ASCII)
$manifest = [ordered]@{version=$version; commit=$commit; archive="$name.zip"; sha256=$hash; files=$files.Count}
$manifestPath = Join-Path $directory "$name.manifest.json"
[System.IO.File]::WriteAllText($manifestPath, ($manifest | ConvertTo-Json) + "`n", [System.Text.Encoding]::ASCII)
$manifest | ConvertTo-Json
