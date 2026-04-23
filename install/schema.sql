-- ============================================
-- Depo & Stok Yönetim Sistemi — Veritabanı Şeması
-- SQLite 3 uyumlu
-- ============================================

PRAGMA journal_mode=WAL;
PRAGMA foreign_keys=ON;

-- Migration: Add location table if missing
CREATE TABLE IF NOT EXISTS locations (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    kat         TEXT NOT NULL,
    bolge       TEXT NOT NULL,
    raf         INTEGER NOT NULL,
    goz         INTEGER NOT NULL,
    kod         TEXT UNIQUE NOT NULL,
    kapasite    INTEGER DEFAULT 100,
    aktif       INTEGER DEFAULT 1,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(kat, bolge, raf, goz)
);

-- Ürünler
CREATE TABLE IF NOT EXISTS products (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    barcode     TEXT UNIQUE NOT NULL,
    name        TEXT NOT NULL,
    sku         TEXT,
    category    TEXT,
    unit        TEXT DEFAULT 'adet',
    min_stock   INTEGER DEFAULT 5,
    active      INTEGER DEFAULT 1,
    product_type TEXT DEFAULT 'hazir' CHECK(product_type IN ('hazir','imalat','parca')),
    location_id INTEGER,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (location_id) REFERENCES locations(id)
);

-- Stok Hareketleri
CREATE TABLE IF NOT EXISTS stock_movements (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    product_id  INTEGER NOT NULL,
    type        TEXT NOT NULL CHECK(type IN ('in','out','correction','transfer')),
    quantity    INTEGER NOT NULL,
    reference   TEXT,
    user_id     INTEGER,
    note        TEXT,
    location_id INTEGER,
    from_location_id INTEGER,
    to_location_id INTEGER,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (location_id) REFERENCES locations(id),
    FOREIGN KEY (from_location_id) REFERENCES locations(id),
    FOREIGN KEY (to_location_id) REFERENCES locations(id)
);

-- Siparişler
CREATE TABLE IF NOT EXISTS orders (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    order_no    TEXT UNIQUE NOT NULL,
    customer    TEXT,
    status      TEXT DEFAULT 'pending' CHECK(status IN ('pending','preparing','done','cancelled')),
    notes       TEXT,
    prepared_by INTEGER,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    done_at     DATETIME,
    FOREIGN KEY (prepared_by) REFERENCES users(id)
);

-- Sipariş Kalemleri
CREATE TABLE IF NOT EXISTS order_items (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    order_id    INTEGER NOT NULL,
    product_id  INTEGER NOT NULL,
    quantity    INTEGER NOT NULL,
    scanned     INTEGER DEFAULT 0,
    FOREIGN KEY (order_id)   REFERENCES orders(id),
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- Kullanıcılar
CREATE TABLE IF NOT EXISTS users (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    username    TEXT UNIQUE NOT NULL,
    password    TEXT NOT NULL,
    full_name   TEXT,
    role        TEXT DEFAULT 'depocu' CHECK(role IN ('admin','depocu','muhasebe')),
    active      INTEGER DEFAULT 1,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Reçete (Bill of Materials) - Mamul ürün hangi parçalardan oluşuyor
CREATE TABLE IF NOT EXISTS bom_items (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 mamul_id INTEGER NOT NULL,
 parca_id INTEGER NOT NULL,
 miktar INTEGER NOT NULL DEFAULT 1,
 created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (mamul_id) REFERENCES products(id),
 FOREIGN KEY (parca_id) REFERENCES products(id),
 UNIQUE(mamul_id, parca_id)
);

-- Üretim Emirleri
CREATE TABLE IF NOT EXISTS production_orders (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 mamul_id INTEGER NOT NULL,
 miktar INTEGER NOT NULL,
 status TEXT DEFAULT 'pending' CHECK(status IN ('pending','completed','cancelled')),
 notes TEXT,
 created_by INTEGER,
 created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
 completed_at DATETIME,
 FOREIGN KEY (mamul_id) REFERENCES products(id),
 FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Üretim Emri Detayları (kullanılan parçalar)
CREATE TABLE IF NOT EXISTS production_order_items (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  production_order_id INTEGER NOT NULL,
  parca_id INTEGER NOT NULL,
  miktar INTEGER NOT NULL,
  FOREIGN KEY (production_order_id) REFERENCES production_orders(id),
  FOREIGN KEY (parca_id) REFERENCES products(id)
);

-- Ürün-Lokasyon ilişkileri (bir ürün birden fazla gözde olabilir)
CREATE TABLE IF NOT EXISTS product_locations (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  product_id INTEGER NOT NULL,
  location_id INTEGER NOT NULL,
  quantity INTEGER DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (product_id) REFERENCES products(id),
  FOREIGN KEY (location_id) REFERENCES locations(id),
  UNIQUE(product_id, location_id)
);

-- İndeksler (performans)
CREATE INDEX IF NOT EXISTS idx_products_barcode ON products(barcode);
CREATE INDEX IF NOT EXISTS idx_products_type ON products(product_type);
CREATE INDEX IF NOT EXISTS idx_products_location ON products(location_id);
CREATE INDEX IF NOT EXISTS idx_movements_product ON stock_movements(product_id);
CREATE INDEX IF NOT EXISTS idx_movements_created ON stock_movements(created_at);
CREATE INDEX IF NOT EXISTS idx_movements_location ON stock_movements(location_id);
CREATE INDEX IF NOT EXISTS idx_orders_status ON orders(status);
CREATE INDEX IF NOT EXISTS idx_order_items_order ON order_items(order_id);
CREATE INDEX IF NOT EXISTS idx_bom_mamul ON bom_items(mamul_id);
CREATE INDEX IF NOT EXISTS idx_bom_parca ON bom_items(parca_id);
CREATE INDEX IF NOT EXISTS idx_production_mamul ON production_orders(mamul_id);
CREATE INDEX IF NOT EXISTS idx_locations_kod ON locations(kod);
CREATE INDEX IF NOT EXISTS idx_locations_kat ON locations(kat, bolge, raf);
CREATE INDEX IF NOT EXISTS idx_product_locations_product ON product_locations(product_id);
CREATE INDEX IF NOT EXISTS idx_product_locations_location ON product_locations(location_id);

-- Admin İşlem Logları
CREATE TABLE IF NOT EXISTS admin_logs (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id     INTEGER NOT NULL,
    action      TEXT NOT NULL,
    table_name  TEXT,
    record_id   INTEGER,
    old_value   TEXT,
    new_value   TEXT,
    ip_address  TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE INDEX IF NOT EXISTS idx_admin_logs_user ON admin_logs(user_id);
CREATE INDEX IF NOT EXISTS idx_admin_logs_table ON admin_logs(table_name, record_id);
CREATE INDEX IF NOT EXISTS idx_admin_logs_created ON admin_logs(created_at);

-- Örnek Kategoriler (opsiyonel)
-- INSERT INTO products (barcode,name,sku,category,unit,min_stock) VALUES ...
