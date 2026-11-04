@echo off
chcp 65001 >nul
title MOK-Business - Serveur de la boutique
cd /d "%~dp0"

echo ===============================================================
echo    MOK-BUSINESS - Serveur de la boutique (sans internet)
echo ===============================================================
echo.
echo    Adresse(s) de cet ordinateur sur le WiFi :
ipconfig | findstr /i "IPv4"
echo.
echo    Sur chaque telephone (connecte au MEME WiFi), ouvrez :
echo.
echo        http://ADRESSE-CI-DESSUS:8000/app/
echo        (exemple : http://192.168.1.188:8000/app/)
echo.
echo    - Laissez CETTE fenetre OUVERTE toute la journee.
echo    - Pour arreter le soir : fermez cette fenetre.
echo ===============================================================
echo.

php artisan serve --host=0.0.0.0 --port=8000

echo.
echo Le serveur s'est arrete. Appuyez sur une touche pour fermer.
pause >nul
