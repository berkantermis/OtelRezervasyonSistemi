USE OtelRezervasyonSistemi;
GO

/* =========================================================
   VARSA ESKİ VIEW'LARI SİL
   ========================================================= */

IF OBJECT_ID('dbo.vw_AktifRezervasyonlar', 'V') IS NOT NULL
    DROP VIEW dbo.vw_AktifRezervasyonlar;
IF OBJECT_ID('dbo.vw_MusteriRezOzet', 'V') IS NOT NULL
    DROP VIEW dbo.vw_MusteriRezOzet;
IF OBJECT_ID('dbo.vw_BekleyenOdemeliFisler', 'V') IS NOT NULL
    DROP VIEW dbo.vw_BekleyenOdemeliFisler;
IF OBJECT_ID('dbo.vw_OdaDolulukTakvimi', 'V') IS NOT NULL
    DROP VIEW dbo.vw_OdaDolulukTakvimi;
GO

/* =========================================================
   1) AKTİF REZERVASYONLAR (Müşteri + Oda + Tarih + Kişi Sayısı)
   ========================================================= */

CREATE VIEW dbo.vw_AktifRezervasyonlar
AS
SELECT
    R.RezervasyonID,
    R.GirisTarihi,
    R.CikisTarihi,
    R.RezervasyonDurum,

    M.MusteriID,
    M.Isim       AS MusteriAd,
    M.Soyisim    AS MusteriSoyad,
    M.Email      AS MusteriEmail,

    O.OdaID,
    O.OdaNumarasi,
    OT.OdaTipiAdi,
    OT.OdaTipiFiyat,

    -- Rezervasyondaki gerçek kişi sayısı
    COUNT(RK.RezervasyonKisiID) AS KisiSayisi
FROM Rezervasyonlar R
JOIN Musteriler M ON M.MusteriID = R.MusteriID
JOIN Odalar O     ON O.OdaID      = R.OdaID
JOIN OdaTipi OT   ON OT.OdaTipiID = O.OdaTipiID
LEFT JOIN RezervasyonKisileri RK ON RK.RezervasyonID = R.RezervasyonID
WHERE ISNULL(R.RezervasyonDurum, N'Aktif') <> N'İptal'
GROUP BY
    R.RezervasyonID,
    R.GirisTarihi,
    R.CikisTarihi,
    R.RezervasyonDurum,
    M.MusteriID,
    M.Isim,
    M.Soyisim,
    M.Email,
    O.OdaID,
    O.OdaNumarasi,
    OT.OdaTipiAdi,
    OT.OdaTipiFiyat;
GO

/* =========================================================
   2) MÜŞTERİ REZERVASYON ÖZETİ + FİŞ
   ========================================================= */

CREATE VIEW dbo.vw_MusteriRezOzet
AS
SELECT
    M.MusteriID,
    M.Isim       AS MusteriAd,
    M.Soyisim    AS MusteriSoyad,
    M.Email,

    R.RezervasyonID,
    R.GirisTarihi,
    R.CikisTarihi,
    R.RezervasyonDurum,

    O.OdaNumarasi,
    OT.OdaTipiAdi,

    F.FisID,
    F.FisNo,
    F.FisTarihi,
    F.FisTipi,
    F.AraToplam,
    F.KdvTutar,
    F.IndirimTutar,
    F.GenelToplam,
    F.OdendiMi
FROM Musteriler M
JOIN Rezervasyonlar R ON R.MusteriID = M.MusteriID
JOIN Odalar O         ON O.OdaID      = R.OdaID
JOIN OdaTipi OT       ON OT.OdaTipiID = O.OdaTipiID
LEFT JOIN Fisler F    ON F.RezervasyonID = R.RezervasyonID;
GO

/* =========================================================
   3) BEKLEYEN ÖDEMELİ FİŞLER
   ========================================================= */

CREATE VIEW dbo.vw_BekleyenOdemeliFisler
AS
SELECT
    F.FisID,
    F.FisNo,
    F.FisTarihi,
    F.FisTipi,
    F.AraToplam,
    F.KdvTutar,
    F.IndirimTutar,
    F.GenelToplam,

    R.RezervasyonID,
    R.GirisTarihi,
    R.CikisTarihi,
    R.RezervasyonDurum,

    M.MusteriID,
    M.Isim    AS MusteriAd,
    M.Soyisim AS MusteriSoyad,
    M.Email   AS MusteriEmail
FROM Fisler F
JOIN Rezervasyonlar R ON R.RezervasyonID = F.RezervasyonID
JOIN Musteriler    M ON M.MusteriID      = R.MusteriID
WHERE F.OdendiMi = 0
  AND F.FisTipi  = N'Normal';
GO

/* =========================================================
   4) ODA DOLULUK TAKVİMİ
   ========================================================= */

CREATE VIEW dbo.vw_OdaDolulukTakvimi
AS
SELECT
    O.OdaID,
    O.OdaNumarasi,
    OT.OdaTipiAdi,

    R.RezervasyonID,
    R.GirisTarihi,
    R.CikisTarihi,
    R.RezervasyonDurum,

    M.MusteriID,
    M.Isim    AS MusteriAd,
    M.Soyisim AS MusteriSoyad
FROM Odalar O
JOIN OdaTipi OT        ON OT.OdaTipiID      = O.OdaTipiID
LEFT JOIN Rezervasyonlar R ON R.OdaID      = O.OdaID
LEFT JOIN Musteriler M     ON M.MusteriID  = R.MusteriID;
GO
