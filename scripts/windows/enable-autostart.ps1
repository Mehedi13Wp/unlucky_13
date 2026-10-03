param(
    [switch]$StartNow
)

$ErrorActionPreference = 'Stop'
$taskNames = @('Unlucky13 Laravel API', 'Unlucky13 Worker')

foreach ($taskName in $taskNames) {
    $task = Get-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue

    if ($null -eq $task) {
        throw "$taskName is not installed. Run install-autostart.ps1 first."
    }

    Enable-ScheduledTask -TaskName $taskName | Out-Null
    Write-Host "${taskName}: enabled"
}

if ($StartNow) {
    Start-ScheduledTask -TaskName 'Unlucky13 Laravel API'
    Start-Sleep -Seconds 3
    Start-ScheduledTask -TaskName 'Unlucky13 Worker'
    Write-Host 'Both tasks started now.'
}

Write-Host ''
Write-Host 'Unlucky 13 auto-start is ON.'
