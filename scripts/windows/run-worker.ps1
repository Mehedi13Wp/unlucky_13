$ErrorActionPreference = 'Continue'

$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$logDir = Join-Path $repoRoot 'storage\logs'
$logFile = Join-Path $logDir 'unlucky13-worker-autostart.log'
$mutex = New-Object System.Threading.Mutex($false, 'Local\Unlucky13WorkerAutostart')

New-Item -ItemType Directory -Force -Path $logDir | Out-Null

function Write-RunnerLog {
    param([string]$Message)
    $line = "[{0}] {1}" -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $Message
    Add-Content -Path $logFile -Value $line
}

if (-not $mutex.WaitOne(0)) {
    Write-RunnerLog 'Another worker autostart runner is already active. Exiting.'
    exit 0
}

try {
    Set-Location $repoRoot
    Write-RunnerLog "Worker runner started in $repoRoot"
    Start-Sleep -Seconds 10

    while ($true) {
        try {
            $python = (Get-Command python -ErrorAction Stop).Source

            if (-not (Test-Path (Join-Path $repoRoot 'worker\run.py'))) {
                Write-RunnerLog 'worker\run.py was not found. Retrying in 30 seconds.'
                Start-Sleep -Seconds 30
                continue
            }

            Write-RunnerLog 'Starting persistent multi-campaign worker.'
            & $python worker\run.py work *>> $logFile
            $exitCode = $LASTEXITCODE
            Write-RunnerLog "Worker process exited with code $exitCode. Restarting in 5 seconds."
        }
        catch {
            Write-RunnerLog "Worker runner error: $($_.Exception.Message). Retrying in 15 seconds."
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
