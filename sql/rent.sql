-- ============================================================
-- RE360 — Rent module (separate from the sale side: nothing here
-- touches builders / projects / inventory / clients).
--
-- Two registers, nothing more:
--   rent_flats   — flats owners have given us to rent out
--   rent_seekers — people looking to take a flat on rent
--
-- Import once on top of schema.sql.
-- ============================================================
SET NAMES utf8mb4;

-- ---------- Flats given for rent ----------
DROP TABLE IF EXISTS rent_flats;
CREATE TABLE rent_flats (
    id                INT AUTO_INCREMENT PRIMARY KEY,

    -- where the flat is
    building_name     VARCHAR(180) NOT NULL,
    flat_no           VARCHAR(40),
    wing              VARCHAR(30),
    floor             VARCHAR(20),
    address           VARCHAR(255),
    location          VARCHAR(100),          -- Panvel, Kharghar, Kamothe ...
    sector            VARCHAR(60),
    building_location VARCHAR(255),          -- landmark / exact spot in the area

    -- what it is (kept short: without these the list cannot answer a phone call)
    config            VARCHAR(30),           -- 1 RK / 1 BHK / 2 BHK ...
    furnishing        ENUM('unfurnished','semi_furnished','fully_furnished') DEFAULT 'unfurnished',
    rent              INT DEFAULT 0,         -- per month, rupees
    deposit           INT DEFAULT 0,
    available_from    DATE NULL,

    -- owner
    owner_name        VARCHAR(120) NOT NULL,
    owner_mobile      VARCHAR(20),
    owner_alt_mobile  VARCHAR(20),
    owner_email       VARCHAR(150),

    owner_id          INT NULL,              -- rent_owners.id

    status            ENUM('available','rented','on_hold') NOT NULL DEFAULT 'available',
    notes             TEXT,

    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX (location), INDEX (status), INDEX (config), INDEX (building_name), INDEX (owner_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- People looking to rent ----------
DROP TABLE IF EXISTS rent_seekers;
CREATE TABLE rent_seekers (
    id                INT AUTO_INCREMENT PRIMARY KEY,

    name              VARCHAR(120) NOT NULL,
    mobile            VARCHAR(20),
    alt_mobile        VARCHAR(20),
    email             VARCHAR(150),
    occupation        VARCHAR(120),

    -- what they are looking for
    preferred_location VARCHAR(150),
    preferred_sector   VARCHAR(60),
    config             VARCHAR(30),
    furnishing         ENUM('any','unfurnished','semi_furnished','fully_furnished') DEFAULT 'any',
    budget_min         INT DEFAULT 0,        -- per month
    budget_max         INT DEFAULT 0,
    needed_from        DATE NULL,
    family_type        ENUM('family','bachelor','company','other') DEFAULT 'family',

    status            ENUM('searching','shown','finalised','dropped') NOT NULL DEFAULT 'searching',
    source            VARCHAR(80),
    notes             TEXT,

    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX (status), INDEX (preferred_location), INDEX (config)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Owners ----------
-- Owner details started out inline on rent_flats. Once one owner hands over
-- more than one flat that duplicates the phone number in several rows, so an
-- owner is now a record of its own and rent_flats points at it. The inline
-- columns stay filled as a fallback for rows created before this.
DROP TABLE IF EXISTS rent_owners;
CREATE TABLE rent_owners (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(120) NOT NULL,
    mobile      VARCHAR(20),
    alt_mobile  VARCHAR(20),
    email       VARCHAR(150),
    address     VARCHAR(255),
    notes       TEXT,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (name), INDEX (mobile)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Visits ----------
DROP TABLE IF EXISTS rent_visits;
CREATE TABLE rent_visits (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    flat_id     INT NULL,
    seeker_id   INT NULL,
    visit_date  DATETIME NULL,
    status      ENUM('scheduled','done','no_show','cancelled') NOT NULL DEFAULT 'scheduled',
    feedback    TEXT,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (flat_id), INDEX (seeker_id), INDEX (visit_date), INDEX (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Agreements ----------
-- A rent agreement is normally 11 months, and the renewal is repeat income,
-- so end_date is the column the whole module leans on.
DROP TABLE IF EXISTS rent_agreements;
CREATE TABLE rent_agreements (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    flat_id           INT NULL,
    seeker_id         INT NULL,
    owner_id          INT NULL,

    tenant_name       VARCHAR(120),        -- kept as written on the agreement
    tenant_mobile     VARCHAR(20),

    start_date        DATE NULL,
    end_date          DATE NULL,
    rent              INT DEFAULT 0,
    deposit           INT DEFAULT 0,
    maintenance       INT DEFAULT 0,
    escalation_pct    DECIMAL(5,2) DEFAULT 5.00,
    notice_period     VARCHAR(40),
    registered        TINYINT(1) DEFAULT 0,

    brokerage_amount  INT DEFAULT 0,
    brokerage_from    ENUM('tenant','owner','both','none') DEFAULT 'tenant',
    brokerage_received TINYINT(1) DEFAULT 0,
    brokerage_date    DATE NULL,

    status            ENUM('active','expired','renewed','terminated') NOT NULL DEFAULT 'active',
    renewed_from_id   INT NULL,
    notes             TEXT,

    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (flat_id), INDEX (end_date), INDEX (status), INDEX (brokerage_received)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
