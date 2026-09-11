# Guardian Vault

Guardian Vault is a PHP 8.2 and MariaDB customer/admin portal for managing safeguarded-item account records.

## Requirements

- PHP 8.2+ with PDO MySQL
- MariaDB 10.4+ or MySQL 8+
- Apache with `mod_headers` and `AllowOverride` enabled
- HTTPS in every non-local environment

## Configuration

Set the variables documented in `.env.example` through the web server or secret manager. The application permits the XAMPP `root`/blank-password defaults only when the request is local and `GUARDIAN_APP_ENV` is not `production`.

## Database

For an existing installation, back up the database and apply migrations in numeric order:

```sh
mysql -u guardian_app -p guardianvaultau < database/001_production_hardening.sql
mysql -u guardian_app -p guardianvaultau < database/002_email_uniqueness_after_resolution.sql
mysql -u guardian_app -p guardianvaultau < database/003_statement_snapshots.sql
mysql -u guardian_app -p guardianvaultau < database/004_admin_mfa.sql
mysql -u guardian_app -p guardianvaultau < database/005_remove_redundant_indexes.sql
```

Before applying `001_production_hardening.sql` elsewhere, resolve duplicate `user_id` rows in each child table. The migration intentionally fails rather than discard ambiguous financial or beneficiary records. It records colliding customer emails in `data_quality_issues`; resolve those records and then apply `002_email_uniqueness_after_resolution.sql`.

For a fresh installation, import `database/schema.sql` instead of replaying the migrations. It contains structure only and no customer data.

## Verification

Run:

```sh
php tools/verify.php
```

The verifier checks PHP syntax, required migrations, dangerous patterns, and application/database schema alignment without modifying data.

## Security notes

- Do not commit real credentials, database dumps, or customer data.
- Use a dedicated least-privileged database account.
- Terminate TLS at a trusted proxy or Apache and forward the original HTTPS scheme.
- Enrol every administrator in TOTP from the admin security screen, then set `GUARDIAN_ADMIN_MFA_REQUIRED=1`.
- Backups and logs contain sensitive personal and financial data and require encryption and access controls.
