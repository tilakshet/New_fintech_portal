# One-command local dev bootstrap for Windows (PowerShell).
# Mirrors README.md section 3, steps 3-7: creates .env, starts the
# Docker containers, seeds demo data, and opens the login page.
#
# Usage:  .\dev-up.ps1
# Re-running is safe - it won't overwrite an existing .env value or
# reseed data you don't ask for.

$ErrorActionPreference = "Continue"

function Fail($msg) {
    Write-Host $msg -ForegroundColor Red
    exit 1
}

Write-Host "== Verapay dev bootstrap ==" -ForegroundColor Cyan

# 1. .env
if (-not (Test-Path ".env")) {
    if (-not (Test-Path ".env.example")) {
        Fail "No .env.example found - run this from the project root."
    }
    Copy-Item ".env.example" ".env"
    Write-Host "Created .env from .env.example"
} else {
    Write-Host ".env already exists - leaving it as-is"
}

# 2. GATEWAY_ENCRYPTION_KEY - generate one if the line is missing or blank.
# Never overwrites an existing value: rotating it makes every previously
# stored gateway secret undecryptable (see config/config.php).
$envText = Get-Content ".env" -Raw
if ($envText -match "(?m)^GATEWAY_ENCRYPTION_KEY=\s*$" -or $envText -notmatch "(?m)^GATEWAY_ENCRYPTION_KEY=") {
    $rng = [System.Security.Cryptography.RNGCryptoServiceProvider]::new()
    $bytes = New-Object byte[] 32
    $rng.GetBytes($bytes)
    $key = [Convert]::ToBase64String($bytes)

    if ($envText -match "(?m)^GATEWAY_ENCRYPTION_KEY=\s*$") {
        $envText = $envText -replace "(?m)^GATEWAY_ENCRYPTION_KEY=\s*$", "GATEWAY_ENCRYPTION_KEY=$key"
    } else {
        $envText = $envText.TrimEnd() + "`r`nGATEWAY_ENCRYPTION_KEY=$key`r`n"
    }
    Set-Content ".env" $envText -NoNewline
    Write-Host "Generated GATEWAY_ENCRYPTION_KEY in .env"
} else {
    Write-Host "GATEWAY_ENCRYPTION_KEY already set - leaving it as-is"
}

# 3. Make sure the Docker daemon itself is actually running - "docker compose"
# fails immediately (not a timeout) if only the CLI is installed but Docker
# Desktop hasn't been launched yet.
function Test-DockerRunning {
    docker info *> $null
    return ($LASTEXITCODE -eq 0)
}

if (-not (Test-DockerRunning)) {
    Write-Host "`nDocker daemon isn't responding - looking for Docker Desktop..." -ForegroundColor Yellow

    $dockerExe = @(
        "$env:ProgramFiles\Docker\Docker\Docker Desktop.exe",
        "$env:LOCALAPPDATA\Docker\Docker Desktop.exe",
        "$env:LOCALAPPDATA\Programs\DockerDesktop\Docker Desktop.exe"
    ) | Where-Object { Test-Path $_ } | Select-Object -First 1

    # None of the known default install locations hit - Docker Desktop can
    # also be a per-user install elsewhere. Fall back to walking up from
    # wherever the docker CLI itself actually is (e.g.
    # .../DockerDesktop/resources/bin/docker.exe -> .../DockerDesktop/).
    if (-not $dockerExe) {
        $dockerCmd = Get-Command docker -ErrorAction SilentlyContinue
        if ($dockerCmd) {
            $dir = Split-Path $dockerCmd.Source
            for ($i = 0; $i -lt 4 -and -not $dockerExe; $i++) {
                $candidate = Join-Path $dir "Docker Desktop.exe"
                if (Test-Path $candidate) { $dockerExe = $candidate }
                $parent = Split-Path $dir -ErrorAction SilentlyContinue
                if (-not $parent -or $parent -eq $dir) { break }
                $dir = $parent
            }
        }
    }

    if (-not $dockerExe) {
        Fail "Docker Desktop isn't running and I couldn't find its executable to start it automatically. Start it yourself (or install it from https://www.docker.com/products/docker-desktop/), then re-run this script."
    }

    Write-Host "Launching '$dockerExe' - this can take a minute or two on first start..." -ForegroundColor Yellow
    Start-Process $dockerExe

    $dockerReady = $false
    for ($i = 0; $i -lt 60; $i++) {
        if (Test-DockerRunning) { $dockerReady = $true; break }
        Start-Sleep -Seconds 3
    }

    if (-not $dockerReady) {
        Fail "Docker Desktop still isn't responding after 3 minutes. Open it manually, wait for the whale icon to say it's running, then re-run this script."
    }

    Write-Host "Docker is ready." -ForegroundColor Green
}

# 4. Docker
Write-Host "`nStarting containers (docker compose up -d --build)..." -ForegroundColor Cyan
docker compose up -d --build
if ($LASTEXITCODE -ne 0) {
    Fail "docker compose up failed - is Docker Desktop running? See README.md section 3 'If something goes wrong'."
}

# 5. Wait for the app container to actually be able to run commands
# (db healthcheck + depends_on already gate app's own startup, this
# just guards against a slow first boot on the very first `--build`).
Write-Host "Waiting for the app container..." -ForegroundColor Cyan
$ready = $false
for ($i = 0; $i -lt 20; $i++) {
    docker compose exec -T app php -v *> $null
    if ($LASTEXITCODE -eq 0) { $ready = $true; break }
    Start-Sleep -Seconds 3
}
if (-not $ready) {
    Fail "App container never became ready. Run 'docker compose logs -f app' to see why."
}

# 6. Apply any pending database migrations. Safe on every run: a fresh
# volume's schema.sql already has everything, so this is a no-op; a stale
# volume from an older checkout (the case that used to fail with "Table
# ... doesn't exist") gets patched up automatically.
Write-Host "Checking database schema..." -ForegroundColor Cyan
docker compose exec -T app php database/migrate.php
if ($LASTEXITCODE -ne 0) {
    Fail "Migration failed - check the output above."
}

# 7. Seed demo data
Write-Host "Seeding demo data..." -ForegroundColor Cyan
docker compose exec -T app php database/seed.php
if ($LASTEXITCODE -ne 0) {
    Fail "Seeding failed - check the output above."
}

# 8. Open the app
$url = "http://localhost:8080/login"
Write-Host "`nVerapay is up: $url" -ForegroundColor Green
Write-Host "Demo login: priya@verapay.test / Demo!2024pass (admin: admin@verapay.test)"
Start-Process $url
