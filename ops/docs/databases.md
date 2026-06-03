# Databases — connect, archive, delete

Reference for the MySQL databases on the firstchurch (HostGator/cPanel) account.
As of 2026-05-30 there are **10 databases**, but only **one is the live site**.

## ⚠️ The one rule

**Never touch `seattle1_wp806`** — that is the live WordPress database (308 MB).
Everything in the "Legacy" table below is from old Joomla / phpBB / earlier
WordPress installs and is safe to archive and drop. Always **archive before you
delete**, and keep the archive off-server.

## Inventory

| Database | User(s) | Size | What it is | Action |
|---|---|---|---|---|
| `seattle1_wp806` | `seattle1_wp806` | 308 MB | **Live WordPress** | **KEEP** |
| `seattle1_prodjoomla1` | `seattle1_prod` | 7.5 MB | Old Joomla (production) | archive + drop |
| `seattle1_joomla2` | `seattle1_joomla2` | 7.9 MB | Old Joomla | archive + drop |
| `seattle1_wrdp3` | `seattle1_wrdp3` | 15 MB | Old WordPress | archive + drop |
| `seattle1_devjoomla1` | `seattle1_dev` | 1.7 MB | Old Joomla (dev) | archive + drop |
| `seattle1_dev2joomla1` | `seattle1_dev`/`prod2` | 1.3 MB | Old Joomla (dev) | archive + drop |
| `seattle1_jo151` | `seattle1_jo151` | 1.0 MB | Old Joomla 1.5 | archive + drop |
| `seattle1_phpb1` | `seattle1_phpb1` | 1.0 MB | Old phpBB forum | archive + drop |
| `seattle1_wrdp2` | `seattle1_wrdp2` | 0.25 MB | Old WordPress | archive + drop |
| `seattle1_testsite` | (none) | 4 KB | Test scratch | drop |

Unused users seen on the account: `seattle1_sophia`, `seattle1_prod2` (drop with
their databases). Total legacy data is only ~35 MB — this cleanup is about
**tidiness and removing dormant credentials**, not disk space.

## Where the credentials live

- **Live WordPress DB** — in `~/public_html/wp-config.php` (`DB_NAME`, `DB_USER`,
  `DB_PASSWORD`, `DB_HOST=localhost`). Read them without opening the file:
  ```bash
  ssh firstchurch "cd ~/public_html && wp config get DB_NAME && wp config get DB_USER && wp config get DB_PASSWORD"
  ```
- **All DB users & databases** — cPanel → **MySQL® Databases**. cPanel does not
  show existing passwords in plaintext; if you need a legacy user's password you
  **reset** it there (or just use phpMyAdmin, which needs no password — see below).
- **Compromised legacy credential** — `seattle1_prod`'s password used to sit in
  plaintext in `~/scripts/backup.pl` (now deleted). Treat it as compromised:
  dropping that user (below) closes it; if you keep the DB for any reason, reset
  the password in cPanel.

## How to connect

**A. Live WordPress DB — easiest, via WP-CLI (no password needed):**
```bash
ssh firstchurch
cd ~/public_html
wp db query "SHOW TABLES;"     # run SQL
wp db cli                      # interactive mysql shell
```

**B. Any database via the mysql client (needs that DB's user + password):**
```bash
ssh firstchurch
mysql -u seattle1_prod -p seattle1_prodjoomla1   # prompts for password
```

**C. phpMyAdmin (GUI, all databases, no password to remember):**
cPanel → **phpMyAdmin**. Authenticates via your cPanel session, so you can browse
and export *any* of the databases above without knowing the per-user password.
Best option for the legacy DBs whose passwords you don't have.

## How to archive (dump) a database

Make a folder for archives (NOT the old `~/backup`, which we removed):
```bash
ssh firstchurch "mkdir -p ~/db-archive"
```

**Live WP DB (WP-CLI):**
```bash
ssh firstchurch "cd ~/public_html && wp db export ~/db-archive/wp806-$(date +%F).sql"
```

**A legacy DB you have the password for (mysqldump):**
```bash
ssh firstchurch "mysqldump -u seattle1_prod -p seattle1_prodjoomla1 | gzip > ~/db-archive/prodjoomla1-$(date +%F).sql.gz"
```

**Legacy DBs without a known password:** export them from **phpMyAdmin**
(select the DB → Export → Go) — downloads a `.sql` straight to your machine.

**Pull an archive down to this machine (off-server copy):**
```bash
scp firstchurch:db-archive/'*.gz' ~/church/web-db-archive/
```

## How to delete a database (and its user)

**Safest — cPanel GUI:** MySQL® Databases → find the DB → **Delete Database**;
then under "Current Users", **Delete** the matching user.

**Over SSH — cPanel UAPI (works without root):**
```bash
# archive first, verify the .sql, THEN:
ssh firstchurch "uapi Mysql delete_database name=seattle1_prodjoomla1"
ssh firstchurch "uapi Mysql delete_user name=seattle1_prod"
```
Repeat per database/user. Do **not** pass `seattle1_wp806` to either command.

> Plain `DROP DATABASE` via the mysql client usually fails on shared hosting
> (the per-DB user lacks the DROP privilege) — use the cPanel page or UAPI above.

## Recommended order for the legacy cleanup
1. `mkdir ~/db-archive`
2. Archive every legacy DB (mysqldump where you have creds; phpMyAdmin otherwise).
3. `scp` the archives to this machine and confirm they open / are non-empty.
4. Only then `delete_database` + `delete_user` for each legacy entry.
5. Leave `seattle1_wp806` (DB + user) completely alone.

## Archiving a whole separate WordPress site (files + DB)

Example: `paxchristiyoga.org` — a separate WordPress **4.6** (2016) install under
`public_html/paxchristiyoga.org`, on DB `seattle1_wrdp2`, ~165 MB of files. It is
too old to run on PHP 8 (`wp` and the site itself fatal with
`__autoload() is no longer supported`), so **use `mysqldump`, not `wp db export`.**

```bash
ssh firstchurch
mkdir -p ~/site-archive
PAX=/home3/seattle1/public_html/paxchristiyoga.org

# 1) Database — read creds from its own wp-config (no password printed)
PASS=$(wp --path=$PAX config get DB_PASSWORD)
mysqldump --no-tablespaces -u seattle1_wrdp2 -p"$PASS" seattle1_wrdp2 \
  | gzip > ~/site-archive/paxchristiyoga-db.sql.gz

# 2) Files — tar the whole install (core + wp-content)
tar -czf ~/site-archive/paxchristiyoga-files.tar.gz -C ~/public_html paxchristiyoga.org

# 3) Verify
gzip -t ~/site-archive/paxchristiyoga-db.sql.gz && echo "db archive OK"
tar -tzf ~/site-archive/paxchristiyoga-files.tar.gz | head
ls -lh ~/site-archive/
```
Then pull copies down to this machine (run on the NUC):
```bash
mkdir -p ~/church/site-archives
scp 'firstchurch:site-archive/paxchristiyoga-*' ~/church/site-archives/
```

**Optional decommission afterwards** (only once archives are verified + downloaded):
```bash
rm -rf /home3/seattle1/public_html/paxchristiyoga.org
ssh firstchurch "uapi Mysql delete_database name=seattle1_wrdp2"
ssh firstchurch "uapi Mysql delete_user     name=seattle1_wrdp2"
```
Also remove the `paxchristiyoga.org` **addon domain / subdomain** in cPanel
(Domains), otherwise the mapping lingers after the files are gone.
