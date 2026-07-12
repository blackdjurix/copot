param(
    [string]$Source = "C:\Git\copot",
    [string]$Destination = "C:\xampp\htdocs\copot.test",
    [switch]$DryRun
)

# Developers and Codex edit only the Git clone. The XAMPP htdocs directory is
# a one-way runtime mirror; changes made there are never synchronized back.
$ErrorActionPreference = 'Stop'

$sourcePath = [System.IO.Path]::GetFullPath($Source).TrimEnd('\')
$destinationPath = [System.IO.Path]::GetFullPath($Destination).TrimEnd('\')

if (-not (Test-Path -LiteralPath $sourcePath -PathType Container)) {
    [Console]::Error.WriteLine("Sync source does not exist: $sourcePath")
    exit 8
}

if ($sourcePath.Equals($destinationPath, [System.StringComparison]::OrdinalIgnoreCase)) {
    [Console]::Error.WriteLine('Source and destination must be different directories.')
    exit 8
}

if (-not (Test-Path -LiteralPath $destinationPath)) {
    New-Item -ItemType Directory -Path $destinationPath -Force | Out-Null
}

$common = @('/R:2', '/W:1', '/NFL', '/NDL', '/NP')
if ($DryRun) {
    $common += '/L'
}

function Invoke-RobocopyPass {
    param(
        [string]$Label,
        [string]$From,
        [string]$To,
        [string[]]$Arguments
    )

    Write-Host "[$Label] $From -> $To"
    & robocopy $From $To @Arguments @common
    $code = $LASTEXITCODE

    if ($code -ge 8) {
        [Console]::Error.WriteLine("Robocopy pass [$Label] failed with exit code $code.")
        exit $code
    }

    Write-Host "[$Label] robocopy exit code: $code"
}

Write-Host "Source:      $sourcePath"
Write-Host "Destination: $destinationPath"
Write-Host "Mode:        $(if ($DryRun) { 'dry-run' } else { 'sync' })"
Write-Host 'Excluded:    .git directory'
Write-Host 'Preserved:   destination .env, .env.*, auth.json, and storage runtime state'

# Application/source pass. Purge stale mirrored code, but never enter Git
# metadata or storage, and never delete destination-local environment files.
Invoke-RobocopyPass 'application' $sourcePath $destinationPath @(
    '/E', '/PURGE',
    '/XD', (Join-Path $sourcePath '.git'), (Join-Path $sourcePath 'storage'),
    '/XF', '.env', '.env.*', 'auth.json'
)

# Storage pass seeds tracked directory skeletons without purging runtime logs,
# cache, installation markers, keys, or uploaded site assets.
$sourceStorage = Join-Path $sourcePath 'storage'
if (Test-Path -LiteralPath $sourceStorage -PathType Container) {
    Invoke-RobocopyPass 'storage-skeleton' $sourceStorage (Join-Path $destinationPath 'storage') @(
        '/E',
        '/XF', 'installed.lock', '.install.lock', '.installed-*', '*.key'
    )
}

Write-Host "Sync completed successfully ($(if ($DryRun) { 'dry-run' } else { 'actual' }))."
exit 0
