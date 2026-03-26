@echo off
color 0B
echo ========================================================
echo   HAPPY CHURCH RUIRU - SECURE CLOUD LINK GENERATOR
echo ========================================================
echo.
echo Please wait 5 seconds while we generate a secure domain...
echo.
echo  *** INSTRUCTIONS ***
echo 1. Look carefully at the big green text that appears below.
echo 2. It will show a real web link (e.g. https://xxxxxx.pinggy.link)
echo 3. Copy or type THAT EXACT LINK into your mobile phone browser!
echo 4. Append /church_events_system/ to the end of the link.
echo.
echo You can even scan the QR code that appears with your phone's camera!
echo.
ssh -p 443 -R0:localhost:80 -o StrictHostKeyChecking=no a.pinggy.io
pause
