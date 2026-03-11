@echo off
chcp 65001 >nul
echo ========================================
echo   AulaBot - Commit e push para GitHub
echo ========================================
echo.

where git >nul 2>&1
if errorlevel 1 (
    echo [ERRO] Git nao encontrado. Instale em: https://git-scm.com/download/win
    echo Depois feche e abra o terminal e execute este script de novo.
    pause
    exit /b 1
)

cd /d "%~dp0"

echo 1. Adicionando ficheiros...
git add .
echo.
echo 2. Estado:
git status
echo.
set /p MSG="Mensagem do commit (ex: Primeiro commit): "
if "%MSG%"=="" set MSG=Atualizacao do projeto AulaBot
echo.
echo 3. A fazer commit: %MSG%
git commit -m "%MSG%"
if errorlevel 1 (
    echo Nenhuma alteracao para commitar, ou commit feito com sucesso.
)
echo.
echo 4. A enviar para GitHub (origin main)...
git push -u origin main
if errorlevel 1 (
    echo.
    echo Se pedir login: use um Personal Access Token em vez da password.
    echo Crie em: GitHub - Settings - Developer settings - Personal access tokens
)
echo.
echo Concluido.
pause
