-- ============================================================
-- 1. Categories
-- ============================================================
-- Stammdaten für Part-Kategorien.
-- Wird später z.B. für Dropdowns verwendet.
-- ============================================================

CREATE TABLE plm_categories (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    name        TEXT NOT NULL UNIQUE,
    description TEXT,
    sort_order  INTEGER NOT NULL DEFAULT 0,
    active      INTEGER NOT NULL DEFAULT 1
);

CREATE INDEX idx_plm_categories_active_order ON plm_categories(active, sort_order);


-- ============================================================
-- 2. Status
-- ============================================================
-- Stammdaten für Part-Version-Status.
-- Wird später z.B. für Dropdowns verwendet.
-- ============================================================

CREATE TABLE plm_status (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    name        TEXT NOT NULL UNIQUE,
    description TEXT,
    sort_order  INTEGER NOT NULL DEFAULT 0,
    active      INTEGER NOT NULL DEFAULT 1
);

CREATE INDEX idx_plm_status_active_order ON plm_status(active, sort_order);

-- ============================================================
-- 3a. Company Type
-- ============================================================
-- Stammdaten für Company Type
-- Wird später z.B. für Dropdowns verwendet.
-- ============================================================

CREATE TABLE plm_company_types (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    name        TEXT NOT NULL UNIQUE,
    description TEXT,
    sort_order  INTEGER NOT NULL DEFAULT 0,
    active      INTEGER NOT NULL DEFAULT 1
);

CREATE INDEX idx_plm_company_type_active_order ON plm_company_types(active, sort_order);


-- ============================================================
-- 3b. Companies
-- ============================================================

CREATE TABLE plm_companies (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    name        TEXT NOT NULL UNIQUE,
    type_id     INTEGER, 
    description TEXT,

    FOREIGN KEY (type_id)
        REFERENCES plm_company_types(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
);


-- ============================================================
-- 4. Parts
-- ============================================================

CREATE TABLE plm_part (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    ipn         TEXT NOT NULL UNIQUE, 
    description TEXT,
    category_id INTEGER,
    
    FOREIGN KEY (category_id) 
        REFERENCES plm_categories(id) 
        ON UPDATE CASCADE 
        ON DELETE SET NULL
);


CREATE INDEX idx_plm_part_category ON plm_part(category_id);


-- ============================================================
-- 5. Part Versions
-- ============================================================

CREATE TABLE plm_part_version (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    part_id     INTEGER NOT NULL,
    major       INTEGER NOT NULL,
    revision    INTEGER NOT NULL,
    version_id  TEXT NOT NULL UNIQUE,
    status_id   INTEGER,

    FOREIGN KEY (part_id)
        REFERENCES plm_part(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    FOREIGN KEY (status_id)
        REFERENCES plm_status(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
);


CREATE INDEX idx_plm_part_version_part 
    ON plm_part_version(part_id);

CREATE INDEX idx_plm_part_version_status 
    ON plm_part_version(status_id);


-- ============================================================
-- 6. Manufacturer Parts
-- ============================================================

CREATE TABLE plm_manufacturer_part (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,

    part_ipn_id     INTEGER NOT NULL,
    manufacturer_id INTEGER NOT NULL,
    mpn             TEXT NOT NULL UNIQUE,
    description     TEXT,

    FOREIGN KEY (part_ipn_id)
        REFERENCES plm_part(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    FOREIGN KEY (manufacturer_id)
        REFERENCES plm_companies(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
);


CREATE INDEX idx_plm_manufacturer_part_part ON plm_manufacturer_part(part_ipn_id);
CREATE INDEX idx_plm_manufacturer_part_manufacturer ON plm_manufacturer_part(manufacturer_id);


-- ============================================================
-- 7. Supplier Parts
-- ============================================================

CREATE TABLE plm_supplier_part (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    mpn_id      INTEGER NOT NULL,
    supplier_id INTEGER NOT NULL,
    spn         TEXT NOT NULL,
    description TEXT,

    FOREIGN KEY (mpn_id)
        REFERENCES plm_manufacturer_part(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    FOREIGN KEY (supplier_id)
        REFERENCES plm_companies(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    UNIQUE (supplier_id, spn)
);


CREATE INDEX idx_plm_supplier_part_mpn ON plm_supplier_part(mpn_id);
CREATE INDEX idx_plm_supplier_part_supplier ON plm_supplier_part(supplier_id);


-- ============================================================
-- 8. Part Substitutes
-- ============================================================

CREATE TABLE plm_part_substitutes (
    id                INTEGER PRIMARY KEY AUTOINCREMENT,
    parent_ipn_id     INTEGER NOT NULL,
    substitute_ipn_id INTEGER NOT NULL,

    FOREIGN KEY (parent_ipn_id) 
        REFERENCES plm_part(id) 
        ON UPDATE CASCADE 
        ON DELETE CASCADE,

    FOREIGN KEY (substitute_ipn_id)
        REFERENCES plm_part(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    UNIQUE (parent_ipn_id, substitute_ipn_id),

    CHECK (parent_ipn_id <> substitute_ipn_id)
);


CREATE INDEX idx_plm_part_substitutes_parent ON plm_part_substitutes(parent_ipn_id);
CREATE INDEX idx_plm_part_substitutes_substitute ON plm_part_substitutes(substitute_ipn_id);


-- ============================================================
-- 9. Products
-- ============================================================

CREATE TABLE plm_product (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    product_id  TEXT NOT NULL UNIQUE,
    description TEXT
);


-- ============================================================
-- 10. Product Variants
-- ============================================================

CREATE TABLE plm_product_variant (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    variant_id  TEXT NOT NULL UNIQUE,
    product_id  INTEGER NOT NULL,
    description TEXT,

    FOREIGN KEY (product_id)
        REFERENCES plm_product(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
);


CREATE INDEX idx_plm_product_variant_product 
    ON plm_product_variant(product_id);


-- ============================================================
-- 11. Product Variant Items
-- ============================================================
-- Verknüpft eine konkrete Produktvariante mit
-- einer konkreten Part-Version.
--
-- Beispiel:
--
-- MC1210F-BK
--     -> MC1210F-BODY-A
--     -> MC1210F-BOTTOM-A
--     -> MC1210F-COVER-A
--
-- ============================================================

CREATE TABLE plm_product_variant_item (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    variant_id  INTEGER NOT NULL,
    version_id  INTEGER NOT NULL,
    quantity    REAL NOT NULL DEFAULT 1,

    FOREIGN KEY (variant_id)
        REFERENCES plm_product_variant(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    FOREIGN KEY (version_id)
        REFERENCES plm_part_version(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    UNIQUE (variant_id, version_id),

    CHECK (quantity > 0)
);


CREATE INDEX idx_plm_product_variant_item_variant 
    ON plm_product_variant_item(variant_id);

CREATE INDEX idx_plm_product_variant_item_version 
    ON plm_product_variant_item(version_id);

-- ============================================================
-- 12. Part Items
-- ============================================================
-- Die eigentliche BOM-Beziehung.
--
-- Eine Part-Version kann aus beliebig vielen anderen
-- Part-Versionen bestehen.
--
-- parent_version_id
--        |
--        +----> child_version_id
--
-- ============================================================

CREATE TABLE plm_part_item (
    id                INTEGER PRIMARY KEY AUTOINCREMENT,

    parent_version_id INTEGER NOT NULL,
    child_version_id  INTEGER NOT NULL,

    quantity          REAL NOT NULL DEFAULT 1,
    designators       TEXT,

    FOREIGN KEY (parent_version_id)
        REFERENCES plm_part_version(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    FOREIGN KEY (child_version_id)
        REFERENCES plm_part_version(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CHECK (quantity > 0)
);


CREATE INDEX idx_plm_part_item_parent
    ON plm_part_item(parent_version_id);

CREATE INDEX idx_plm_part_item_child
    ON plm_part_item(child_version_id);


-- ============================================================
-- 13. Part Item Variants
-- ============================================================
-- Ersetzt den Multi-Value Lookup "variants" aus Struct.
--
-- Keine Einträge:
--     -> Part Item gilt für ALLE Produktvarianten.
--
-- Einträge vorhanden:
--     -> Part Item gilt nur für die angegebenen Varianten.
--
-- ============================================================

CREATE TABLE plm_part_item_variant (

    part_item_id INTEGER NOT NULL,
    variant_id   INTEGER NOT NULL,

    PRIMARY KEY (part_item_id, variant_id),

    FOREIGN KEY (part_item_id)
        REFERENCES plm_part_item(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    FOREIGN KEY (variant_id)
        REFERENCES plm_product_variant(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
);


CREATE INDEX idx_plm_part_item_variant_variant
    ON plm_part_item_variant(variant_id);
