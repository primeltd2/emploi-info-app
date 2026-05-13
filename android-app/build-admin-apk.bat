@echo off
setlocal

cd /d "%~dp0"
call gradlew.bat :app:assembleAdminRelease
if errorlevel 1 exit /b %errorlevel%

if not exist "..\downloads" mkdir "..\downloads"
copy /Y "app\build\outputs\apk\admin\release\app-admin-release.apk" "..\downloads\admin-emploi-info.apk"
if errorlevel 1 exit /b %errorlevel%

echo APK administrative prete: ..\downloads\admin-emploi-info.apk
