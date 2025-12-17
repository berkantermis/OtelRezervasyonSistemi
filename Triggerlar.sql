USE OtelRezervasyonSistemi;
GO

/* =========================================================
   ESKİ TRIGGER'LARI SİL
   ========================================================= */

IF OBJECT_ID('TR_Rezervasyonlar_NoOverlap', 'TR') IS NOT NULL DROP TRIGGER TR_Rezervasyonlar_NoOverlap;
IF OBJECT_ID('TR_Rezervasyonlar_OdaDurum', 'TR') IS NOT NULL DROP TRIGGER TR_Rezervasyonlar_OdaDurum;
IF OBJECT_ID('TR_RezervasyonKisileri_Kapasite', 'TR') IS NOT NULL DROP TRIGGER TR_RezervasyonKisileri_Kapasite;
IF OBJECT_ID('TR_Feedback_Kisit', 'TR') IS NOT NULL DROP TRIGGER TR_Feedback_Kisit;
IF OBJECT_ID('TR_Odemeler_FisDurumu', 'TR') IS NOT NULL DROP TRIGGER TR_Odemeler_FisDurumu;
IF OBJECT_ID('TR_FisKalemleri_FisToplam', 'TR') IS NOT NULL DROP TRIGGER TR_FisKalemleri_FisToplam;
IF OBJECT_ID('TR_Rezervasyon_Iptal_Fis', 'TR') IS NOT NULL DROP TRIGGER TR_Rezervasyon_Iptal_Fis;
GO

/* =========================================================
   1) AYNI ODAYA TARİH ÇAKIŞMALI REZERVASYON ENGELİ
   ========================================================= */

CREATE TRIGGER TR_Rezervasyonlar_NoOverlap
ON Rezervasyonlar
AFTER INSERT, UPDATE
AS
BEGIN
    SET NOCOUNT ON;

    IF EXISTS (
        SELECT 1
        FROM inserted I
        JOIN Rezervasyonlar R
            ON R.OdaID = I.OdaID
           AND R.RezervasyonID <> I.RezervasyonID
           AND ISNULL(R.RezervasyonDurum, N'Aktif') <> N'İptal'
           AND ISNULL(I.RezervasyonDurum, N'Aktif') <> N'İptal'
        WHERE R.GirisTarihi < ISNULL(I.CikisTarihi, R.CikisTarihi)
          AND ISNULL(R.CikisTarihi, R.GirisTarihi) > I.GirisTarihi
    )
    BEGIN
        RAISERROR (N'Ayni oda için çakişan rezervasyon mevcut.', 16, 1);
        ROLLBACK TRANSACTION;
        RETURN;
    END
END;
GO

/* =========================================================
   2) REZERVASYONLARA GÖRE ODA DURUMUNU OTOMATİK GÜNCELLE
   ========================================================= */

CREATE TRIGGER TR_Rezervasyonlar_OdaDurum
ON Rezervasyonlar
AFTER INSERT, UPDATE, DELETE
AS
BEGIN
    SET NOCOUNT ON;

    ;WITH DegisenOdalar AS (
        SELECT OdaID FROM inserted
        UNION
        SELECT OdaID FROM deleted
    )
    UPDATE O
        SET OdaDurum =
            CASE
                WHEN EXISTS (
                    SELECT 1
                    FROM Rezervasyonlar R
                    WHERE R.OdaID = O.OdaID
                      AND ISNULL(R.RezervasyonDurum, N'Aktif') <> N'İptal'
                )
                THEN 1
                ELSE 0
            END
    FROM Odalar O
    JOIN DegisenOdalar D ON D.OdaID = O.OdaID;
END;
GO

-- =========================================================
--  3) REZERVASYON KİŞİLERİNE GÖRE ODA KAPASİTESİ KONTROLÜ
-- - Aynı rezervasyondaki kişi sayısı, oda kapasitesini aşamaz
--   ========================================================= 

CREATE TRIGGER TR_RezervasyonKisileri_Kapasite
ON RezervasyonKisileri
AFTER INSERT, UPDATE, DELETE
AS
BEGIN
    SET NOCOUNT ON;

    IF EXISTS (
        SELECT 1
        FROM (
            SELECT 
                R.RezervasyonID,
                COUNT(RK.RezervasyonKisiID) AS KisiAdedi,
                O.OdaKapasitesi
            FROM Rezervasyonlar R
            JOIN (
                SELECT RezervasyonID FROM inserted
                UNION
                SELECT RezervasyonID FROM deleted
            ) DR ON DR.RezervasyonID = R.RezervasyonID
            JOIN Odalar O ON O.OdaID = R.OdaID
            LEFT JOIN RezervasyonKisileri RK 
                   ON RK.RezervasyonID = R.RezervasyonID
            GROUP BY R.RezervasyonID, O.OdaKapasitesi
        ) AS KisiSayilari
        WHERE KisiSayilari.KisiAdedi > KisiSayilari.OdaKapasitesi
    )
    BEGIN
        RAISERROR (N'Oda kapasitesi, bu rezervasyondaki kişi sayısını karşılamıyor.', 16, 1);
        ROLLBACK TRANSACTION;
        RETURN;
    END
END;
GO

/* =========================================================
   4) FEEDBACK KISITI
   ========================================================= */

CREATE TRIGGER TR_Feedback_Kisit
ON Feedback
AFTER INSERT, UPDATE
AS
BEGIN
    SET NOCOUNT ON;

    IF EXISTS (
        SELECT 1
        FROM inserted I
        LEFT JOIN Rezervasyonlar R
           ON R.RezervasyonID = I.RezervasyonID
          AND R.MusteriID     = I.MusteriID
        WHERE
            R.RezervasyonID IS NULL                      -- rezervasyon yok
            OR R.RezervasyonDurum = N'İptal'             -- iptal edilmiş
            OR R.CikisTarihi > CAST(GETDATE() AS DATE)   -- konaklama bitmemiş
    )
    BEGIN
        RAISERROR (N'Bu rezervasyon için şu anda geri bildirim verilemez.', 16, 1);
        ROLLBACK TRANSACTION;
        RETURN;
    END
END;
GO

/* =========================================================
   5) ÖDEMELERE GÖRE FİŞİN ODENDİMİ / ODEMETARİHİ BİLGİLERİNİ GÜNCELLE
   ========================================================= */

CREATE TRIGGER TR_Odemeler_FisDurumu
ON Odemeler
AFTER INSERT, UPDATE, DELETE
AS
BEGIN
    SET NOCOUNT ON;

    ;WITH DegisenFisler AS (
        SELECT FisID FROM inserted
        UNION
        SELECT FisID FROM deleted
    ),
    OdemeOzet AS (
        SELECT 
            F.FisID,
            SUM(ISNULL(O.Tutar, 0)) AS ToplamOdeme,
            MAX(O.OdemeTarihi)      AS SonOdemeTarihi
        FROM Fisler F
        LEFT JOIN Odemeler O ON O.FisID = F.FisID
        JOIN DegisenFisler DF ON DF.FisID = F.FisID
        GROUP BY F.FisID
    )
    UPDATE F
        SET 
            F.OdendiMi =
                CASE
                    WHEN OZ.ToplamOdeme >= F.GenelToplam THEN 1
                    ELSE 0
                END,
            F.OdemeTarihi =
                CASE
                    WHEN OZ.ToplamOdeme >= F.GenelToplam THEN OZ.SonOdemeTarihi
                    ELSE NULL
                END
    FROM Fisler F
    JOIN OdemeOzet OZ ON OZ.FisID = F.FisID;
END;
GO

/* =========================================================
   6) FİS KALEMLERİ DEĞİŞİNCE FİS TOPLAMLARINI OTOMATİK HESAPLA
   ========================================================= */

CREATE TRIGGER TR_FisKalemleri_FisToplam
ON FisKalemleri
AFTER INSERT, UPDATE, DELETE
AS
BEGIN
    SET NOCOUNT ON;

    ;WITH DegisenFisler AS (
        SELECT FisID FROM inserted
        UNION
        SELECT FisID FROM deleted
    ),
    AraToplamOzet AS (
        SELECT 
            F.FisID,
            SUM(ISNULL(FK.SatirTutar, 0)) AS YeniAraToplam
        FROM Fisler F
        LEFT JOIN FisKalemleri FK ON FK.FisID = F.FisID
        JOIN DegisenFisler DF ON DF.FisID = F.FisID
        GROUP BY F.FisID
    )
    UPDATE F
        SET 
            F.AraToplam   = OZ.YeniAraToplam,
            F.KdvTutar    = ROUND(OZ.YeniAraToplam * F.KdvOrani / 100.0, 2),
            F.GenelToplam = OZ.YeniAraToplam
                            + ROUND(OZ.YeniAraToplam * F.KdvOrani / 100.0, 2)
                            - F.IndirimTutar
    FROM Fisler F
    JOIN AraToplamOzet OZ ON OZ.FisID = F.FisID;
END;
GO

/* =========================================================
   7) REZERVASYON İPTAL OLUNCA OTOMATİK İPTAL FİŞİ OLUŞTUR
   ========================================================= */

CREATE TRIGGER TR_Rezervasyon_Iptal_Fis
ON Rezervasyonlar
AFTER UPDATE
AS
BEGIN
    SET NOCOUNT ON;

    ;WITH IptalEdilen AS (
        SELECT i.RezervasyonID
        FROM inserted i
        JOIN deleted d ON i.RezervasyonID = d.RezervasyonID
        WHERE d.RezervasyonDurum <> N'İptal'
          AND i.RezervasyonDurum = N'İptal'
    )
    INSERT INTO Fisler (
        RezervasyonID, FisNo, FisTarihi,
        AraToplam, KdvOrani, KdvTutar,
        IndirimTutar, GenelToplam,
        OdendiMi, OdemeTarihi, FisTipi
    )
    SELECT 
        R.RezervasyonID,
        CONCAT(N'IPTAL-', R.RezervasyonID, N'-', FORMAT(SYSDATETIME(), 'yyyyMMddHHmmssfff')),
        SYSDATETIME(),
        0,   -- AraToplam
        0,   -- KdvOrani
        0,   -- KdvTutar
        0,   -- IndirimTutar
        0,   -- GenelToplam
        0,   -- OdendiMi
        NULL,
        N'İptal'
    FROM IptalEdilen R
    WHERE NOT EXISTS (
        SELECT 1 FROM Fisler F
        WHERE F.RezervasyonID = R.RezervasyonID
          AND F.FisTipi = N'İptal'
    );
END;
GO
