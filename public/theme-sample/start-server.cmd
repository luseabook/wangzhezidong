@echo off
cd /d "%~dp0\..\.."
echo UI sample: http://127.0.0.1:4321/theme-sample/index.html
echo Press Ctrl+C to stop.
node public\theme-sample\server.mjs
pause