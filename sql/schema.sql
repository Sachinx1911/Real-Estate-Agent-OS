-- ============================================================
-- RE360 — Real Estate Channel Partner Operating System
-- MySQL schema (import via phpMyAdmin on Hostinger, or mysql CLI)
-- ============================================================
-- Create the database first (skip on Hostinger if already created):
-- CREATE DATABASE IF NOT EXISTS re360 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE re360;

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- ---------- Users ----------
DROP TABLE IF EXISTS users;
CREATE TABLE users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(120) NOT NULL,
    role          ENUM('admin','channel_partner','sales') NOT NULL DEFAULT 'channel_partner',
    email         VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    mobile        VARCHAR(20),
    avatar        VARCHAR(255),
    is_online     TINYINT(1) NOT NULL DEFAULT 0,
    last_login    DATETIME NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Builders ----------
DROP TABLE IF EXISTS builders;
CREATE TABLE builders (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    name                VARCHAR(150) NOT NULL,
    company             VARCHAR(180),
    group_name          VARCHAR(150),
    established_year    SMALLINT,
    head_office         VARCHAR(255),
    office_location     VARCHAR(150),
    contact_person      VARCHAR(120),
    designation         VARCHAR(100),
    mobile              VARCHAR(20),
    whatsapp            VARCHAR(20),
    email               VARCHAR(150),
    website             VARCHAR(200),
    rera_entity         VARCHAR(150),
    gst_no              VARCHAR(30),
    cp_contact          VARCHAR(120),
    total_projects      INT DEFAULT 0,
    completed_projects  INT DEFAULT 0,
    ongoing_projects    INT DEFAULT 0,
    upcoming_projects   INT DEFAULT 0,
    delivered_projects  INT DEFAULT 0,
    years_in_business   SMALLINT,
    major_locations     VARCHAR(255),
    reputation_note     TEXT,
    construction_quality VARCHAR(100),
    delivery_record     VARCHAR(100),
    logo                VARCHAR(255),
    -- reliability scores (0-10)
    score_construction  DECIMAL(3,1) DEFAULT 0,
    score_delivery      DECIMAL(3,1) DEFAULT 0,
    score_location      DECIMAL(3,1) DEFAULT 0,
    score_pricing       DECIMAL(3,1) DEFAULT 0,
    score_reputation    DECIMAL(3,1) DEFAULT 0,
    score_documentation DECIMAL(3,1) DEFAULT 0,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (name), INDEX (office_location)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Projects ----------
DROP TABLE IF EXISTS projects;
CREATE TABLE projects (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    builder_id          INT NOT NULL,
    name                VARCHAR(180) NOT NULL,
    type                ENUM('residential','commercial','mixed') NOT NULL DEFAULT 'residential',
    status              ENUM('new_launch','under_construction','ready','upcoming','on_hold') NOT NULL DEFAULT 'under_construction',
    address             VARCHAR(255),
    city                VARCHAR(100) DEFAULT 'Navi Mumbai',
    node                VARCHAR(100),           -- e.g. Panvel, Kharghar
    sector              VARCHAR(60),
    micro_market        VARCHAR(120),
    pincode             VARCHAR(10),
    latitude            DECIMAL(10,7),
    longitude           DECIMAL(10,7),
    maharera_no         VARCHAR(60),
    rera_link           VARCHAR(255),
    rera_reg_date       DATE NULL,
    rera_verified       TINYINT(1) DEFAULT 0,
    proposed_completion DATE NULL,
    possession_label    VARCHAR(60),            -- e.g. "Dec 2027", "Ready"
    current_status      VARCHAR(120),
    total_towers        INT DEFAULT 0,
    total_units         INT DEFAULT 0,
    land_parcel         VARCHAR(60),            -- e.g. "5 Acres"
    project_area        VARCHAR(60),            -- e.g. "2.75 Acres"
    launch_date         DATE NULL,
    oc_status           VARCHAR(60),
    cc_status           VARCHAR(60),
    delay_history       TEXT,
    price_min           BIGINT DEFAULT 0,       -- rupees
    price_max           BIGINT DEFAULT 0,
    hero_image          VARCHAR(255),
    brochure_file       VARCHAR(255),
    description         TEXT,
    is_featured         TINYINT(1) DEFAULT 0,
    -- sales intelligence
    best_for            VARCHAR(255),           -- e.g. "First-time buyer, Family"
    budget_band         VARCHAR(60),            -- e.g. "₹75L–95L"
    strengths           TEXT,                   -- newline / JSON list
    weaknesses          TEXT,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (builder_id), INDEX (node), INDEX (status), INDEX (is_featured),
    CONSTRAINT fk_proj_builder FOREIGN KEY (builder_id) REFERENCES builders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Project configurations (BHK types) ----------
DROP TABLE IF EXISTS project_configurations;
CREATE TABLE project_configurations (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    project_id    INT NOT NULL,
    config        VARCHAR(30) NOT NULL,         -- '2 BHK'
    carpet_area   INT,                          -- sq.ft.
    balcony_area  INT,
    builtup_area  INT,
    floor_range   VARCHAR(40),
    facing        VARCHAR(40),
    view_desc     VARCHAR(80),
    unit_count    INT DEFAULT 0,
    base_price    BIGINT DEFAULT 0,
    layout_image  VARCHAR(255),
    INDEX (project_id), INDEX (config),
    CONSTRAINT fk_cfg_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Towers ----------
DROP TABLE IF EXISTS towers;
CREATE TABLE towers (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    project_id      INT NOT NULL,
    name            VARCHAR(60) NOT NULL,       -- 'A'
    floors          INT DEFAULT 0,
    units_per_floor INT DEFAULT 0,
    total_units     INT DEFAULT 0,
    lifts           INT DEFAULT 0,
    parking         VARCHAR(60),
    possession      VARCHAR(60),
    INDEX (project_id),
    CONSTRAINT fk_tower_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Inventory (flat-level — critical) ----------
DROP TABLE IF EXISTS inventory;
CREATE TABLE inventory (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    project_id      INT NOT NULL,
    tower           VARCHAR(60),
    floor           INT,
    flat_no         VARCHAR(30) NOT NULL,       -- 'A-501'
    config          VARCHAR(30),                -- '2 BHK'
    carpet          INT,                        -- sq.ft.
    facing          VARCHAR(40),
    view_desc       VARCHAR(80),
    status          ENUM('available','hold','token','booked','agreement','registered','sold','cancelled','blocked')
                    NOT NULL DEFAULT 'available',
    price           BIGINT DEFAULT 0,           -- rupees
    last_verified_at DATETIME NULL,
    verified_by     INT NULL,                   -- users.id
    source          VARCHAR(80),                -- 'Sales Manager', 'CP portal'
    confidence      ENUM('high','medium','low') DEFAULT 'medium',
    notes           VARCHAR(255),
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (project_id), INDEX (status), INDEX (config), INDEX (tower),
    CONSTRAINT fk_inv_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Pricing / charges ----------
DROP TABLE IF EXISTS pricing;
CREATE TABLE pricing (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    project_id     INT NOT NULL,
    config         VARCHAR(30),
    base_price     BIGINT DEFAULT 0,
    floor_rise     BIGINT DEFAULT 0,
    premium        BIGINT DEFAULT 0,
    parking_charge BIGINT DEFAULT 0,
    club_charge    BIGINT DEFAULT 0,
    infra_charge   BIGINT DEFAULT 0,
    dev_charge     BIGINT DEFAULT 0,
    gst_pct        DECIMAL(5,2) DEFAULT 5.00,
    stamp_duty_pct DECIMAL(5,2) DEFAULT 6.00,
    registration   BIGINT DEFAULT 0,
    other_charges  BIGINT DEFAULT 0,
    INDEX (project_id),
    CONSTRAINT fk_price_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Payment plans ----------
DROP TABLE IF EXISTS payment_plans;
CREATE TABLE payment_plans (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    project_id  INT NOT NULL,
    plan_name   VARCHAR(120) NOT NULL,
    description TEXT,
    milestones  TEXT,                            -- JSON: [{"label":"Booking","pct":10}, ...]
    is_default  TINYINT(1) DEFAULT 0,
    INDEX (project_id),
    CONSTRAINT fk_pp_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Offers ----------
DROP TABLE IF EXISTS offers;
CREATE TABLE offers (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    project_id       INT NOT NULL,
    type             VARCHAR(60),               -- festive/spot/investor/cash/floor/parking/stamp/gst/furniture/rental/assured
    details          TEXT,
    official_or_verbal ENUM('official','verbal') DEFAULT 'official',
    valid_till       DATE NULL,
    is_active        TINYINT(1) DEFAULT 1,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (project_id), INDEX (is_active),
    CONSTRAINT fk_offer_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Amenities ----------
DROP TABLE IF EXISTS amenities;
CREATE TABLE amenities (
    id       INT AUTO_INCREMENT PRIMARY KEY,
    name     VARCHAR(100) NOT NULL,
    category ENUM('lifestyle','kids','fitness','sports','security','senior','convenience') DEFAULT 'lifestyle',
    icon     VARCHAR(40)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS project_amenities;
CREATE TABLE project_amenities (
    project_id  INT NOT NULL,
    amenity_id  INT NOT NULL,
    PRIMARY KEY (project_id, amenity_id),
    CONSTRAINT fk_pa_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    CONSTRAINT fk_pa_amenity FOREIGN KEY (amenity_id) REFERENCES amenities(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Parking ----------
DROP TABLE IF EXISTS parking;
CREATE TABLE parking (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    project_id  INT NOT NULL,
    type        VARCHAR(40),                    -- open/covered/podium/basement/mechanical
    count       INT DEFAULT 0,
    charge      BIGINT DEFAULT 0,
    ev_charging TINYINT(1) DEFAULT 0,
    INDEX (project_id),
    CONSTRAINT fk_park_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Legal docs ----------
DROP TABLE IF EXISTS legal_docs;
CREATE TABLE legal_docs (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    project_id  INT NOT NULL,
    doc_type    VARCHAR(60),                    -- maharera/title/cc/oc/approved_plan/na_order/bank_approval/agreement/society
    status      ENUM('verified','not_verified','pending') DEFAULT 'pending',
    file        VARCHAR(255),
    note        VARCHAR(255),
    INDEX (project_id),
    CONSTRAINT fk_legal_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Banks (home loan) ----------
DROP TABLE IF EXISTS banks;
CREATE TABLE banks (
    id   INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    logo VARCHAR(255)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS project_banks;
CREATE TABLE project_banks (
    project_id       INT NOT NULL,
    bank_id          INT NOT NULL,
    max_loan         BIGINT DEFAULT 0,
    special_scheme   VARCHAR(150),
    interest_subsidy VARCHAR(100),
    pre_approved     TINYINT(1) DEFAULT 0,
    PRIMARY KEY (project_id, bank_id),
    CONSTRAINT fk_pb_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    CONSTRAINT fk_pb_bank FOREIGN KEY (bank_id) REFERENCES banks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- CP details (per builder) ----------
DROP TABLE IF EXISTS cp_details;
CREATE TABLE cp_details (
    id                     INT AUTO_INCREMENT PRIMARY KEY,
    builder_id             INT NOT NULL,
    cp_registration_required TINYINT(1) DEFAULT 1,
    cp_code                VARCHAR(60),
    registration_process   TEXT,
    cp_contact             VARCHAR(120),
    commission_pct         DECIMAL(5,2) DEFAULT 2.00,
    commission_basis       VARCHAR(120),         -- 'agreement value'
    payout_stage           VARCHAR(120),
    payout_timeline        VARCHAR(120),
    gst_req                TINYINT(1) DEFAULT 1,
    tds                    VARCHAR(40),
    lead_reg_process       TEXT,
    lead_validity_days     INT DEFAULT 30,
    duplicate_rules        TEXT,
    site_visit_process     TEXT,
    cancellation_rules     TEXT,
    INDEX (builder_id),
    CONSTRAINT fk_cp_builder FOREIGN KEY (builder_id) REFERENCES builders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Clients ----------
DROP TABLE IF EXISTS clients;
CREATE TABLE clients (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(120) NOT NULL,
    mobile      VARCHAR(20),
    email       VARCHAR(150),
    location    VARCHAR(120),
    profession  VARCHAR(120),
    purpose     ENUM('self','investment','rental','parents','second_home') DEFAULT 'self',
    assigned_to INT NULL,
    status      ENUM('new','contacted','site_visit','negotiation','booked','lost') DEFAULT 'new',
    source      VARCHAR(80),
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (status), INDEX (assigned_to)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Client requirements ----------
DROP TABLE IF EXISTS client_requirements;
CREATE TABLE client_requirements (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    client_id               INT NOT NULL,
    preferred_location      VARCHAR(150),
    alt_location            VARCHAR(150),
    bhk                     VARCHAR(30),
    min_carpet              INT DEFAULT 0,
    agreement_budget        BIGINT DEFAULT 0,
    all_in_budget           BIGINT DEFAULT 0,
    own_contribution        BIGINT DEFAULT 0,
    loan_amount             BIGINT DEFAULT 0,
    loan_required           TINYINT(1) DEFAULT 1,
    preferred_floor         VARCHAR(40),
    facing                  VARCHAR(40),
    possession_within_months INT DEFAULT 24,
    parking                 VARCHAR(40),
    amenities_pref          VARCHAR(255),
    builder_pref            VARCHAR(150),
    ready_or_uc             ENUM('any','ready','under_construction') DEFAULT 'any',
    INDEX (client_id),
    CONSTRAINT fk_req_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Site visits ----------
DROP TABLE IF EXISTS site_visits;
CREATE TABLE site_visits (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    client_id   INT NOT NULL,
    project_id  INT NULL,
    visit_date  DATETIME NULL,
    status      ENUM('scheduled','done','no_show','cancelled') DEFAULT 'scheduled',
    notes       TEXT,
    done_by     INT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (client_id), INDEX (project_id), INDEX (visit_date),
    CONSTRAINT fk_sv_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Bookings ----------
DROP TABLE IF EXISTS bookings;
CREATE TABLE bookings (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    client_id    INT NOT NULL,
    project_id   INT NOT NULL,
    flat_id      INT NULL,
    value        BIGINT DEFAULT 0,
    stage        ENUM('token','booked','agreement','registered') DEFAULT 'token',
    booking_date DATE NULL,
    notes        TEXT,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (client_id), INDEX (project_id), INDEX (booking_date),
    CONSTRAINT fk_bk_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
    CONSTRAINT fk_bk_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Tasks / follow-ups ----------
DROP TABLE IF EXISTS tasks;
CREATE TABLE tasks (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    title        VARCHAR(200) NOT NULL,
    type         ENUM('followup','document','callback','visit','other') DEFAULT 'followup',
    related_type VARCHAR(40),                    -- 'client','project','builder'
    related_id   INT NULL,
    subtitle     VARCHAR(200),
    due_at       DATETIME NULL,
    priority     ENUM('high','medium','low') DEFAULT 'medium',
    status       ENUM('open','done') DEFAULT 'open',
    assigned_to  INT NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (status), INDEX (due_at), INDEX (priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Documents ----------
DROP TABLE IF EXISTS documents;
CREATE TABLE documents (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    entity_type VARCHAR(40),                     -- 'project','builder','client'
    entity_id   INT NULL,
    doc_name    VARCHAR(200),
    doc_type    VARCHAR(60),                     -- brochure/floor_plan/price_sheet/rera/agreement
    file_path   VARCHAR(255),
    uploaded_by INT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Activity log (Recent Updates feed) ----------
DROP TABLE IF EXISTS activity_log;
CREATE TABLE activity_log (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NULL,
    action      VARCHAR(60),                     -- 'inventory_updated','offer_added', ...
    entity_type VARCHAR(40),
    entity_id   INT NULL,
    message     VARCHAR(255),
    icon        VARCHAR(40),                     -- css icon key
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Settings ----------
DROP TABLE IF EXISTS settings;
CREATE TABLE settings (
    skey   VARCHAR(80) PRIMARY KEY,
    svalue TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
