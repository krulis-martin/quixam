# Quixam

Quixam is a web application for online examinations and test management. It is built around reusable test templates, scheduled exam terms, student registrations, and a browser-based test-taking interface. The application has been used for real university courses and is designed for workflows where teachers prepare tests locally and synchronize them to the server.

The project is implemented in PHP 8.2 on top of Nette, Doctrine/Nettrine, Latte, and MySQL/MariaDB. Besides the web UI, the repository also contains CLI commands for administration and a separate `tests-client` directory with scripts for managing templates, terms, and registrations through the REST API.

⚠️ Please note that the application was tailored for our specific needs and it is still under development. ⚠️

## Repository layout

- `app` - application code
- `bin/console` - CLI entry point for administration and maintenance
- `config` - shared and local application configuration
- `migrations` - database migrations
- `tests-client` - local client scripts for teachers and admins
- `www` - public web root

## Deployment From GitHub

This section describes a typical deployment to a server from a fresh clone.

### 1. Clone the repository

```bash
git clone https://github.com/krulis-martin/quixam.git
cd quixam
```

### 2. Install PHP dependencies

```bash
composer install --no-dev --optimize-autoloader
```

The application requires PHP 8.2 or newer (tested on PHP 8.2 and 8.3).

### 3. Prepare local configuration

Quixam requires `config/local.neon` configuration file, so this file must exist on the target instance. You may use `config/local.neon.example` as a starting point. It should stay out of version control and contain instance-specific settings such as:

- database connection parameters
- JWT verification key in `accessTokens.verificationKey`
- CAS configuration if external authentication is used
- any local overrides of application settings

A minimal deployment should define at least the database connection and the access token key.

### 4. Prepare writable directories

Make sure the following directories are writable by the web server and CLI user:

- `log`
- `temp`

The `cleaner` script removes generated cache and proxy files (recommended to run after deployment/updates and before running migrations):

```bash
./cleaner
```

### 5. Configure the web server

Point the document root to the `www` directory. All requests should be served through `www/index.php`. If you use Apache (recommended), the included `.htaccess` file should work without modifications (if mod_rewrite is enabled). Make sure the web server uses HTTPS (only).

### 6. Run database migrations

The following command applies all pending database migrations. It should be called after deployment and after any update that includes new migrations. The `--no-interaction` flag allows it to run non-interactively, which is useful for automated deployments.

```bash
php bin/console migrations:migrate --no-interaction
```

## Basic Usage

Quixam supports several roles. In practice, the most common workflows are administrative setup, teacher-side content management, and student test-taking.

### Administrator workflow

Administrators usually prepare the instance and user accounts.

Create a local user:

```bash
php bin/console users:add user@example.com --firstName=John --lastName=Doe --role=teacher
```

Set or reset a local password:

```bash
php bin/console users:passwd user@example.com
```

Load initial data or fixtures from YAML:

```bash
php bin/console db:fill path/to/data.yaml
```

Most importantly, the administrator must create the test templates and assign teachers to them (as owners). The teachers can then manage the content and scheduling of exams based on these templates.

Furthermore, the system logs actions of the users (students), to prevent cheating and to allow for later analysis. Currently, these data are accessible only to administrators through the database (and files).

### Teacher workflow

Teachers usually work with the scripts in `tests-client`, which are documented in more detail in [`tests-client/README.md`](tests-client/README.md).

The usual sequence is:

1. authenticate with `tests-client/login.php`
2. upload or synchronize a test template with `tests-client/upload.php`
3. create or update exam terms with `tests-client/test-terms.php`
4. register students that will take the test with `tests-client/register-students.php`

The `tests-client` scripts use a local `config.yaml` file and a saved access token. They are intended for workflows where test content is maintained locally in YAML and Markdown and then synchronized to the Quixam instance.

### Student workflow

Students sign in to the web application, see terms for which they were registered, enter the selected term, and complete the generated test in the browser.

The UI is straightforward and the home page briefly presents the question types.
