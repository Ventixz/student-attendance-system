@echo off
rem Stops the attendance website. All attendance data is kept for next time.
title AttendTrack
cd /d "%~dp0"

docker compose stop
echo.
echo   The attendance website has stopped. Your data is saved.
echo   Double-click start.bat to start it again.
echo.
pause
