-- Uzun görsel URL'leri (Google vb.) için sütunu genişletir. Bir kez çalıştırın.
USE company_portal;
ALTER TABLE news MODIFY image_url TEXT NULL;
