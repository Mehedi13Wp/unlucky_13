$ErrorActionPreference = 'Continue'

$taskNames = @('Unlucky13 Laravel API', 'Unlucky13 Worker')

foreach ($taskName in $taskNames) {
    $task = Get-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue

    if ($null -eq $task) {
        Write-Host "$taskName: not installed"
        continue
    }

    Stop-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue
    Disable-ScheduledTask -TaskName $taskName | Out-Null
    Write-Host "$taskName: disabled and stopped"
}

Write-Host ''
Write-Host 'Unlucky 13 auto-start is OFF. The tasks are kept so they can be enabled later.'
