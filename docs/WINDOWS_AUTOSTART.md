# Windows Auto-Start

Unlucky 13 can start its local Laravel API and persistent Python worker automatically when the Windows user logs in.

## What it does

Two Task Scheduler entries are installed for the current Windows user:

- Unlucky13 Laravel API
- Unlucky13 Worker

The tasks launch hidden PowerShell runner scripts. Each runner keeps its process alive and restarts it if it exits unexpectedly.

The worker still follows the application safety rules:

- only Active campaigns are processed
- paused campaigns are skipped
- global website deduplication remains active
- outreach drafts still require human review
- approved email delivery is not performed by the scheduler

## Install

First stop any manually running Laravel server or python worker/run.py work window with Ctrl+C. This avoids running a manual process alongside the scheduled process.

From the repository root:

    powershell -NoProfile -ExecutionPolicy Bypass -File scripts\windows\install-autostart.ps1 -StartNow

Normally the tasks can be created for the current user without elevation. If Windows returns Access Denied, open PowerShell once as Administrator and run the installer again.

## Check status

    powershell -NoProfile -ExecutionPolicy Bypass -File scripts\windows\status-autostart.ps1

Runtime logs are written to:

    storage\logs\unlucky13-laravel-autostart.log
    storage\logs\unlucky13-worker-autostart.log

## Temporarily turn auto-start OFF

This stops both scheduled tasks and disables them without deleting the configuration:

    powershell -NoProfile -ExecutionPolicy Bypass -File scripts\windows\disable-autostart.ps1

## Turn auto-start back ON

Enable for the next logon:

    powershell -NoProfile -ExecutionPolicy Bypass -File scripts\windows\enable-autostart.ps1

Enable and start immediately:

    powershell -NoProfile -ExecutionPolicy Bypass -File scripts\windows\enable-autostart.ps1 -StartNow

## Remove auto-start completely

    powershell -NoProfile -ExecutionPolicy Bypass -File scripts\windows\uninstall-autostart.ps1

## PC power behavior

Windows auto-start does not make a powered-off PC run jobs. For local operation:

- the PC must remain on
- internet must remain connected
- Windows sleep should be disabled while the machine is expected to work

A future VPS deployment can run the same Laravel and worker processes without depending on the local PC.
