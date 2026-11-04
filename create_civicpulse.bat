
@echo off
setlocal

title CivicPulse Project Structure Generator

echo ========================================
echo       CivicPulse Project Generator
echo ========================================
echo.

REM Create root directory
if not exist "civicpulse" mkdir "civicpulse"
cd civicpulse

echo Creating framework directories...

REM Framework
mkdir "framework\src\Application" 2>nul
mkdir "framework\src\Routing" 2>nul
mkdir "framework\src\Http" 2>nul
mkdir "framework\src\Container" 2>nul
mkdir "framework\src\Database" 2>nul
mkdir "framework\src\Validation" 2>nul
mkdir "framework\src\Support" 2>nul
mkdir "framework\tests" 2>nul

REM Application
echo Creating application directories...

mkdir "app\Controllers" 2>nul
mkdir "app\Models" 2>nul
mkdir "app\Services" 2>nul
mkdir "app\Middleware" 2>nul
mkdir "app\Events" 2>nul
mkdir "app\Policies" 2>nul

REM Configuration
mkdir "config" 2>nul

REM Database
mkdir "database\migrations" 2>nul
mkdir "database\seeders" 2>nul

REM Public
mkdir "public" 2>nul

REM Routes
mkdir "routes" 2>nul

REM Storage
mkdir "storage" 2>nul

REM Tests
mkdir "tests" 2>nul

echo Creating initial files...

REM Public entry point
if not exist "public\index.php" (
    (
        echo ^<?php
        echo.
        echo declare(strict_types=1^);
        echo.
        echo // CivicPulse application entry point
        echo.
        echo require_once dirname(__DIR__) . '/vendor/autoload.php';
    ) > "public\index.php"
)

REM Web routes
if not exist "routes\web.php" (
    (
        echo ^<?php
        echo.
        echo declare(strict_types=1^);
        echo.
        echo // Register web routes here.
    ) > "routes\web.php"
)

REM Composer configuration
if not exist "composer.json" (
    (
        echo {
        echo     "name": "civicpulse/framework",
        echo     "description": "CivicPulse PHP Application",
        echo     "type": "project",
        echo     "require": {
        echo         "php": "^8.2"
        echo     },
        echo     "autoload": {
        echo         "psr-4": {
        echo             "CivicPulse\\": "app/",
        echo             "CivicPulse\\Framework\\": "framework/src/"
        echo         }
        echo     }
        echo }
    ) > "composer.json"
)

echo.
echo ========================================
echo CivicPulse structure created successfully!
echo ========================================
echo.
echo Project location:
cd
echo.
echo Next steps:
echo 1. Open the civicpulse folder in VS Code.
echo 2. Run composer install.
echo 3. Start building your framework.
echo.

pause
