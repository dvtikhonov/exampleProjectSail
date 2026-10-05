# Запуск обычного профиля Chrome с remote debugging (порт 9222).
# Важно: перед запуском закрыть ВСЕ окна Chrome; дальше не стартовать Chrome
# обычным ярлыком — иначе CDP может пропасть.
$ErrorActionPreference = 'Stop'

Write-Host 'Stopping all chrome.exe...'
Get-Process chrome -ErrorAction SilentlyContinue | Stop-Process -Force -ErrorAction SilentlyContinue
Start-Sleep -Seconds 2
Get-Process chrome -ErrorAction SilentlyContinue | Stop-Process -Force -ErrorAction SilentlyContinue
Start-Sleep -Seconds 1

$chrome = 'C:\Program Files\Google\Chrome\Application\chrome.exe'
if (-not (Test-Path $chrome)) {
  throw "Chrome not found: $chrome"
}

$userData = Join-Path $env:LOCALAPPDATA 'Google\Chrome\User Data'
$port = 9222

Write-Host "Starting Chrome with CDP on $port (profile: $userData)"
Start-Process -FilePath $chrome -ArgumentList @(
  "--remote-debugging-port=$port",
  "--user-data-dir=$userData",
  '--restore-last-session',
  '--no-first-run'
)

for ($i = 0; $i -lt 30; $i++) {
  Start-Sleep -Milliseconds 400
  try {
    $null = Invoke-WebRequest -Uri "http://127.0.0.1:$port/json/version" -UseBasicParsing -TimeoutSec 1
    Write-Host "CDP ready: http://127.0.0.1:$port"
    Write-Host 'If Briskly tab is missing: Ctrl+Shift+T to restore session (SMS only if /auth).'
    Write-Host 'Verify CDP: open http://127.0.0.1:9222/json in this Chrome — must show JSON.'
    Write-Host 'Keep this Chrome open; do not start a second Chrome via normal shortcut.'
    exit 0
  } catch {
    # retry
  }
}

Write-Host 'ERROR: CDP port did not open. Close all Chrome windows and retry.'
exit 1
