# Windows Auto-Start

Unlucky 13 can start its local Laravel API, Laravel scheduler, and persistent Python worker automatically when the Windows user logs in.

## What it does

Three Task Scheduler entries are installed for the current Windows user:

- Unlucky13 Laravel API
- Unlucky13 Laravel Scheduler
- Unlucky13 Worker

The tasks launch hidden PowerShell runner scripts. Each runner keeps its process alive and restarts it if it exits unexpectedly.

The three processes have different jobs:

- Laravel API serves the local web application and worker API.
- Laravel Scheduler runs `php artisan schedule:work` and executes the approved-email delivery automation every minute.
- Python Worker handles discovery, analysis, contact discovery, and outreach drafting.

Delivery safety still applies:

- only Active campaigns are processed
- paused campaigns are skipped
- global website deduplication remains active
- outreach drafts still require human review
- live automatic email delivery requires an enabled, fully configured, SMTP-verified sender
- campaign, sender, suppression, retry, interval, session, and global daily limits remain enforced

## Install

First stop any manually running Laravel server, `php artisan schedule:work`, or `python worker/run.py work` window with Ctrl+C. This avoids running a manual process alongside the scheduled process.

From the repository root:

    powershell -NoProfile -ExecutionPolicy Bypass -File scripts\windows\install-autostart.ps1 -StartNow

Normally the tasks can be created for the current user without elevation. If Windows returns Access Denied, open PowerShell once as Administrator and run the installer again.

## Check status

    powershell -NoProfile -ExecutionPolicy Bypass -File scripts\windows\status-autostart.ps1

Runtime logs are written to:

    storage\logs\unlucky13-laravel-autostart.log
    storage\logs\unlucky13-scheduler-autostart.log
    storage\logs\unlucky13-worker-autostart.log
    storage\logs\laravel.log

The Laravel log includes aggregate `delivery-automation tick` entries with status, users checked, and sent count. Recipient addresses are not included in the scheduler tick log.

## Dry-run before live delivery

Set this in your local `.env`:

    UNLUCKY13_DELIVERY_DRY_RUN=true

Then clear cached configuration:

    php artisan optimize:clear

In dry-run mode, sender-account delivery uses Laravel's log transport instead of making a real SMTP delivery connection. Database state still follows the successful delivery path, so use only disposable test campaigns and test leads.

When the full dry-run is complete, stop automation first, change:

    UNLUCKY13_DELIVERY_DRY_RUN=false

and run:

    php artisan optimize:clear

Live automatic delivery will then require at least one SMTP-verified sender.

## SMTP sender verification

Saving or importing sender credentials clears any previous verification.

From **Sender Accounts**, click **Verify SMTP** for each sender you plan to use live. Verification sends one test email from that configured sender back to the same sender address. A successful test records the verification timestamp. Failed verification records the error without exposing the SMTP password.

Do not start live automation until at least one enabled sender shows **Verified**.

## Temporarily turn auto-start OFF

This stops all three scheduled tasks and disables them without deleting the configuration:

    powershell -NoProfile -ExecutionPolicy Bypass -File scripts\windows\disable-autostart.ps1

## Turn auto-start back ON

Enable for the next logon:

    powershell -NoProfile -ExecutionPolicy Bypass -File scripts\windows\enable-autostart.ps1

Enable and start immediately:

    powershell -NoProfile -ExecutionPolicy Bypass -File scripts\windows\enable-autostart.ps1 -StartNow

## Remove auto-start completely

    powershell -NoProfile -ExecutionPolicy Bypass -File scripts\windows\uninstall-autostart.ps1

## Final pre-live checks

From the repository root:

    git pull --ff-only
    composer install
    php artisan optimize:clear
    php artisan migrate
    php artisan test
    php artisan schedule:list

Required conditions before real sender CSV use:

- all migrations have run
- full PHPUnit suite is green
- `delivery-automation` appears in `schedule:list`
- Windows auto-start status shows all three tasks installed
- at least one sender is enabled, configured, and SMTP-verified
- the real credential CSV is not committed to Git
- `APP_KEY` is backed up securely because sender passwords are encrypted with the application key
- dry-run has been tested with disposable data before live mode

## PC power behavior

Windows auto-start does not make a powered-off PC run jobs. For local operation:

- the PC must remain on
- internet must remain connected
- Windows sleep should be disabled while the machine is expected to work

A future VPS deployment can run the same Laravel API, scheduler, and worker processes without depending on the local PC.
