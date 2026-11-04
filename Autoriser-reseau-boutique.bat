@echo off
chcp 65001 >nul
title Autoriser MOK-Business sur le reseau (une seule fois)

echo Cette action autorise les telephones a se connecter au serveur.
echo A lancer UNE SEULE FOIS, en tant qu'administrateur :
echo    clic droit sur ce fichier  ^>  "Executer en tant qu'administrateur".
echo.

netsh advfirewall firewall add rule name="MOK-Business (port 8000)" dir=in action=allow protocol=TCP localport=8000

if %errorlevel%==0 (
  echo.
  echo OK : le port 8000 est autorise. Les telephones peuvent se connecter.
) else (
  echo.
  echo Echec : relancez ce fichier en tant qu'administrateur.
)
echo.
pause
