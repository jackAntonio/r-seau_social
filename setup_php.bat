@echo off
chcp 65001 >nul
cd /d "%~dp0"

set PHP=php
where php >nul 2>&1 || set PHP="C:\Users\pc\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe"

echo ============================================================
echo   Démarrage du serveur Forgab (Réseau Social)
echo ============================================================
echo Répertoire : %CD%
echo.
%PHP% -r "echo 'PHP Version : ' . PHP_VERSION . PHP_EOL; echo 'pdo_mysql   : ' . (extension_loaded('pdo_mysql') ? 'OK (XAMPP)' : 'MANQUANT') . PHP_EOL; echo 'pdo_pgsql   : ' . (extension_loaded('pdo_pgsql') ? 'OK (Supabase)' : 'MANQUANT') . PHP_EOL;"
echo.
echo Application disponible sur : http://localhost:8080/
echo.
echo Note : Pensez à démarrer MySQL dans XAMPP (Port 3306).
echo Pour initialiser la base de données automatiquement : php init_mysql.php
echo.
echo Appuyer sur Ctrl+C pour arrêter le serveur.
echo ============================================================
echo.

%PHP% -S localhost:8080 router.php
