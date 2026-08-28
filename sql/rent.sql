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

    status            ENUM('available','rented','on_hold') NOT NULL DEFAULT 'available',
    notes             TEXT,

    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX (location), INDEX (status), INDEX (config), INDEX (building_name)
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
