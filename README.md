WalletScript
============

WalletScript is a small PHP wallet front-end for Bitcoin-style JSON-RPC daemons.

What was updated
----------------

- Replaced deprecated `mysql_*` calls with PDO for PHP 8 compatibility.
- Switched new registrations to `password_hash()` and added automatic upgrade for legacy MD5 passwords on login.
- Added CSRF protection to login, registration, withdrawal, and address generation.
- Hardened request handling, output escaping, redirects, and session flow.
- Modernized the schema and setup instructions.

Requirements
------------

- PHP 8.1+ with `pdo_mysql`
- MySQL or MariaDB
- A Bitcoin-style RPC daemon with wallet/account RPC methods enabled

Configuration
-------------

WalletScript now reads configuration from environment variables:

```bash
export WALLETSCRIPT_DB_HOST=127.0.0.1
export WALLETSCRIPT_DB_NAME=walletscript
export WALLETSCRIPT_DB_USER=wallet_user
export WALLETSCRIPT_DB_PASS=strong_password

export WALLETSCRIPT_RPC_HOST=127.0.0.1
export WALLETSCRIPT_RPC_PORT=8332
export WALLETSCRIPT_RPC_USER=rpc_user
export WALLETSCRIPT_RPC_PASS=rpc_password
```

Setup
-----

1. Create a database for WalletScript.
2. Import [`db.sql`](/Users/sambo/Documents/wallet/WalletScript/db.sql).
3. Set the database and RPC environment variables before serving the app.
4. Serve the project with PHP or your preferred web server.

Example local run:

```bash
php -S 127.0.0.1:8080
```

Security note
-------------

This project is still intentionally minimal and uses older wallet RPC patterns like `sendfrom` and account-based addressing, which many newer Bitcoin-family daemons have deprecated or removed. Verify your daemon still supports those calls before using this in production.
