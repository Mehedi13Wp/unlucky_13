$ErrorActionPreference = 'Continue'

$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$logDir = Join-Path $repoRoot 'storage\logs'
$logFile = Join-Path $logDir 'unlucky13-scheduler-autostart.log'
$mutex = New-Object System.Threading.Mutex($false, 'Local\Unlucky13SchedulerAutostart')

New-Item -ItemType Directory -Force -Path $logDir | Out-Null

function Write-RunnerLog {
    param([string]$Message)
    $line = "[{0}] {1}" -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $Message
    Add-Content -Path $logFile -Value $line
}

if (-not $mutex.WaitOne(0)) {
    Write-RunnerLog 'Another scheduler autostart runner is already active. Exiting.'
    exit 0
}

try {
    Set-Location $repoRoot
    Write-RunnerLog "Scheduler runner started in $repoRoot"
    Start-Sleep -Seconds 5

    while ($true) {
        try {
            $php = (Get-Command php -ErrorAction Stop).Source

            if (-not (Test-Path (Join-Path $repoRoot 'artisan'))) {
                Write-RunnerLog 'artisan was not found. Retrying in 30 seconds.'
                Start-Sleep -Seconds 30
                continue
            }

            Write-RunnerLog 'Starting Laravel schedule:work.'
            & $php artisan schedule:work *>> $logFile
            $exitCode = $LASTEXITCODE
            Write-RunnerLog "Scheduler process exited with code $exitCode. Restarting in 5 seconds."
        }
        catch {
            Write-RunnerLog "Scheduler runner error: $($_.Exception.Message). Retrying in 15 seconds."
            Start-Sleep -Seconds 15
            continue
        }

        Start-Sleep -Seconds 5
    }
}
finally {
    try { $mutex.ReleaseMutex() } catch {}
    $mutex.Dispose()
}
