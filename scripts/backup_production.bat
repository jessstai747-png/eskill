@echo off
REM Script de Backup Automatizado para Produção (Windows)
REM Configurar no Agendador de Tarefas

set BACKUP_DIR=C:\backups\mercadolivre
set DB_NAME=mercadolivre_db
set DB_USER=ml_user
set DB_PASS=senha_forte_aqui
set RETENTION_DAYS=30

REM Criar diretório se não existir
if not exist "%BACKUP_DIR%" mkdir "%BACKUP_DIR%"

REM Nome do arquivo com data
for /f "tokens=2-4 delims=/ " %%a in ('date /t') do set mydate=%%c%%b%%a
for /f "tokens=1-2 delims=/:" %%a in ('time /t') do set mytime=%%a%%b
set BACKUP_FILE=%BACKUP_DIR%\backup_%mydate%_%mytime%.sql

REM Fazer backup do banco
"C:\xampp\mysql\bin\mysqldump.exe" -u %DB_USER% -p%DB_PASS% %DB_NAME% > %BACKUP_FILE%

REM Comprimir (requer 7zip ou similar)
REM "C:\Program Files\7-Zip\7z.exe" a "%BACKUP_FILE%.zip" "%BACKUP_FILE%"

REM Log
echo %date% %time%: Backup criado: %BACKUP_FILE% >> %BACKUP_DIR%\backup.log
