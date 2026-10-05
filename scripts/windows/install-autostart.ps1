param(
    [switch]$StartNow
)

$ErrorActionPreference = 'Stop'

$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
$laravelRunner = Join-Path $PSScriptRoot 'run-laravel.ps1'
$schedulerRunner = Join-Path $PSScriptRoot 'run-scheduler.ps1'
$workerRunner = Join-Path $PSScriptRoot 'run-worker.ps1'
$taskLaravel = 'Unlucky13 Laravel API'
$taskScheduler = 'Unlucky13 Laravel Scheduler'
$taskWorker = 'Unlucky13 Worker'
$userId = [System.Security.Principal.WindowsIdentity]::GetCurrent().Name
$powerShellExe = Join-Path $PSHOME 'powershell.exe'

if (-not (Test-Path $laravelRunner)) { throw "Missing runner: $laravelRunner" }
if (-not (Test-Path $schedulerRunner)) { throw "Missing runner: $schedulerRunner" }
if (-not (Test-Path $workerRunner)) { throw "Missing runner: $workerRunner" }

function New-Unlucky13Action {
    param([string]$ScriptPath)

    $arguments = '-NoLogo -NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File "' + $ScriptPath + '"'
    return New-ScheduledTaskAction -Execute $powerShellExe -Argument $arguments -WorkingDirectory $repoRoot
}

$trigger = New-ScheduledTaskTrigger -AtLogOn -User $userId
$settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable -RestartCount 999 -RestartInterval (New-TimeSpan -Minutes 1) -ExecutionTimeLimit ([TimeSpan]::Zero) -MultipleInstances IgnoreNew
$principal = New-ScheduledTaskPrincipal -UserId $userId -LogonType Interactive -RunLevel Limited

try {
    Register-ScheduledTask -TaskName $taskLaravel -Action (New-Unlucky13Action $laravelRunner) -Trigger $trigger -Settings $settings -Principal $principal -Description 'Starts and keeps the local Unlucky 13 Laravel API available after Windows logon.' -Force | Out-Null
    Register-ScheduledTask -TaskName $taskScheduler -Action (New-Unlucky13Action $schedulerRunner) -Trigger $trigger -Settings $settings -Principal $principal -Description 'Starts and keeps Laravel schedule:work running for approved delivery automation.' -Force | Out-Null
    Register-ScheduledTask -TaskName $taskWorker -Action (New-Unlucky13Action $workerRunner) -Trigger $trigger -Settings $settings -Principal $principal -Description 'Starts and keeps the Unlucky 13 multi-campaign worker running after Windows logon.' -Force | Out-Null
}
catch {
    Write-Error "Could not register the Unlucky 13 startup tasks. $($_.Exception.Message) If Windows reports Access Denied, open PowerShell once as Administrator and run this installer again."
    exit 1
}

Write-Host 'Installed Windows auto-start tasks:'
Write-Host "  - $taskLaravel"
Write-Host "  - $taskScheduler"
Write-Host "  - $taskWorker"
Write-Host ''
Write-Host 'They will start automatically at the next Windows logon.'

if ($StartNow) {
    Start-ScheduledTask -TaskName $taskLaravel
    Start-Sleep -Seconds 3
    Start-ScheduledTask -TaskName $taskScheduler
    Start-Sleep -Seconds 2
    Start-ScheduledTask -TaskName $taskWorker
    Write-Host ''
    Write-Host 'Started all three tasks now.'
}

Write-Host ''
Write-Host 'Status:'
Get-ScheduledTask -TaskName $taskLaravel, $taskScheduler, $taskWorker | Select-Object TaskName, State | Format-Table -AutoSize
