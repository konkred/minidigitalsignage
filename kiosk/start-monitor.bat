@echo off
rem Startet den Browser im Vollbild als Signage-Monitor und nach einem Absturz neu.
rem Wer den Browser regulaer schliesst (Alt+F4), beendet auch das Skript.
rem Einmalig nach der Installation mit --setup aufrufen, wenn die Erweiterung
rem von Hand geladen werden muss (noetig bei Google Chrome).
setlocal

set "EXTENSION_DIR=%~dp0..\extension"
for %%I in ("%EXTENSION_DIR%") do set "EXTENSION_DIR=%%~fI"
set "PROFILE_DIR=%LOCALAPPDATA%\SignageMonitor"

set "BROWSER=%SIGNAGE_BROWSER%"
if not defined BROWSER if exist "%LOCALAPPDATA%\Chromium\Application\chrome.exe" set "BROWSER=%LOCALAPPDATA%\Chromium\Application\chrome.exe"
if not defined BROWSER if exist "%ProgramFiles%\Chromium\Application\chrome.exe" set "BROWSER=%ProgramFiles%\Chromium\Application\chrome.exe"
if not defined BROWSER if exist "%ProgramFiles%\Google\Chrome\Application\chrome.exe" set "BROWSER=%ProgramFiles%\Google\Chrome\Application\chrome.exe"
if not defined BROWSER if exist "%ProgramFiles(x86)%\Google\Chrome\Application\chrome.exe" set "BROWSER=%ProgramFiles(x86)%\Google\Chrome\Application\chrome.exe"
if not defined BROWSER if exist "%LOCALAPPDATA%\Google\Chrome\Application\chrome.exe" set "BROWSER=%LOCALAPPDATA%\Google\Chrome\Application\chrome.exe"
if not defined BROWSER (
    echo Kein Chromium oder Chrome gefunden. Pfad ueber SIGNAGE_BROWSER angeben.
    pause
    exit /b 1
)

if /i "%~1"=="--setup" (
    echo Erweiterungsordner: %EXTENSION_DIR%
    start "" "%BROWSER%" --user-data-dir="%PROFILE_DIR%" --no-first-run chrome://extensions
    pause
    exit /b 0
)

:loop
start "" /wait "%BROWSER%" --kiosk --user-data-dir="%PROFILE_DIR%" --load-extension="%EXTENSION_DIR%" --no-first-run --noerrdialogs --disable-infobars --disable-session-crashed-bubble --disable-features=Translate about:blank
if errorlevel 1 (
    echo Browser abgestuerzt, Neustart in 5 Sekunden.
    timeout /t 5 /nobreak >nul
    goto loop
)
