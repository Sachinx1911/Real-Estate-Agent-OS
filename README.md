# RE360 — Real Estate Channel Partner Operating System

PHP + MySQL. No Node, no Composer, no build step — upload and run on Hostinger shared hosting.

---

## Hostinger वर कसे टाकायचे (deploy)

**संपूर्ण step-by-step guide: [DEPLOY.md](DEPLOY.md)** — PHP version, database, SSL,
permissions, health check, backup, troubleshooting सगळं तिथे आहे.

थोडक्यात:

1. **PHP 8.1+** सेट करा (hPanel → PHP Configuration)
2. **MySQL database** बनवा (hPanel → Databases)
3. **Files** `public_html/` मध्ये टाका (File Manager / Git / FTP)
4. `config/db.sample.php` ची copy `config/db.local.php` बनवून credentials भरा
5. Production mode आपोआप — domain वर असेल तर production, localhost वर development
6. `uploads/` आणि `logs/` ला permission **755** द्या
7. **SSL** चालू करा (hPanel → Security → SSL) — हे setup च्या आधी करा
8. `https://yourdomain.com/tools/healthcheck.php` उघडून सगळं हिरवं आहे का बघा
9. `https://yourdomain.com/setup.php` — tables + admin account तयार होतील
10. **`setup.php` delete करा** ⚠️
11. `https://yourdomain.com/` वर login करा

---

## Deployment साठी असलेली extra files

| File | काय करते |
|---|---|
| `DEPLOY.md` | संपूर्ण Hostinger guide (मराठी) |
| `tools/healthcheck.php` | Server तपासणी — PHP, extensions, DB, permissions, HTTPS |
| `tools/reset_password.php` | Password विसरल्यास emergency reset (`config/reset.allow` लागते) |
| `config/db.sample.php` | Credentials template → copy करून `db.local.php` बनवा |
| `.user.ini` | PHP limits (upload size, memory, timezone) |
| `.htaccess` | HTTPS redirect, security headers, caching, error pages |
| `403.php` `404.php` `500.php` | Custom error pages |
| `logs/` | PHP error log + login throttle (web वरून block) |
| `robots.txt` | Search engines ना block करते |

---

## Local testing (XAMPP, optional)

1. XAMPP install करा → Apache + MySQL start करा
2. हा folder `C:\xampp\htdocs\re360\` मध्ये copy करा
3. phpMyAdmin मध्ये `re360` नावाचा database बनवा
4. `config/db.php` मध्ये: host `127.0.0.1`, user `root`, pass रिकामा
5. `http://localhost/re360/setup.php` उघडा

---

## Demo data काढून टाकायचा असेल तर

phpMyAdmin मध्ये:
```sql
DELETE FROM inventory; DELETE FROM project_configurations; DELETE FROM towers;
DELETE FROM bookings; DELETE FROM site_visits; DELETE FROM client_requirements;
DELETE FROM clients; DELETE FROM projects; DELETE FROM cp_details;
DELETE FROM builders; DELETE FROM tasks; DELETE FROM activity_log;
```
(users table ला हात लावू नका — तुमचे login जाईल.)

---

## Folder structure

```
index.php          front controller — सर्व pages इथून जातात
login.php          sign in
setup.php          पहिल्यांदा चालवायची file (नंतर delete)
403/404/500.php    custom error pages
config/            db credentials + constants (web वरून blocked)
includes/          auth, csrf, header, sidebar, footer, helpers, icons, crud
pages/             प्रत्येक module चा एक file
api/               AJAX endpoints (search, इ.)
assets/css|js|img  dark theme + front-end + icons
sql/               schema.sql (tables) + seed.sql (demo data)
tools/             healthcheck.php, reset_password.php
logs/              php-error.log + login throttle (web वरून blocked)
uploads/           brochures, floor plans, documents
```

---

## Modules (सर्व तयार)

| Module | काय करतो |
|---|---|
| **Dashboard** | 6 KPI, inventory by location, project status donut, recent updates, top performing, project spotlight, live inventory (filters सह), client matcher, follow-ups + calendar |
| **Builders** | List, profile, 6-parameter reliability score, add/edit |
| **Projects** | Filters + 7 tabs (Overview / Inventory / Pricing / Amenities / Location / Legal / **Sales Intelligence**) |
| **Inventory** | Flat-level table, freshness engine, single + bulk paste entry |
| **Leads & Clients** | Pipeline, 3-part budget, requirement capture |
| **Matching Engine** | % score + ✅/⚠️ कारणे — client च्या BHK चे **प्रत्यक्ष available flats** बघून |
| **Site Visits** | Schedule + log, history |
| **Bookings** | Stage pipeline; flat status आपोआप update होतो |
| **Tasks & Follow Ups** | Priority, due date, overdue tracking |
| **Calendar** | Site visits + follow-ups + possession dates |
| **Offers** | Official vs verbal, expiry tracking |
| **Payment Plans** | Milestone breakdown (CLP / 20:80 / subvention) |
| **Legal / RERA** | Per-project document checklist |
| **CP Management** | Commission %, payout terms, lead rules |
| **Documents** | Upload brochures, floor plans, price sheets |
| **Reports** | "कुठे किती inventory?", ₹/sq.ft. benchmark, lead funnel, freshness health |
| **Comparisons** | 2–4 projects side by side, best-value highlights |
| **Settings** | Profile, password, team members |

Global search **Ctrl + K** — builders, projects, clients, flat numbers.

---

## Data freshness (golden rule)

Inventory दाखवताना प्रत्येक flat वर rंग दिसतो:

| रंग | अर्थ |
|---|---|
| 🟢 हिरवा | 3 दिवसांच्या आत verify केलेले |
| 🟡 पिवळा | 3–7 दिवस |
| 🟠 नारंगी | 7–15 दिवस |
| 🔴 लाल | 15+ दिवस — **client ला सांगण्याआधी verify करा** |

Inventory save केल्यावर "Last Verified" आपोआप आजची तारीख-वेळ होते.
