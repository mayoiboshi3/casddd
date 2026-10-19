# Database caching

Data pages and JSON endpoints use includes/data_cache.php for mysqli queries, prepared statements, and PDO reads. Cached results live in an application-specific directory under PHP's system temp directory, outside the web root.

- Default lifetime: 120 seconds (2 minutes), including chat, notification, and polling endpoints.
- Keys include the database connection identity, session/user/role, SQL, bound values, and invalidation generation.
- Both database bootstraps invalidate at the start and end of POST/PUT/PATCH/DELETE requests. This covers existing native mysqli and PDO write handlers, including handlers that call exit.
- Cached query wrappers also invalidate after changed rows and schema changes. No-op housekeeping updates retain warm entries.
- Login/password/security checks, database-clock and schema inspection queries, and reads during mutating requests run directly against the database. PDO transaction reads also bypass caching.
- Oversized results (over 2 MB) and unavailable cache storage fall back to database reads. Expired files are cleaned opportunistically.

Set APP_CACHE_ENABLED=0 in the PHP environment to disable caching. APP_CACHE_TTL changes the default lifetime in seconds.

Writes made outside these application requests (SQL console, another application, scheduled scripts) become visible when entries expire. Such writers can call data_cache_invalidate() after committing for immediate refresh.

Run integration checks with:

    C:\xampp\php\php.exe casddd/test-support/data_cache_test.php

The checks use only temporary database tables and cache files. They use MYSQLHOST, MYSQLPORT, MYSQLUSER, MYSQLPASSWORD, and MYSQLDATABASE when set; otherwise they use the local XAMPP defaults and database db2.
