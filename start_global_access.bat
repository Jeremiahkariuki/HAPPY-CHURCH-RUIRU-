@echo off
echo ========================================================
echo   CHURCH EVENTS SYSTEM - GLOBAL ACCESS HELPER
echo ========================================================
echo.
echo To view your system from ANY mobile phone or laptop in the world:
echo.
echo 1. Ensure XAMPP (Apache and MySQL) is running.
echo 2. Ensure you have Ngrok installed and in your PATH.
echo.
echo Attempting to start the tunnel...
echo.
ngrok http 80
echo.
if %errorlevel% neq 0 (
    echo [ERROR] Ngrok was not found. Please download it from ngrok.com
    echo and place it in your XAMPP folder or add it to your System PATH.
)
pause
