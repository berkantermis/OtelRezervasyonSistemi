USE OtelRezervasyonSistemi;
GO

/* =========================================================
   INDEXLER
   - Zaten PK ve UNIQUE için otomatik index var
   - Burada sık sorgulanacak kolonlara ek indexler açıyoruz
   ========================================================= */

------------------------------------------------------------
-- 1) MUSTERILER
-- Sık aranan alanlar: TCKN, Pasaport, Email, Telefon
------------------------------------------------------------
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_Musteriler_TCKN' AND object_id = OBJECT_ID('dbo.Musteriler'))
    DROP INDEX IX_Musteriler_TCKN ON dbo.Musteriler;
CREATE NONCLUSTERED INDEX IX_Musteriler_TCKN 
ON dbo.Musteriler (TCKN);

IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_Musteriler_PasaportNo' AND object_id = OBJECT_ID('dbo.Musteriler'))
    DROP INDEX IX_Musteriler_PasaportNo ON dbo.Musteriler;
CREATE NONCLUSTERED INDEX IX_Musteriler_PasaportNo 
ON dbo.Musteriler (PasaportNo);

IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_Musteriler_Email' AND object_id = OBJECT_ID('dbo.Musteriler'))
    DROP INDEX IX_Musteriler_Email ON dbo.Musteriler;
CREATE NONCLUSTERED INDEX IX_Musteriler_Email 
ON dbo.Musteriler (Email);

IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_Musteriler_Telefon' AND object_id = OBJECT_ID('dbo.Musteriler'))
    DROP INDEX IX_Musteriler_Telefon ON dbo.Musteriler;
CREATE NONCLUSTERED INDEX IX_Musteriler_Telefon 
ON dbo.Musteriler (UlkeKodu, TelefonNo);


------------------------------------------------------------
-- 2) KULLANICILAR
-- Email zaten UNIQUE, PK var. Lookup için MusteriID / PersonelID indexleyelim.
------------------------------------------------------------
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_Kullanicilar_MusteriID' AND object_id = OBJECT_ID('dbo.Kullanicilar'))
    DROP INDEX IX_Kullanicilar_MusteriID ON dbo.Kullanicilar;
CREATE NONCLUSTERED INDEX IX_Kullanicilar_MusteriID
ON dbo.Kullanicilar (MusteriID);

IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_Kullanicilar_PersonelID' AND object_id = OBJECT_ID('dbo.Kullanicilar'))
    DROP INDEX IX_Kullanicilar_PersonelID ON dbo.Kullanicilar;
CREATE NONCLUSTERED INDEX IX_Kullanicilar_PersonelID
ON dbo.Kullanicilar (PersonelID);


------------------------------------------------------------
-- 3) REZERVASYONLAR
-- Sık sorgular: bir müşterinin rezervasyonları, oda doluluk, tarih aralığı
------------------------------------------------------------
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_Rezervasyonlar_MusteriID' AND object_id = OBJECT_ID('dbo.Rezervasyonlar'))
    DROP INDEX IX_Rezervasyonlar_MusteriID ON dbo.Rezervasyonlar;
CREATE NONCLUSTERED INDEX IX_Rezervasyonlar_MusteriID
ON dbo.Rezervasyonlar (MusteriID);

IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_Rezervasyonlar_Oda_Tarih' AND object_id = OBJECT_ID('dbo.Rezervasyonlar'))
    DROP INDEX IX_Rezervasyonlar_Oda_Tarih ON dbo.Rezervasyonlar;
CREATE NONCLUSTERED INDEX IX_Rezervasyonlar_Oda_Tarih
ON dbo.Rezervasyonlar (OdaID, GirisTarihi, CikisTarihi);

IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_Rezervasyonlar_Durum' AND object_id = OBJECT_ID('dbo.Rezervasyonlar'))
    DROP INDEX IX_Rezervasyonlar_Durum ON dbo.Rezervasyonlar;
CREATE NONCLUSTERED INDEX IX_Rezervasyonlar_Durum
ON dbo.Rezervasyonlar (RezervasyonDurum);


------------------------------------------------------------
-- 4) REZERVASYONKISILERI
-- Çoğunlukla RezervasyonID ile sorgulanacak
------------------------------------------------------------
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_RezervasyonKisileri_RezID' AND object_id = OBJECT_ID('dbo.RezervasyonKisileri'))
    DROP INDEX IX_RezervasyonKisileri_RezID ON dbo.RezervasyonKisileri;
CREATE NONCLUSTERED INDEX IX_RezervasyonKisileri_RezID
ON dbo.RezervasyonKisileri (RezervasyonID);


------------------------------------------------------------
-- 5) REZERVASYONHIZMETLERI
------------------------------------------------------------
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_RezHiz_RezID' AND object_id = OBJECT_ID('dbo.RezervasyonHizmetleri'))
    DROP INDEX IX_RezHiz_RezID ON dbo.RezervasyonHizmetleri;
CREATE NONCLUSTERED INDEX IX_RezHiz_RezID
ON dbo.RezervasyonHizmetleri (RezervasyonID);

IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_RezHiz_HizmetID' AND object_id = OBJECT_ID('dbo.RezervasyonHizmetleri'))
    DROP INDEX IX_RezHiz_HizmetID ON dbo.RezervasyonHizmetleri;
CREATE NONCLUSTERED INDEX IX_RezHiz_HizmetID
ON dbo.RezervasyonHizmetleri (HizmetID);


------------------------------------------------------------
-- 6) FISLER
-- Fatura ekranları için FisNo, RezervasyonID, FisTipi ile aranır
------------------------------------------------------------
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_Fisler_RezID' AND object_id = OBJECT_ID('dbo.Fisler'))
    DROP INDEX IX_Fisler_RezID ON dbo.Fisler;
CREATE NONCLUSTERED INDEX IX_Fisler_RezID
ON dbo.Fisler (RezervasyonID);

IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_Fisler_FisNo' AND object_id = OBJECT_ID('dbo.Fisler'))
    DROP INDEX IX_Fisler_FisNo ON dbo.Fisler;
CREATE NONCLUSTERED INDEX IX_Fisler_FisNo
ON dbo.Fisler (FisNo);

IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_Fisler_FisTipi' AND object_id = OBJECT_ID('dbo.Fisler'))
    DROP INDEX IX_Fisler_FisTipi ON dbo.Fisler;
CREATE NONCLUSTERED INDEX IX_Fisler_FisTipi
ON dbo.Fisler (FisTipi);


------------------------------------------------------------
-- 7) ODEMELER
------------------------------------------------------------
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_Odemeler_FisID' AND object_id = OBJECT_ID('dbo.Odemeler'))
    DROP INDEX IX_Odemeler_FisID ON dbo.Odemeler;
CREATE NONCLUSTERED INDEX IX_Odemeler_FisID
ON dbo.Odemeler (FisID);


------------------------------------------------------------
-- 8) FEEDBACK
------------------------------------------------------------
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_Feedback_MusteriID' AND object_id = OBJECT_ID('dbo.Feedback'))
    DROP INDEX IX_Feedback_MusteriID ON dbo.Feedback;
CREATE NONCLUSTERED INDEX IX_Feedback_MusteriID
ON dbo.Feedback (MusteriID);

IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_Feedback_RezID' AND object_id = OBJECT_ID('dbo.Feedback'))
    DROP INDEX IX_Feedback_RezID ON dbo.Feedback;
CREATE NONCLUSTERED INDEX IX_Feedback_RezID
ON dbo.Feedback (RezervasyonID);


------------------------------------------------------------
-- 9) OdaOzellikleri
------------------------------------------------------------
IF EXISTS (SELECT 1 FROM sys.indexes WHERE name = 'IX_OdaOzellikleri_OdaID' AND object_id = OBJECT_ID('dbo.OdaOzellikleri'))
    DROP INDEX IX_OdaOzellikleri_OdaID ON dbo.OdaOzellikleri;
CREATE NONCLUSTERED INDEX IX_OdaOzellikleri_OdaID
ON dbo.OdaOzellikleri (OdaID);
GO
