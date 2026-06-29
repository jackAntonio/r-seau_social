@echo off
set PHP="C:\Users\pc\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe"
set DIR=%~dp0

echo === Demarrage du serveur PHP pour le Reseau Social ===
echo.
%PHP% -r "echo 'PHP : '; echo PHP_VERSION; echo PHP_EOL; echo 'pdo_pgsql : '; echo extension_loaded('pdo_pgsql') ? 'OK' : 'MANQUANT'; echo PHP_EOL;"
echo.
echo Ouvrir : http://localhost:8080/register.html
echo Appuyer sur Ctrl+C pour arreter
echo.
%PHP% -S localhost:8080 -t "%DIR%" "%DIR%router.php"
