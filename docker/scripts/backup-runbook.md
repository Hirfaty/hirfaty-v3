# Encrypted backups in Cloudflare R2: setup runbook

Do these **in this order**. Steps 1 to 4 change nothing the server is doing: the old local timer keeps working until step 5 deploys the new script, which aborts if `/etc/hirfaty/backup.env` is missing. Never paste a secret key or token into a chat.

## 1. On your computer: make the key

Install age (`winget install FiloSottile.age` on Windows, or a release from github.com/FiloSottile/age), then:

```bash
age-keygen -o hirfaty-backup.key
```

It prints the public key (`age1...`). The file holds the private key (`AGE-SECRET-KEY-1...`).

- Store the file in your password manager **and** one more place off the computer, such as an encrypted USB drive.
- It never goes on the server. **If it is lost, every backup is unreadable, for good.**

## 2. In Cloudflare: bucket, retention, token

1. R2 > Create bucket, named `hirfaty-backups`.
2. Bucket > Settings > **Bucket lock rules** > add a rule for all objects, retention **30 days**.
3. Bucket > Settings > **Object lifecycle rules** > add a rule for all objects, delete after **35 days**.
4. R2 > Manage API tokens > create a token with **Object Read & Write**, applied to **this bucket only**. Copy the Access Key ID and Secret Access Key straight into step 4; they are shown once.
5. Copy the bucket's S3 API URL from its Settings page (for step 4, up to `.com`).

Every night stores a full copy, so storage is about 35 times one backup. To estimate the bill, compare `sudo du -sh /var/backups/hirfaty/*` on the server with R2's pricing.

## 3. Optional but recommended: a monitor

The lifecycle rule keeps deleting old backups even if new ones stop arriving, so a silent failure would eventually leave nothing. On healthchecks.io, create a check with a period of 1 day and a grace of 2 hours, and copy its ping URL for step 4. Production and staging each need their own check.

## 4. On the server: tools and settings

```bash
sudo apt-get update && sudo apt-get install -y age rclone curl
```

```bash
sudo install -d -m 700 -o root -g root /etc/hirfaty
```

```bash
sudo install -m 600 -o root -g root /dev/null /etc/hirfaty/backup.env
```

```bash
sudo nano /etc/hirfaty/backup.env
```

Fill it in (the same content as `docker/scripts/backup.env.example`):

```
R2_ENDPOINT=https://<account-id>.r2.cloudflarestorage.com
R2_ACCESS_KEY_ID=
R2_SECRET_ACCESS_KEY=
R2_BUCKET=hirfaty-backups
AGE_RECIPIENT=age1...
BACKUP_HEARTBEAT_URL=
BACKUP_HEARTBEAT_URL_STAGING=
```

Prove the credentials and, most importantly, the lock, before deploying anything. This leaves one tiny encrypted object in `smoke-test/`, which the lifecycle rule removes.

```bash
sudo bash -c '
  set -euo pipefail
  . /etc/hirfaty/backup.env
  export RCLONE_CONFIG=/dev/null RCLONE_CONFIG_R2_TYPE=s3 RCLONE_CONFIG_R2_PROVIDER=Cloudflare RCLONE_CONFIG_R2_REGION=auto RCLONE_CONFIG_R2_ACL=private
  export RCLONE_CONFIG_R2_ENDPOINT="$R2_ENDPOINT" RCLONE_CONFIG_R2_ACCESS_KEY_ID="$R2_ACCESS_KEY_ID" RCLONE_CONFIG_R2_SECRET_ACCESS_KEY="$R2_SECRET_ACCESS_KEY" RCLONE_CONFIG_R2_NO_CHECK_BUCKET=true
  object="r2:$R2_BUCKET/smoke-test/$(date -u +%s).age"
  echo "smoke test" | age -r "$AGE_RECIPIENT" | rclone rcat "$object"
  rclone lsl "r2:$R2_BUCKET/smoke-test/"
  if rclone deletefile "$object" 2>/dev/null; then echo "PROBLEM: the delete worked, the bucket lock is NOT protecting backups"; else echo "OK: upload worked, delete refused by the bucket lock"; fi
'
```

Do not continue until it prints `OK`.

## 5. Deploy

Merge this branch to `main`. CI deploys it to `~/prod-hirfaty` (`git reset --hard`, then `make prod-release`). From this moment the 02:30 timer runs the new script, so do steps 6 and 7 the same day. Merge into `staging` too when you want `make stage-backup` to use it.

## 6. On the server: first backup and restore check

```bash
cd ~/prod-hirfaty && sudo make prod-backup
```

```bash
sudo make prod-backup-list
```

The new backup should say `complete`.

```bash
sudo make prod-backup-check
```

Paste the private key at the prompt (it is not shown, and it stays in memory only). It must end with `RESTORE CHECK PASSED`. Compare the row counts with what you know of the site. If the server might be compromised, do not type the key on it: run `restore.sh` from a clean machine instead.

## 7. On the server: the timer

```bash
sudo cp ~/prod-hirfaty/docker/systemd/hirfaty-backup.* /etc/systemd/system/ && sudo systemctl daemon-reload
```

```bash
sudo systemctl start hirfaty-backup.service && systemctl status hirfaty-backup.service --no-pager
```

```bash
systemctl list-timers hirfaty-backup.timer --no-pager
```

`status` should show `status=0/SUCCESS`, and the monitor from step 3 should show a ping.

## 8. Media browser

If it was running, recreate it so it drops the old backup mount:

```bash
make media-browser-stop
```

```bash
make media-browser
```

To see a backup's photos, download `media.tar.gz.age` from R2 and run this on your computer, then upload `media.tar.gz` on the page (5 GB maximum):

```bash
age -d -i hirfaty-backup.key -o media.tar.gz media.tar.gz.age
```

## 9. Only after step 6 passed: delete the old local backups

These are unencrypted copies of buyer data sitting on the server. Look before deleting. Decide for yourself whether to wait a night or two for the timer to prove itself.

```bash
sudo du -sh /var/backups/hirfaty /var/backups/hirfaty-staging
```

```bash
sudo rm -rf /var/backups/hirfaty /var/backups/hirfaty-staging
```

## Afterwards

- Run `sudo make prod-backup-check` every few months: a backup only counts once it has been restored.
- A real restore is described at the top of `docker/scripts/restore.sh`. **Never untar media over a volume in use.**
- To change the key, make a new one, put its public key in `AGE_RECIPIENT`, and keep the old private key for 35 days: each backup's `_complete` names the key it was encrypted to.
