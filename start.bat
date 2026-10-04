@echo off
echo ========================================================
echo   Starting LearnHub - Online Learning Platform
echo ========================================================

:: Check if MySQL is already listening
netstat -ano | findstr "3306 3307" >nul
if %errorlevel% neq 0 (
    echo Starting MySQL database on port 3307...
    start "" "C:\xampp\mysql\bin\mysqld.exe" --defaults-file="C:\xampp\mysql\bin\my.ini" --port=3307 --standalone
    timeout /t 2 /nobreak >nul
)

:: Open the website in browser
echo Opening website in your default browser...
start http://localhost/learnhub/

echo Done! Website running at: http://localhost/learnhub/
pause
