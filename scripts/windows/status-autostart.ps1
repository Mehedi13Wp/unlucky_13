$ErrorActionPreference = 'Continue'
$taskNames = @('Unlucky13 Laravel API', 'Unlucky13 Worker')

$rows = foreach ($taskName in $taskNames) {
    $task = Get-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue

    if ($null -eq $task) {
        [PSCustomObject]@{
            TaskName = $taskName
            Installed = $false
            State = 'NotInstalled'
            Enabled = $false
        }
        continue
    }

    [PSCustomObject]@{
        TaskName = $taskName
        Installed = $true
        State = [string]$task.State
        Enabled = [string]$task.State -ne 'Disabled'
    }
}

$rows | Format-Table -AutoSize

$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..\..')).Path
Write-Host ''
Write-Host 'Logs:'
Write-Host "  $(Join-Path $repoRoot 'storage\logs\unlucky13-laravel-autostart.log')"
Write-Host "  $(Join-Path $repoRoot 'storage\logs\unlucky13-worker-autostart.log')"
