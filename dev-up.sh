#!/usr/bin/env bash
# One-command local dev bootstrap for Mac/Linux/Git-Bash.
# Mirrors README.md section 3, steps 3-7: creates .env, starts the
# Docker containers, seeds demo data, and opens the login page.
#
# Usage:  ./dev-up.sh
# Re-running is safe - it won't overwrite an existing .env value or
# reseed data you don't ask for.

set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")"

echo "== Verapay dev bootstrap =="

# 1. .env
if [ ! -f .env ]; then
    [ -f .env.example ] || { echo "No .env.example found - run this from the project root." >&2; exit 1; }
    cp .env.example .env
    echo "Created .env from .env.example"
else
    echo ".env already exists - leaving it as-is"
fi

# 2. GATEWAY_ENCRYPTION_KEY - generate one if the line is missing or blank.
# Never overwrites an existing value: rotating it makes every previously
# stored gateway secret undecryptable (see config/config.php).
if grep -qE '^GATEWAY_ENCRYPTION_KEY=.+' .env; then
    echo "GATEWAY_ENCRYPTION_KEY already set - leaving it as-is"
else
    if command -v openssl >/dev/null 2>&1; then
        key=$(openssl rand -base64 32)
    elif command -v php >/dev/null 2>&1; then
        key=$(php -r "echo base64_encode(random_bytes(32));")
    else
        echo "Need openssl or php installed locally to generate GATEWAY_ENCRYPTION_KEY." >&2
        exit 1
    fi

    if grep -qE '^GATEWAY_ENCRYPTION_KEY=' .env; then
        # Portable in-place edit for both GNU and BSD/macOS sed.
        sed -i.bak "s|^GATEWAY_ENCRYPTION_KEY=.*|GATEWAY_ENCRYPTION_KEY=${key}|" .env
        rm -f .env.bak
    else
        printf '\nGATEWAY_ENCRYPTION_KEY=%s\n' "$key" >> .env
    fi
    echo "Generated GATEWAY_ENCRYPTION_KEY in .env"
fi

# 3. Make sure the Docker daemon itself is actually running - "docker compose"
# fails immediately (not a timeout) if only the CLI is installed but Docker
# Desktop hasn't been launched yet.
docker_running() {
    docker info >/dev/null 2>&1
}

if ! docker_running; then
    echo
    echo "Docker daemon isn't responding - trying to start it..."

    started=false
    case "$(uname -s)" in
        MINGW*|MSYS*|CYGWIN*)
            # Git Bash / Windows. Docker Desktop can be a system-wide
            # (Program Files) or per-user (%LOCALAPPDATA%) install - check
            # the known locations, then fall back to walking up from
            # wherever the docker CLI itself actually is on PATH (e.g.
            # .../DockerDesktop/resources/bin/docker -> .../DockerDesktop/).
            local_appdata=""
            if command -v cygpath >/dev/null 2>&1 && [ -n "${LOCALAPPDATA:-}" ]; then
                local_appdata=$(cygpath -u "$LOCALAPPDATA" 2>/dev/null || true)
            fi

            win_exe=""
            for candidate in \
                "/c/Program Files/Docker/Docker/Docker Desktop.exe" \
                "${local_appdata:+$local_appdata/Docker/Docker Desktop.exe}" \
                "${local_appdata:+$local_appdata/Programs/DockerDesktop/Docker Desktop.exe}"
            do
                if [ -n "$candidate" ] && [ -f "$candidate" ]; then
                    win_exe="$candidate"
                    break
                fi
            done

            if [ -z "$win_exe" ] && command -v docker >/dev/null 2>&1; then
                dir=$(dirname "$(command -v docker)")
                for _ in 1 2 3 4; do
                    if [ -f "$dir/Docker Desktop.exe" ]; then
                        win_exe="$dir/Docker Desktop.exe"
                        break
                    fi
                    parent=$(dirname "$dir")
                    [ "$parent" = "$dir" ] && break
                    dir="$parent"
                done
            fi

            if [ -n "$win_exe" ]; then
                # cmd.exe needs a native backslash path, not the /c/... form.
                win_native=$(cygpath -w "$win_exe" 2>/dev/null || echo "$win_exe")
                cmd.exe /c start "" "$win_native" >/dev/null 2>&1 &
                started=true
            fi
            ;;
        Darwin)
            if open -a Docker 2>/dev/null; then
                started=true
            fi
            ;;
        Linux)
            if command -v systemctl >/dev/null 2>&1 && sudo -n systemctl start docker 2>/dev/null; then
                started=true
            fi
            ;;
    esac

    if [ "$started" != true ]; then
        echo "Couldn't start Docker automatically. Install/start Docker Desktop (https://www.docker.com/products/docker-desktop/) - or 'sudo systemctl start docker' on Linux - then re-run this script." >&2
        exit 1
    fi

    echo "Waiting for Docker to be ready - this can take a minute or two on first start..."
    ready=false
    for _ in $(seq 1 60); do
        if docker_running; then ready=true; break; fi
        sleep 3
    done

    if [ "$ready" != true ]; then
        echo "Docker still isn't responding after 3 minutes. Open it manually, wait for it to say it's running, then re-run this script." >&2
        exit 1
    fi
    echo "Docker is ready."
fi

# 4. Docker
echo
echo "Starting containers (docker compose up -d --build)..."
if ! docker compose up -d --build; then
    echo "docker compose up failed - is Docker running? See README.md section 3 'If something goes wrong'." >&2
    exit 1
fi

# 5. Wait for the app container to actually be able to run commands
# (db healthcheck + depends_on already gate app's own startup, this
# just guards against a slow first boot on the very first --build).
echo "Waiting for the app container..."
ready=false
for _ in $(seq 1 20); do
    if docker compose exec -T app php -v >/dev/null 2>&1; then
        ready=true
        break
    fi
    sleep 3
done
if [ "$ready" != true ]; then
    echo "App container never became ready. Run 'docker compose logs -f app' to see why." >&2
    exit 1
fi

# 6. Apply any pending database migrations. Safe on every run: a fresh
# volume's schema.sql already has everything, so this is a no-op; a stale
# volume from an older checkout (the case that used to fail with "Table
# ... doesn't exist") gets patched up automatically.
echo "Checking database schema..."
docker compose exec -T app php database/migrate.php

# 7. Seed demo data
echo "Seeding demo data..."
docker compose exec -T app php database/seed.php

# 8. Open the app
url="http://localhost:8080/login"
echo
echo "Verapay is up: $url"
echo "Demo login: priya@verapay.test / Demo!2024pass (admin: admin@verapay.test)"

if command -v open >/dev/null 2>&1; then
    open "$url"
elif command -v xdg-open >/dev/null 2>&1; then
    xdg-open "$url"
fi
