# BIS Standards AI Recommendation Engine

A working reference implementation of the SIH problem statement:
PHP frontend/backend (auth, dashboard, admin, user management) +
a Python/Flask AI microservice (sentence-transformer embeddings +
FAISS + keyword fusion) that recommends BIS standards from a
natural-language query.

## Folder structure

```
bis-recommender/
  database/schema.sql       -- MySQL schema + seed admin
  data/standards_verified.csv -- the standards knowledge base
  php/                       -- all PHP pages
  python/server.py           -- Flask AI microservice
  python/requirements.txt
  assets/css/app.css         -- shared stylesheet
```

## 1. Database setup

```bash
mysql -u root -p < database/schema.sql
```

Before running it, generate your own admin password hash and paste
it into the `INSERT INTO users ...` line at the bottom of
`schema.sql` in place of the placeholder:

```bash
php -r "echo password_hash('choose-a-strong-password', PASSWORD_DEFAULT), PHP_EOL;"
```

Never commit a real password hash to source control on a shared
repo — regenerate it locally.

## 2. Import the standards data

Start the PHP server (step 3), log in as admin, and open
`import_standards.php` from the dashboard sidebar — it reads
`data/standards_verified.csv` into the `standards` table and is
safe to re-run whenever the CSV changes.

## 3. Run the PHP app

```bash
cd php
php -S 127.0.0.1:8000
```

Visit `http://127.0.0.1:8000/index.php`, sign in with the seeded
admin account, then use "Add new user" on the dashboard (or
`admin_register.php`) to create government/private test accounts,
or let people self-register via `register.php`.

## 4. Run the AI microservice

```bash
cd python
python -m venv venv
source venv/bin/activate        # Windows: venv\Scripts\activate
pip install -r requirements.txt
python server.py
```

This starts Flask on `127.0.0.1:5000`, loads
`data/standards_verified.csv`, and builds the FAISS index in memory.
The PHP pages (`recommend.php` via `ai_proxy.php`, and the chat
widget via `chatbot.php`) both call this service — the PHP side
never talks to FAISS directly, which keeps the AI service
swappable and independently scalable.

Re-run the index after changing the CSV, either by restarting
`server.py` or by calling:

```bash
curl -X POST http://127.0.0.1:5000/reindex
```

(Wire this up to an admin-only button in `dashboard.php` with a
`curl_init` call from PHP if you want a one-click "rebuild index".)

## What was fixed from the original code

- **`profile.php`**: the role field is now read-only. Previously a
  user could submit `role=admin` from their own profile form and
  grant themselves admin access.
- **`manage_users.php`**: restored the admin session check that was
  commented out. Previously anyone could load the page directly and
  reset any user's password or change their role.
- **`admin_register.php`**: now requires an existing admin session.
  Previously it was a public page that created admin accounts with
  no authorization check at all.

## localStorage usage (frontend)

- `bis_signin_email` — remembers the login email only (never the
  password), cleared by the user's own browser controls.
- `bis_reg_fullname` / `bis_reg_email` / `bis_reg_username` — form
  persistence on the registration page, cleared automatically after
  a successful signup.
- `bis_recent_searches` — the last 8 queries typed into the
  recommendation search, rendered as clickable chips. Nothing
  sensitive, never sent to the server except as a fresh search.

All localStorage access is wrapped so the app still works if
storage is unavailable (private browsing, storage quota, etc).

## Predefined data baked into `recommend.php`

- Sector filter list matches the `sector` column values already in
  `standards_verified.csv`.
- Quick-suggestion chips: "steel structure design", "earthquake
  resistant design", "cement specification", "fire safety building
  code" — help first-time users who don't know what to type.
- Status badges: Active (green), Withdrawn (red), Reaffirmed/Draft
  (amber) — the AI service also uses status as a ranking multiplier
  so Withdrawn standards don't outrank Active ones at similar
  semantic similarity.
