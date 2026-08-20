# RE360 — Hostinger वर Deploy करण्याची संपूर्ण Guide

PHP + MySQL. Node नाही, Composer नाही, build step नाही — files upload करा आणि चालवा.

**एकूण वेळ:** साधारण २०–३० मिनिटं.

---

## तयारी — काय काय लागेल

| गोष्ट | कुठे मिळेल |
|---|---|
| Hostinger hosting plan | Premium / Business (कोणताही shared plan चालेल) |
| Domain | Hostinger कडून, किंवा बाहेरचा (GoDaddy/Namecheap) — nameserver बदलावे लागतील |
| PHP 8.1 किंवा वरचं | hPanel मध्ये सेट करता येतं (Step 1) |
| MySQL database | hPanel → Databases |

---

## Step 1 — PHP version सेट करा

**hPanel → Websites → तुमची site → Advanced → PHP Configuration**

- **PHP version** tab: **8.1 किंवा 8.2** निवडा (8.0 च्या खाली जाऊ नका)
- **PHP extensions** tab: हे tick असल्याची खात्री करा —
  `pdo_mysql`, `mbstring`, `json`, `fileinfo`, `openssl`, `gd`, `zip`
- **Save** दाबा

> ⚠️ PHP 7.x वर app चालणार नाही — `match()`, typed properties वगैरे नवीन syntax वापरलं आहे.

---

## Step 2 — Database तयार करा

**hPanel → Databases → MySQL Databases**

1. **Create a New Database** मध्ये भरा:
   - Database name: `re360`  → तयार होईल `u123456789_re360` अशा नावाने
   - Username: `re360` → तयार होईल `u123456789_re360`
   - Password: **मजबूत password** ठेवा (Generate बटण वापरा)
2. **Create** दाबा
3. हे चारही लिहून ठेवा — पुढच्या step ला लागतील:

```
Database host : localhost
Database name : u123456789_re360
Username      : u123456789_re360
Password      : ••••••••••••
```

> Hostinger वर host जवळपास नेहमी `localhost` असतो. जर remote MySQL वापरत असाल तर hPanel मध्ये दिलेला hostname वापरा.

---

## Step 3 — Files upload करा

### पर्याय A — File Manager (सोपा)

1. या project चा **ZIP** बनवा (सर्व files + folders, top-level folder न ठेवता)
2. **hPanel → Files → File Manager** → `public_html/` उघडा
3. `public_html/` मध्ये आधीपासून असलेली `default.php` / `index.html` **delete** करा
4. वरच्या Upload बटणाने ZIP upload करा
5. ZIP वर right-click → **Extract**
6. ZIP file नंतर delete करा

### पर्याय B — Git (recommended, updates सोपे होतात)

**hPanel → Advanced → GIT**

- Repository: `https://github.com/Sachinx1911/Real-Estate-Agent-OS.git`
- Branch: `main`
- Directory: रिकामं ठेवा (म्हणजे `public_html` मध्ये जाईल)
- **Create** दाबा → नंतर update हवं असेल तेव्हा फक्त **Pull** दाबा

### पर्याय C — FTP (FileZilla)

hPanel → Files → **FTP Accounts** मधून host/user/password घ्या → `public_html/` मध्ये सगळं टाका.

### Upload झाल्यावर structure अशी दिसली पाहिजे

```
public_html/
├── index.php
├── login.php
├── logout.php
├── setup.php          ← नंतर delete करायची
├── 403.php  404.php  500.php
├── .htaccess          ← hidden file — File Manager मध्ये "Show hidden files" चालू करा
├── .user.ini          ← hidden file
├── robots.txt
├── site.webmanifest
├── api/  assets/  config/  includes/  pages/  sql/  tools/
├── logs/              ← permission 755 हवी
└── uploads/           ← permission 755 हवी
```

> ⚠️ **File Manager मध्ये Settings → "Show hidden files" चालू करा**, नाहीतर `.htaccess` आणि `.user.ini` दिसणार नाहीत आणि upload झालं की नाही कळणार नाही.

---

## Step 4 — Database credentials भरा

`config/db.sample.php` ही **template** आहे. ती copy करून `config/db.local.php` बनवा:

1. File Manager मध्ये `config/` उघडा
2. `db.sample.php` वर right-click → **Copy** → नाव `db.local.php` ठेवा
3. `db.local.php` उघडून Step 2 मधली माहिती भरा:

```php
$DB_HOST    = 'localhost';
$DB_PORT    = 3306;
$DB_NAME    = 'u123456789_re360';
$DB_USER    = 'u123456789_re360';
$DB_PASS    = 'तुमचा-password';
$DB_CHARSET = 'utf8mb4';
```

4. **Save** करा

> **`db.local.php` का?** `config/db.php` मध्ये direct password टाकला तर git pull केल्यावर तो पुसला जातो. `db.local.php` गिट-मध्ये कधीच जात नाही, त्यामुळे update केल्यावरही तुमचा password टिकतो.

---

## Step 5 — Production mode चालू करा

`config/config.php` उघडा, वरची ओळ बदला:

```php
define('RE360_ENV', 'production');   // 'development' चं 'production' करा
```

यामुळे PHP errors visitor ला दिसणार नाहीत — ते `logs/php-error.log` मध्ये जातील.

---

## Step 6 — Folder permissions सेट करा

File Manager मध्ये right-click → **Permissions**:

| Folder | Permission |
|---|---|
| `uploads/` | `755` |
| `logs/` | `755` |
| `config/` | `755` |
| सर्व `.php` files | `644` |

---

## Step 7 — SSL (HTTPS) चालू करा

**hPanel → Security → SSL**

- तुमचं domain निवडा → **Install SSL** (Let's Encrypt, मोफत)
- ५–१० मिनिटं लागतात
- झाल्यावर **Force HTTPS** चालू करा

> `.htaccess` मध्ये http → https redirect आधीच लिहिलेला आहे. **SSL install होण्याआधी site उघडली तर redirect loop येऊ शकतो** — म्हणून आधी SSL, मग site उघडा.

---

## Step 8 — Health check चालवा (setup च्या आधी)

Browser मध्ये उघडा:

```
https://yourdomain.com/tools/healthcheck.php
```

ही page तपासते —
- PHP version आणि extensions
- Database connection
- `uploads/`, `logs/`, `config/` लिहिता येतंय का
- HTTPS चालू आहे का
- Production mode आहे का

🔴 लाल ठिपके दिसले तर ते **आधी दुरुस्त करा**, मग पुढे जा. 🟡 पिवळे warnings चालतील.

---

## Step 9 — Setup चालवा

```
https://yourdomain.com/setup.php
```

- आपोआप सर्व tables बनतील
- तुमचं **Admin account** तयार होईल (नाव, email, password — किमान ६ अक्षरं)
- **"Load demo data"** tick ठेवलं तर demo builders/projects/inventory भरेल
  (पहिल्यांदा app कसं दिसतं ते बघण्यासाठी उपयोगी — नंतर काढता येतं)

Setup यशस्वी झाल्यावर `config/installed.lock` नावाची file आपोआप तयार होते. त्यानंतर `setup.php` पुन्हा चालवता येत नाही — कोणी दुसरं admin account बनवू शकत नाही.

---

## Step 10 — setup.php delete करा ⚠️

**हे महत्त्वाचं आहे.** File Manager मध्ये `public_html/setup.php` **delete करा**.

(Lock file असल्यामुळे ती चालणार नाहीच, पण server वर ठेवण्यात अर्थ नाही.)

---

## Step 11 — Login करा

```
https://yourdomain.com/
```

Setup मध्ये टाकलेला email + password वापरा.

**Ctrl + K** दाबून global search — builders, projects, clients, flat numbers.

---

## Step 12 — शेवटची तपासणी

पुन्हा health check उघडा (आता admin login लागेल):

```
https://yourdomain.com/tools/healthcheck.php
```

सगळं 🟢 हिरवं दिसलं पाहिजे. मग हे तपासा:

- [ ] `https://yourdomain.com/config/db.local.php` → **403 दिसलं पाहिजे** (password उघडा दिसता कामा नये)
- [ ] `https://yourdomain.com/sql/schema.sql` → **403 दिसलं पाहिजे**
- [ ] `https://yourdomain.com/logs/php-error.log` → **403 दिसलं पाहिजे**
- [ ] `https://yourdomain.com/setup.php` → **404 दिसलं पाहिजे** (file delete केली आहे)
- [ ] `https://yourdomain.com/काहीतरी-चुकीचं` → सुंदर 404 page दिसलं पाहिजे
- [ ] `http://yourdomain.com` → आपोआप `https://` वर गेलं पाहिजे
- [ ] Documents module मध्ये एक PDF upload करून बघा

---

## Backup — दर आठवड्याला

**hPanel → Files → Backups**

- **Files backup**: automatic असतो, पण महिन्यातून एकदा manual download करा
- **Database backup**: hPanel → Databases → phpMyAdmin → तुमचं database → **Export** → **Go**
  (`.sql` file download होईल — ती सुरक्षित ठेवा)

> Inventory data हा या app चा सर्वात महत्त्वाचा भाग आहे. Database backup चुकवू नका.

---

## Password विसरलात तर

App मध्ये "Forgot password" email link नाही (SMTP लागेल). त्याऐवजी एक सुरक्षित emergency tool आहे:

1. File Manager मध्ये `config/` folder उघडा
2. **New File** → नाव `reset.allow` (रिकामी file, आत काहीही नको)
3. **३० मिनिटांच्या आत** उघडा: `https://yourdomain.com/tools/reset_password.php`
4. Email + नवीन password टाका
5. `reset.allow` आपोआप delete होते — tool पुन्हा lock होतं

`reset.allow` file नसेल तर हे page नेहमी **403** देतं. त्यामुळे बाहेरचा कोणी password बदलू शकत नाही.

---

## Code update करायचा असेल तर

**Git वापरत असाल** (Step 3, पर्याय B):

hPanel → Advanced → GIT → **Pull** दाबा. एवढंच.

`config/db.local.php`, `uploads/`, `logs/` यांना धक्का लागत नाही — ते `.gitignore` मध्ये आहेत.

**Manual upload करत असाल:** फक्त बदललेल्या files upload करा. `config/db.local.php` आणि `uploads/` कधीही overwrite करू नका.

---

## Demo data काढून टाकायचा असेल तर

phpMyAdmin → तुमचं database → **SQL** tab → हे paste करा:

```sql
DELETE FROM inventory; DELETE FROM project_configurations; DELETE FROM towers;
DELETE FROM bookings; DELETE FROM site_visits; DELETE FROM client_requirements;
DELETE FROM clients; DELETE FROM projects; DELETE FROM cp_details;
DELETE FROM builders; DELETE FROM tasks; DELETE FROM activity_log;
```

> ⚠️ `users` table ला हात लावू नका — तुमचं login जाईल.

---

## अडचणी आल्या तर (Troubleshooting)

| काय दिसतंय | कारण आणि उपाय |
|---|---|
| **500 Internal Server Error** | बहुतेक `.htaccess` किंवा PHP version. hPanel → PHP Configuration मध्ये 8.1 आहे का बघा. मग `logs/php-error.log` File Manager मधून उघडून खरी चूक बघा. |
| **"Service temporarily unavailable"** | Database credentials चुकीचे. `config/db.local.php` तपासा — विशेषतः database name मध्ये `u123456789_` prefix आहे का. |
| **पांढरी रिकामी screen** | PHP fatal error. `config/config.php` मध्ये तात्पुरतं `'development'` करा, error वाचा, दुरुस्त करा, परत `'production'` करा. |
| **Redirect loop (ERR_TOO_MANY_REDIRECTS)** | SSL अजून install झालेलं नाही. Step 7 पूर्ण करा. तातडीचं असेल तर `.htaccess` मधला "Force HTTPS" block तात्पुरता comment करा (`#` लावा). |
| **CSS लागत नाही / page फुटलेलं दिसतं** | `assets/` folder upload झालं नाही, किंवा permission चुकीची. `assets/css/re360.css` browser मध्ये direct उघडून बघा. |
| **Upload होत नाही** | `uploads/` ची permission `755` करा. File 10 MB पेक्षा मोठी असेल तर `.user.ini` मधली limit वाढवा (बदल लागू व्हायला ५ मिनिटं लागतात). |
| **"Session expired" वारंवार** | Browser cookies block करत असेल, किंवा http आणि https दोन्हीवर site उघडली जातेय. Force HTTPS चालू करा. |
| **"Too many failed attempts"** | ८ चुकीचे प्रयत्न झाले — १५ मिनिटं थांबा, किंवा File Manager मधून `logs/throttle/` मधल्या files delete करा. |
| **setup.php "Setup already completed" दाखवतंय** | हे बरोबर आहे. पुन्हा setup हवं असेल तर `config/installed.lock` delete करा. |
| **Git pull fail होतंय** | Server वर file बदलली गेली आहे. hPanel → GIT मधून repository delete करून पुन्हा जोडा (आधी `config/db.local.php` आणि `uploads/` download करून ठेवा). |

---

## Security — काय काय आधीच केलेलं आहे

| संरक्षण | कुठे |
|---|---|
| Password bcrypt ने hash | `includes/auth.php` |
| CSRF token सर्व forms वर | `includes/csrf.php` + `index.php` |
| Login brute-force थांबवणं (८ प्रयत्न → १५ मिनिटं lock) | `includes/auth.php` |
| Session fixation संरक्षण + ८ तासांनी auto logout | `config/config.php` |
| Cookie: HttpOnly, Secure, SameSite | `config/config.php` |
| SQL injection — सर्व queries prepared statements | `includes/helpers.php` |
| XSS — सर्व output `e()` ने escape | सर्वत्र |
| `config/`, `sql/`, `logs/` web वरून block | प्रत्येकात `.htaccess` |
| `uploads/` मध्ये PHP चालत नाही | `uploads/.htaccess` |
| Setup एकदाच चालतो | `config/installed.lock` |
| Security headers (nosniff, frame-options, referrer-policy) | `.htaccess` |
| Search engines block | `robots.txt` + `noindex` meta |

**HSTS** (सर्वात कडक HTTPS setting) `.htaccess` मध्ये comment करून ठेवलं आहे. HTTPS काही आठवडे व्यवस्थित चालल्यावर तो `#` काढा.

---

## Subfolder मध्ये टाकायचं असेल तर

`public_html/re360/` सारख्या subfolder मध्ये टाकत असाल, तर `.htaccess` मधले error pages बदला:

```apache
ErrorDocument 403 /re360/403.php
ErrorDocument 404 /re360/404.php
ErrorDocument 500 /re360/500.php
```

बाकी सगळं आपोआप जुळवून घेतं — app मधले सर्व links relative आहेत.
