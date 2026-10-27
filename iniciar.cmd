@echo off
cd /d "%~dp0"
".runtime\php\php.exe" -c ".runtime\php\php.ini" -S 127.0.0.1:8088 -t public public/router.php
pause
