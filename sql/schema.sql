-- East Renfrewshire Housing Metrics — app database schema
-- Auth (users / login_attempts / password_setup_tokens) lives in the shared
-- sor_management database, reached via AUTH_DB_* env vars. Nothing auth-related
-- is created here.

-- Identity is keyed on the landlord's name, not the regulator's
-- "Social Landlord ID" — that ID was reissued (a new UUID scheme) starting
-- in the 2020/2021 return, so every landlord has two different IDs across
-- the 2019/2020-2024/2025 window. Name is what stays stable across years.
CREATE TABLE IF NOT EXISTS landlords (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    social_landlord_id VARCHAR(64) NULL,
    landlord_type VARCHAR(50) NULL,
    settlement VARCHAR(100) NULL,
    national_operator VARCHAR(100) NULL,
    is_east_renfrewshire TINYINT(1) NOT NULL DEFAULT 0,
    UNIQUE KEY uq_landlord_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS imports (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    uploaded_by INT UNSIGNED NULL,
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    row_count INT UNSIGNED NOT NULL DEFAULT 0,
    financial_years VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS submissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    -- Nullable: when an import that only *revised* this row is later
    -- deleted, we detach it from that import (see delete_import()) rather
    -- than lose the row, since we don't keep full column-by-column history.
    -- Updated every time any import re-touches this landlord+year — "who
    -- last wrote this row", used to know whether a later import has since
    -- superseded it.
    import_id INT UNSIGNED NULL,
    -- Set once, at INSERT, and never updated — "who created this row from
    -- scratch". Deleting an import can only ever fully remove rows where
    -- first_import_id = that import; everything else was pre-existing and
    -- only had some of its values revised, which can't be losslessly undone.
    first_import_id INT UNSIGNED NULL,
    landlord_id INT UNSIGNED NOT NULL,
    financial_year VARCHAR(9) NOT NULL,
    UNIQUE KEY uq_landlord_year (landlord_id, financial_year),
    KEY idx_financial_year (financial_year),
    CONSTRAINT fk_submissions_import FOREIGN KEY (import_id) REFERENCES imports(id),
    CONSTRAINT fk_submissions_landlord FOREIGN KEY (landlord_id) REFERENCES landlords(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS indicator_values (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    submission_id INT UNSIGNED NOT NULL,
    column_name VARCHAR(255) NOT NULL,
    value_text TEXT NULL,
    value_numeric DECIMAL(14,4) NULL,
    UNIQUE KEY uq_submission_column (submission_id, column_name),
    KEY idx_column_name (column_name(191)),
    CONSTRAINT fk_indicator_values_submission FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS indicator_catalog (
    column_name VARCHAR(255) NOT NULL,
    short_label VARCHAR(255) NULL,
    category VARCHAR(100) NULL,
    unit ENUM('percent','days','hours','gbp','count','text') NOT NULL DEFAULT 'text',
    is_key TINYINT(1) NOT NULL DEFAULT 0,
    -- NULL = direction not known/applicable (e.g. absolute £ totals that
    -- aren't comparable across landlord sizes) — such indicators are left
    -- out of the alerts page rather than guessed at.
    higher_is_better TINYINT(1) NULL,
    PRIMARY KEY (column_name(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS change_events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    import_id INT UNSIGNED NOT NULL,
    landlord_id INT UNSIGNED NOT NULL,
    financial_year VARCHAR(9) NOT NULL,
    column_name VARCHAR(255) NOT NULL,
    change_type ENUM('new_year_data','revised_prior_year','new_landlord') NOT NULL,
    previous_value TEXT NULL,
    new_value TEXT NULL,
    pct_change DECIMAL(8,2) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_created_at (created_at),
    KEY idx_landlord_year (landlord_id, financial_year),
    CONSTRAINT fk_change_events_import FOREIGN KEY (import_id) REFERENCES imports(id),
    CONSTRAINT fk_change_events_landlord FOREIGN KEY (landlord_id) REFERENCES landlords(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
