$ErrorActionPreference = 'Stop'

$workspace = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$stagingRoot = Join-Path $workspace ( 'tmp\release-' + [Guid]::NewGuid().ToString( 'N' ) )
$distRoot = Join-Path $workspace 'dist'

$pluginStage = Join-Path $stagingRoot 'woofeeds-pro'
$serverStage = Join-Path $stagingRoot 'woofeeds-license-server'
New-Item -ItemType Directory -Path $pluginStage -Force | Out-Null
New-Item -ItemType Directory -Path $serverStage -Force | Out-Null
New-Item -ItemType Directory -Path $distRoot -Force | Out-Null

Copy-Item -LiteralPath (Join-Path $workspace 'assets') -Destination $pluginStage -Recurse
Copy-Item -LiteralPath (Join-Path $workspace 'includes') -Destination $pluginStage -Recurse
Copy-Item -LiteralPath (Join-Path $workspace 'woofeeds-pro.php') -Destination $pluginStage
Copy-Item -LiteralPath (Join-Path $workspace 'uninstall.php') -Destination $pluginStage
Copy-Item -LiteralPath (Join-Path $workspace 'readme.txt') -Destination $pluginStage

$serverSource = Join-Path $workspace 'license-server'
Copy-Item -LiteralPath (Join-Path $serverSource 'src') -Destination $serverStage -Recurse
Copy-Item -LiteralPath (Join-Path $serverSource 'sql') -Destination $serverStage -Recurse
Copy-Item -LiteralPath (Join-Path $serverSource 'test') -Destination $serverStage -Recurse
Copy-Item -LiteralPath (Join-Path $serverSource 'package.json') -Destination $serverStage
Copy-Item -LiteralPath (Join-Path $serverSource 'package-lock.json') -Destination $serverStage
Copy-Item -LiteralPath (Join-Path $serverSource '.env.example') -Destination $serverStage
Copy-Item -LiteralPath (Join-Path $serverSource 'README.md') -Destination $serverStage

$pluginZip = Join-Path $distRoot 'woofeeds-pro-0.1.1.zip'
$serverZip = Join-Path $distRoot 'woofeeds-license-server-0.1.1.zip'
Compress-Archive -Path $pluginStage -DestinationPath $pluginZip -CompressionLevel Optimal -Force
Compress-Archive -Path $serverStage -DestinationPath $serverZip -CompressionLevel Optimal -Force

Add-Type -AssemblyName System.IO.Compression.FileSystem
foreach ($zipPath in @($pluginZip, $serverZip)) {
    $archive = [System.IO.Compression.ZipFile]::OpenRead($zipPath)
    try {
        Write-Output "$(Split-Path $zipPath -Leaf): $($archive.Entries.Count) files, $((Get-Item $zipPath).Length) bytes"
        $archive.Entries | ForEach-Object { Write-Output "  $($_.FullName)" }
    }
    finally {
        $archive.Dispose()
    }
}
