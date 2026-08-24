-- MyInvoice.cz — Per-supplier vzhled PDF dokladu („theme")
--
-- `default` = stávající vzhled (fialové/akcentové plochy, podbarvené bloky).
-- `simple`  = jednobarevný „raw" doklad: černý text, tenké linky, žádné
--             podbarvení; logo a název zůstávají (gate email_branding_enabled).
--             Akcentní barva (email_accent_color) se v tomhle režimu na PDF
--             neuplatní — e-mailů se theme netýká.
--
-- Idempotent přes MariaDB native `IF NOT EXISTS`.

SET NAMES utf8mb4;

ALTER TABLE supplier
  ADD COLUMN IF NOT EXISTS pdf_theme VARCHAR(20) NOT NULL DEFAULT 'default' AFTER pdf_logo_show_name;
