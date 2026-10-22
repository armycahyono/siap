@echo off
REM ============================================
REM  SIAP - Cloudflare Tunnel Quick Start
REM  Domain: siap.sudahonline.my.id
REM ============================================
echo.
echo ==========================================
echo   SIAP Online - siap.sudahonline.my.id
echo ==========================================
echo.

REM Check if Laragon/Apache is running
echo [1/3] Mengecek apakah web server aktif...
curl -s -o nul http://siap.test
if %errorlevel% neq 0 (
    echo.
    echo [!] Web server tidak aktif!
    echo [!] Pastikan Laragon sudah Start dan Apache berjalan.
    echo [!] Buka Laragon ^> klik "Start All"
    echo.
    pause
    exit /b 1
)
echo       Web server aktif. OK!

echo.
echo [2/3] Mengecek cloudflared...
if not exist "C:\Program Files (x86)\cloudflared\cloudflared.exe" (
    echo.
    echo [!] cloudflared belum terinstall!
    echo [!] Install dengan: winget install Cloudflare.cloudflared
    echo.
    pause
    exit /b 1
)
echo       cloudflared terinstall. OK!

echo.
echo [3/3] Memulai Cloudflare Tunnel...
echo.
echo ============================================
echo   SIAP aktif online di:
echo   https://siap.sudahonline.my.id
echo.
echo   Tunnel: sips-tunnel
echo   Tekan Ctrl+C untuk menghentikan tunnel.
echo ============================================
echo.

REM Start named tunnel using config.yml
"C:\Program Files (x86)\cloudflared\cloudflared.exe" tunnel run sips-tunnel

pause
