param(
    [Parameter(Mandatory = $true)]
    [ValidateSet('status', 'start', 'stop')]
    [string]$Action,

    [Parameter(Mandatory = $true)]
    [string]$RepoRoot
)

$ErrorActionPreference = 'Stop'

$runtimeDir = Join-Path $RepoRoot 'storage\app\worker-runtime'
$pidFile = Join-Path $runtimeDir 'worker.pid'
$logDir = Join-Path $RepoRoot 'storage\logs'
$stdoutLog = Join-Path $logDir 'unlucky13-dashboard-worker.log'
$stderrLog = Join-Path $logDir 'unlucky13-dashboard-worker-error.log'
$workerScript = Join-Path $RepoRoot 'worker\run.py'

function Write-JsonResult {
    param(
        [bool]$Supported,
        [bool]$Running,
        [Nullable[int]]$WorkerProcessId,
        [string]$Message
    )

    [PSCustomObject]@{
        supported = $Supported
        running = $Running
        pid = $WorkerProcessId
        message = $Message
    } | ConvertTo-Json -Compress
}

function Get-WorkerProcess {
    if (-not (Test-Path $pidFile)) {
        return $null
    }

    $rawPid = (Get-Content -Path $pidFile -Raw -ErrorAction SilentlyContinue).Trim()
    $parsedPid = 0

    if (-not [int]::TryParse($rawPid, [ref]$parsedPid) -or $parsedPid -lt 1) {
        Remove-Item $pidFile -Force -ErrorAction SilentlyContinue
        return $null
    }

    $process = Get-CimInstance Win32_Process -Filter "ProcessId = $parsedPid" -ErrorAction SilentlyContinue

    if ($null -eq $process) {
        Remove-Item $pidFile -Force -ErrorAction SilentlyContinue
        return $null
    }

    $commandLine = [string]$process.CommandLine

    if (
        $commandLine.IndexOf($workerScript, [System.StringComparison]::OrdinalIgnoreCase) -lt 0 -or
        $commandLine.IndexOf(' work', [System.StringComparison]::OrdinalIgnoreCase) -lt 0
    ) {
        Remove-Item $pidFile -Force -ErrorAction SilentlyContinue
        return $null
    }

    return $process
}

try {
    New-Item -ItemType Directory -Force -Path $runtimeDir | Out-Null
    New-Item -ItemType Directory -Force -Path $logDir | Out-Null

    switch ($Action) {
        'status' {
            $process = Get-WorkerProcess

            if ($null -eq $process) {
                Write-JsonResult -Supported $true -Running $false -WorkerProcessId $null -Message 'Worker is stopped.'
                exit 0
            }

            Write-JsonResult -Supported $true -Running $true -WorkerProcessId ([int]$process.ProcessId) -Message 'Worker is running.'
            exit 0
        }

        'start' {
            $existing = Get-WorkerProcess

            if ($null -ne $existing) {
                Write-JsonResult -Supported $true -Running $true -WorkerProcessId ([int]$existing.ProcessId) -Message 'Worker is already running.'
                exit 0
            }

            if (-not (Test-Path $workerScript)) {
                throw "Worker entry point was not found: $workerScript"
            }

            $python = (Get-Command python -ErrorAction Stop).Source
            $startArgs = @{
                FilePath = $python
                ArgumentList = @($workerScript, 'work')
                WorkingDirectory = $RepoRoot
                WindowStyle = 'Hidden'
                RedirectStandardOutput = $stdoutLog
                RedirectStandardError = $stderrLog
                PassThru = $true
            }

            $process = Start-Process @startArgs
            Set-Content -Path $pidFile -Value $process.Id -Encoding ASCII
            Start-Sleep -Milliseconds 500

            $started = Get-WorkerProcess

            if ($null -eq $started) {
                Remove-Item $pidFile -Force -ErrorAction SilentlyContinue
                throw "Worker exited immediately. Check $stderrLog"
            }

            Write-JsonResult -Supported $true -Running $true -WorkerProcessId ([int]$started.ProcessId) -Message 'Worker started.'
            exit 0
        }

        'stop' {
            $process = Get-WorkerProcess

            if ($null -eq $process) {
                Write-JsonResult -Supported $true -Running $false -WorkerProcessId $null -Message 'Worker is already stopped.'
                exit 0
            }

            Stop-Process -Id ([int]$process.ProcessId) -Force -ErrorAction Stop
            Remove-Item $pidFile -Force -ErrorAction SilentlyContinue

            Write-JsonResult -Supported $true -Running $false -WorkerProcessId $null -Message 'Worker stopped.'
            exit 0
        }
    }
}
catch {
    Write-JsonResult -Supported $true -Running $false -WorkerProcessId $null -Message $_.Exception.Message
    exit 1
}
