-- MyInvoice.cz — Samostatný platební variabilní symbol na vydané faktuře
--
-- `invoices.varsymbol` je zároveň ČÍSLO DOKLADU (číselná řada). Některé platby
-- ale přicházejí pod cizím VS (např. výplata z marketplace Aukro pod jeho
-- payment id) — pak musí doklad dostat číslo z řady a VS zvlášť, aby seděl
-- bankovní výpis. `payment_variable_symbol` (jen číslice, max 10) má přednost
-- před `varsymbol` všude, kde jde o PLATBU: řádek Var. symbol + QR na PDF,
-- ISDOC VariableSymbol, Pohoda symVar, párování bankovního výpisu.
-- Číslo dokladu (hlavička PDF, e-maily, název souboru) zůstává `varsymbol`.
--
-- Idempotent přes MariaDB native `IF NOT EXISTS`.

SET NAMES utf8mb4;

ALTER TABLE invoices
  ADD COLUMN IF NOT EXISTS payment_variable_symbol VARCHAR(10) NULL AFTER varsymbol;

ALTER TABLE invoices
  ADD INDEX IF NOT EXISTS idx_invoices_supplier_payment_vs (supplier_id, payment_variable_symbol);
