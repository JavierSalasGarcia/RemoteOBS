@echo off
cd /d %~dp0
:loop
python agent.py
timeout /t 5 /nobreak >nul
goto loop
