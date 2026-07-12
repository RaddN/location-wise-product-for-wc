param(
    [Parameter(Mandatory = $true)]
    [ValidateSet('edd', 'envato')]
    [string] $Channel,

    [string] $OutputDir = ''
)

$ErrorActionPreference = 'Stop'

$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
if ([string]::IsNullOrWhiteSpace($OutputDir)) {
    $OutputDir = Join-Path $PSScriptRoot 'dist'
}

$outputRoot = if ([System.IO.Path]::IsPathRooted($OutputDir)) {
    $OutputDir
} else {
    Join-Path $PSScriptRoot $OutputDir
}

if (!(Test-Path -LiteralPath $outputRoot)) {
    New-Item -ItemType Directory -Path $outputRoot | Out-Null
}

$outputRoot = (Resolve-Path $outputRoot).Path
$pluginSlug = 'multi-location-product-and-inventory-management-pro'
$stageRoot = Join-Path $outputRoot "staging-$Channel"
$stagePlugin = Join-Path $stageRoot $pluginSlug
$zipPath = Join-Path $outputRoot "$pluginSlug-$Channel.zip"

function Assert-ChildPath {
    param(
        [Parameter(Mandatory = $true)]
        [string] $Path,
        [Parameter(Mandatory = $true)]
        [string] $Parent
    )

    $fullPath = [System.IO.Path]::GetFullPath($Path)
    $fullParent = [System.IO.Path]::GetFullPath($Parent).TrimEnd('\') + '\'

    if (!$fullPath.StartsWith($fullParent, [System.StringComparison]::OrdinalIgnoreCase)) {
        throw "Refusing to operate outside expected directory. Path: $fullPath Parent: $fullParent"
    }
}

function Remove-TaggedBlock {
    param(
        [Parameter(Mandatory = $true)]
        [string] $FilePath,
        [Parameter(Mandatory = $true)]
        [string] $Tag
    )

    $content = Get-Content -LiteralPath $FilePath -Raw
    $pattern = "(?s)\r?\n?[ \t]*// <$Tag>.*?[ \t]*// </$Tag>\r?\n?"
    $content = [regex]::Replace($content, $pattern, [Environment]::NewLine)
    Set-Content -LiteralPath $FilePath -Value $content -NoNewline
}

Assert-ChildPath -Path $stageRoot -Parent $outputRoot
if (Test-Path -LiteralPath $stagePlugin) {
    Assert-ChildPath -Path $stagePlugin -Parent $stageRoot
    Remove-Item -LiteralPath $stagePlugin -Recurse -Force
}

New-Item -ItemType Directory -Path $stagePlugin | Out-Null

$excludeDirs = @(
    '.git',
    '.github',
    '.vscode',
    '.cursor',
    '.playwright-mcp',
    'build',
    'docs'
)

$excludeFiles = @(
    '*.zip',
    '*.bak',
    '*backup*.po~',
    '*backup*.pot~',
    '.gitignore'
)

$robocopyArgs = @($repoRoot, $stagePlugin, '/E', '/NFL', '/NDL', '/NJH', '/NJS', '/NP')
$robocopyArgs += '/XD'
$robocopyArgs += $excludeDirs
$robocopyArgs += '/XF'
$robocopyArgs += $excludeFiles

& robocopy @robocopyArgs | Out-Null
if ($LASTEXITCODE -ge 8) {
    throw "robocopy failed with exit code $LASTEXITCODE"
}

$channelFile = Join-Path $stagePlugin 'includes\build-channel.php'
$channelPhp = @"
<?php
if (!defined('ABSPATH')) {
    exit;
}

define('MULOPIMFWC_RELEASE_CHANNEL', '$Channel');
"@
Set-Content -LiteralPath $channelFile -Value $channelPhp -NoNewline

$mainFile = Join-Path $stagePlugin 'multi-location-product-and-inventory-management-pro.php'

if ($Channel -eq 'envato') {
    Remove-TaggedBlock -FilePath $mainFile -Tag 'mulopimfwc-edd-only'

    $envatoRemovePaths = @(
        'admin\license-page.php',
        'includes\analytics.php',
        'languages'
    )

    foreach ($relativePath in $envatoRemovePaths) {
        $targetPath = Join-Path $stagePlugin $relativePath
        if (Test-Path -LiteralPath $targetPath) {
            Assert-ChildPath -Path $targetPath -Parent $stagePlugin
            Remove-Item -LiteralPath $targetPath -Recurse -Force
        }
    }

    $mainContent = Get-Content -LiteralPath $mainFile -Raw
    $licenseIncludePattern = "    if \(mulopimfwc_is_edd_build\(\)\) \{\r?\n        require_once plugin_dir_path\(__FILE__\) \. 'admin/license-page.php';\r?\n    \} else \{\r?\n        require_once plugin_dir_path\(__FILE__\) \. 'admin/envato-support-page.php';\r?\n    \}"
    $mainContent = [regex]::Replace($mainContent, $licenseIncludePattern, "    require_once plugin_dir_path(__FILE__) . 'admin/envato-support-page.php';")
    Set-Content -LiteralPath $mainFile -Value $mainContent -NoNewline

    $envatoReadme = @"
CodeCanyon / Envato Build
=========================

This package is ready to use after installation.

Updates:
Download the latest plugin ZIP from https://codecanyon.net/downloads and upload it from WordPress Admin > Plugins > Add New > Upload Plugin.

Support:
Use the support channel published for the CodeCanyon item.
"@
    Set-Content -LiteralPath (Join-Path $stagePlugin 'CODECANYON-UPDATES.txt') -Value $envatoReadme -NoNewline
}

if (Test-Path -LiteralPath $zipPath) {
    Assert-ChildPath -Path $zipPath -Parent $outputRoot
    Remove-Item -LiteralPath $zipPath -Force
}

$tar = Get-Command tar.exe -ErrorAction SilentlyContinue
if ($tar) {
    Push-Location -LiteralPath $stageRoot
    try {
        & $tar.Source -a -cf $zipPath $pluginSlug
        if ($LASTEXITCODE -ne 0) {
            throw "tar.exe failed with exit code $LASTEXITCODE"
        }
    } finally {
        Pop-Location
    }
} else {
    Compress-Archive -LiteralPath $stagePlugin -DestinationPath $zipPath -Force
}

Assert-ChildPath -Path $stageRoot -Parent $outputRoot
Remove-Item -LiteralPath $stageRoot -Recurse -Force

Write-Host "Built $Channel release: $zipPath"
