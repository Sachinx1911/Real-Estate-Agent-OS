# RE360 — Real Estate Channel Partner Operating System

PHP + MySQL. No Node, no Composer, no build step — upload and run on Hostinger shared hosting.

---

## Hostinger वर कसे टाकायचे (deploy)

### 1. Database तयार करा
hPanel → **Databases → MySQL Databases**
- नवीन database बनवा (उदा. `u123456_re360`)
- User बनवा + password ठेवा
- **Database name, username, password, host** लिहून ठेवा

### 2. Files upload करा
hPanel → **File Manager** (किंवा FTP)
- या folder मधली **सर्व files** `public_html/` मध्ये upload करा
- (Folder structure तशीच ठेवा — `config/`, `includes/`, `pages/`, `api/`, `assets/`, `sql/`, `uploads/`)

### 3. Database credentials भरा
`config/db.php` उघडा आणि हे बदला:

```php
$DB_HOST = 'localhost';        // Hostinger वर सहसा localhost
$DB_NAME = 'u123456_re360';    // तुमचे database name
$DB_USER = 'u123456_sagar';    // तुमचे username
$DB_PASS = 'your-password';    // तुमचा password
```

आणि `config/config.php` मध्ये production mode करा:
```php
define('RE360_ENV', 'production');
```

### 4. Setup चालवा
Browser मध्ये उघडा: **`https://yourdomain.com/setup.php`**
- हे आपोआप सर्व tables बनवेल
- Admin account तयार करेल (नाव, email, password)
- "Load demo data" tick ठेवलं तर demo builders/projects/inventory भरेल (पहिल्यांदा बघण्यासाठी उपयोगी)

### 5. Setup file delete करा ⚠️
Setup झाल्यावर **`setup.php` delete करा** (security साठी महत्त्वाचे).

### 6. Login करा
`https://yourdomain.com/` → तुमचा email + password.

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
config/            db credentials + constants
includes/          auth, header, sidebar, footer, helpers, icons, crud
pages/             प्रत्येक module चा एक file
api/               AJAX endpoints (search, इ.)
assets/css|js      dark theme + front-end
sql/               schema.sql (tables) + seed.sql (demo data)
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
