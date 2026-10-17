#!/bin/sh
# Starts the attendance website with Docker (Mac / Linux) and opens it in your browser.
cd "$(dirname "$0")" || exit 1

if ! docker info >/dev/null 2>&1; then
    echo "Docker Desktop is not running. Open it, wait until it says \"Engine running\", then run this again."
    exit 1
fi

echo "Starting the attendance website (the first time takes a few minutes)..."
docker compose up -d --build --wait || exit 1

URL=http://localhost:8080/
echo "The website is running:  $URL"
echo "Backups (phpMyAdmin):    http://localhost:8081/"
echo "To stop it, run:  sh stop.sh"
if command -v open >/dev/null 2>&1; then
    open "$URL"
elif command -v xdg-open >/dev/null 2>&1; then
    xdg-open "$URL"
fi
