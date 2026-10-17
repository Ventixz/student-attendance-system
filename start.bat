@echo off
rem Starts the attendance website with Docker and opens it in your browser.
title AttendTrack
cd /d "%~dp0"

docker info >nul 2>&1
if errorlevel 1 (
    echo.
    echo   Docker Desktop is not running.
    echo   Open Docker Desktop, wait until it says "Engine running",
    echo   then double-click start.bat again.
    echo.
    pause
    exit /b 1
)

echo.
echo   Starting the attendance website...
echo   The first time takes a few minutes while Docker downloads what it needs.
echo.
docker compose up -d --build
if errorlevel 1 (
    echo.
    echo   Something went wrong. Read the messages above, or see
    echo   "Troubleshooting" in DOCKER-GUIDE.md.
    echo.
    pause
    exit /b 1
)

echo.
echo   Waiting for the website to answer...
set tries=0
:wait
curl.exe -s -o nul http://localhost:8080/ && goto ready
set /a tries+=1
if %tries% geq 60 goto ready
timeout /t 2 /nobreak >nul
goto wait

:ready
start "" http://localhost:8080/
echo.
echo   The website is running:   http://localhost:8080/
echo   Backups (phpMyAdmin):     http://localhost:8081/
echo.
echo   You can close this window. To stop the website, double-click stop.bat.
echo.
pause
