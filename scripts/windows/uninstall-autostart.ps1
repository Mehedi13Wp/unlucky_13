$ErrorActionPreference = 'Continue'
$taskNames = @('Unlucky13 Laravel API', 'Unlucky13 Worker')

foreach ($taskName in $taskNames) {
    $task = Get-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue

    if ($null -eq $task) {
        Write-Host "$taskName: not installed"
        continue
    }

    Stop-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue
    Unregister-ScheduledTask -TaskName $taskName -Confirm:$false
    Write-Host "$taskName: removed"
}

Write-Host ''
Write-Host 'Unlucky 13 Windows auto-start has been removed.'
