# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- JSON remote API client (`remote/json.php`) without ext-soap, with cached sessions and one re-login after an expired session.
- Resources for clients, websites (subdomains, alias domains, Let's Encrypt), the domain module, DNS zones and records, mail domains, mailboxes, aliases, forwards and catchalls, databases and database users, cron jobs, FTP users, backups and servers.
- Typed data objects, restored SOAP fault codes, `AuthenticationException`, `ApiException` and `RateLimitException`.
- Named and runtime connections, `call()` for any remote function and the `ispconfig:check` command.
