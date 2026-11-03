# Local development with a public URL for Payzone's callback.
#
# Starts a Cloudflare quick tunnel to the local server, writes its URL into
# .env as PAYZONE_CALLBACK_BASE_URL, then runs `composer dev` (server, queue,
# Vite). Ctrl+C stops everything, tunnel included. Run it with `make dev`.
#
# A quick tunnel gets a new random *.trycloudflare.com address every start,
# which is why the URL is rewritten each time rather than set once.
#
# -TunnelOnly (`make tunnel`) skips `composer dev`, for when it is already
# running: `php artisan serve` reads .env on every request, so the new URL
# applies at once.

param(
    [int] $Port = 8000,
    [int] $TimeoutSeconds = 60,
    [switch] $TunnelOnly
)

$ErrorActionPreference = 'Stop'

$root = Split-Path -Parent $PSScriptRoot
$envFile = Join-Path $root '.env'
$log = Join-Path ([IO.Path]::GetTempPath()) 'hirfaty-cloudflared.log'

if (-not (Get-Command cloudflared -ErrorAction SilentlyContinue)) {
    throw 'cloudflared is not installed or not on PATH.'
}

if (-not (Test-Path $envFile)) {
    throw ".env not found at $envFile"
}

Remove-Item $log -ErrorAction SilentlyContinue

# cloudflared prints its URL on stderr; both streams go to files we can read.
Write-Host "Starting Cloudflare tunnel to http://localhost:$Port ..." -ForegroundColor Cyan
$tunnel = Start-Process -FilePath 'cloudflared' `
    -ArgumentList 'tunnel', '--no-autoupdate', '--url', "http://localhost:$Port" `
    -RedirectStandardError $log `
    -RedirectStandardOutput "$log.out" `
    -WindowStyle Hidden `
    -PassThru

try {
    $url = $null
    $deadline = (Get-Date).AddSeconds($TimeoutSeconds)

    while (-not $url -and (Get-Date) -lt $deadline) {
        Start-Sleep -Milliseconds 500

        if ($tunnel.HasExited) {
            throw "cloudflared exited early (code $($tunnel.ExitCode)). See $log"
        }

        if (Test-Path $log) {
            $match = Select-String -Path $log -Pattern 'https://[a-z0-9-]+\.trycloudflare\.com' -AllMatches |
                Select-Object -First 1

            if ($match) {
                $url = $match.Matches[0].Value
            }
        }
    }

    if (-not $url) {
        throw "No tunnel URL after $TimeoutSeconds s. See $log"
    }

    # Rewrite (or add) the one line, keeping the file UTF-8 without a BOM.
    $content = [IO.File]::ReadAllText($envFile)
    $line = "PAYZONE_CALLBACK_BASE_URL=`"$url`""

    if ($content -match '(?m)^PAYZONE_CALLBACK_BASE_URL=.*$') {
        $content = [regex]::Replace($content, '(?m)^PAYZONE_CALLBACK_BASE_URL=.*$', $line)
    } else {
        $content = $content.TrimEnd("`r", "`n") + [Environment]::NewLine + $line + [Environment]::NewLine
    }

    [IO.File]::WriteAllText($envFile, $content, (New-Object Text.UTF8Encoding($false)))

    Write-Host "Tunnel ready: $url" -ForegroundColor Green
    Write-Host "PAYZONE_CALLBACK_BASE_URL updated in .env" -ForegroundColor Green

    Push-Location $root

    try {
        # A cached config would keep the previous tunnel's URL.
        php artisan config:clear | Out-Null

        if ($TunnelOnly) {
            Write-Host 'Tunnel open. Ctrl+C to close it.' -ForegroundColor Cyan
            Wait-Process -Id $tunnel.Id
        } else {
            composer dev
        }
    } finally {
        Pop-Location
    }
} finally {
    if (-not $tunnel.HasExited) {
        Write-Host 'Stopping Cloudflare tunnel ...' -ForegroundColor Cyan
        Stop-Process -Id $tunnel.Id -Force -ErrorAction SilentlyContinue
    }
}
