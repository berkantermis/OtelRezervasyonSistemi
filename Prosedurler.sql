USE OtelRezervasyonSistemi;
GO

IF OBJECT_ID('sp_OlusturKonaklamaFisi', 'P') IS NOT NULL
    DROP PROCEDURE sp_OlusturKonaklamaFisi;
GO

CREATE PROCEDURE sp_OlusturKonaklamaFisi
    @RezervasyonID INT,
    @KdvOrani      DECIMAL(5,2) = 10.0  -- %10 varsayılan
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE 
        @MusteriID      INT,
        @OdaID          INT,
        @GirisTarihi    DATE,
        @CikisTarihi    DATE,
        @GeceSayisi     INT,
        @OdaTipiID      INT,
        @OdaFiyat       DECIMAL(10,2),
        @ToplamKatsayi  DECIMAL(10,2),
        @KonaklamaAra   DECIMAL(18,2),
        @IndirimTutar   DECIMAL(18,2),
        @KdvTutar       DECIMAL(18,2),
        @GenelToplam    DECIMAL(18,2),
        @FisID          INT,
        @FisNo          NVARCHAR(50),
        @KisiTamSayi    INT,
        @TamFiyat       DECIMAL(18,2);

    /* 1) Rezervasyon temel bilgileri */
    SELECT 
        @MusteriID   = R.MusteriID,
        @OdaID       = R.OdaID,
        @GirisTarihi = R.GirisTarihi,
        @CikisTarihi = R.CikisTarihi
    FROM Rezervasyonlar R
    WHERE R.RezervasyonID = @RezervasyonID;

    IF @MusteriID IS NULL
    BEGIN
        RAISERROR(N'Geçersiz RezervasyonID.', 16, 1);
        RETURN;
    END

    SET @GeceSayisi = DATEDIFF(DAY, @GirisTarihi, @CikisTarihi);
    IF @GeceSayisi <= 0 SET @GeceSayisi = 1;

    SELECT 
        @OdaTipiID = O.OdaTipiID
    FROM Odalar O
    WHERE O.OdaID = @OdaID;

    SELECT 
        @OdaFiyat = CAST(OT.OdaTipiFiyat AS DECIMAL(10,2))
    FROM OdaTipi OT
    WHERE OT.OdaTipiID = @OdaTipiID;

    /* 2) Yaşa göre katsayı hesaplama */
    SELECT 
        @ToplamKatsayi = SUM(
            CASE 
                WHEN RK.Yas IS NULL THEN 1.0
                WHEN RK.Yas < 7  THEN 0.0   -- 0-7: ücretsiz
                WHEN RK.Yas <= 12 THEN 0.5   -- 7-12: %50
                WHEN RK.Yas < 18 THEN 0.8   -- 12-18: %80
                ELSE 1.0                    -- 18+: tam ücret
            END
        )
    FROM RezervasyonKisileri RK
    WHERE RK.RezervasyonID = @RezervasyonID;

    -- Eğer hiç kişi kaydı yoksa minimum 1 yetişkin varsay
    SET @ToplamKatsayi = ISNULL(@ToplamKatsayi, 0) + 1.0;

    /* 3) Konaklama ara toplam = Gece * OdaFiyat * ToplamKatsayi */
    SET @KonaklamaAra = @GeceSayisi * @OdaFiyat * @ToplamKatsayi;

    /* 4) Personel indirimi (%25) – rezervasyon sahibi aynı zamanda personel mi? */
    IF EXISTS (
        SELECT 1
        FROM Kullanicilar K
        WHERE K.MusteriID = @MusteriID
          AND K.PersonelMi = 1
          AND K.PersonelID IS NOT NULL
    )
    BEGIN
        SET @IndirimTutar = @KonaklamaAra * 0.25;  -- %25 indirim
    END
    ELSE
    BEGIN
        SET @IndirimTutar = 0;
    END

    /* 5) KDV ve Genel Toplam */
    SET @KdvTutar    = ROUND(@KonaklamaAra * @KdvOrani / 100.0, 2);
    SET @GenelToplam = @KonaklamaAra + @KdvTutar - @IndirimTutar;

    /* 6) Fisler'e kayıt aç */
    SET @FisNo = CONCAT('FIS-', @RezervasyonID, '-', FORMAT(SYSDATETIME(), 'yyyyMMddHHmmssfff'));

    INSERT INTO Fisler (
        RezervasyonID, FisNo, FisTarihi,
        AraToplam, KdvOrani, KdvTutar,
        IndirimTutar, GenelToplam,
        OdendiMi, OdemeTarihi, FisTipi
    )
    VALUES (
        @RezervasyonID, @FisNo, SYSDATETIME(),
        @KonaklamaAra, @KdvOrani, @KdvTutar,
        @IndirimTutar, @GenelToplam,
        0, NULL, N'Normal'
    );

    SET @FisID = SCOPE_IDENTITY();

    /* 7) FisKalemleri'ne "Konaklama Ücreti" satırı ekle */
    INSERT INTO FisKalemleri (FisID, HizmetID, Aciklama, Miktar, BirimFiyat)
    VALUES (
        @FisID,
        NULL,   -- istersen oda ücreti için özel bir HizmetID verebilirsin
        N'Konaklama Ücreti',
        @GeceSayisi,
        (@OdaFiyat * @ToplamKatsayi)  -- 1 gecelik toplam kişi katsayılı fiyat
    );

    -- Uygulamanın kullanması için sonuçları döndürelim
    SELECT 
        @FisID        AS OlusanFisID,
        @KonaklamaAra AS AraToplam,
        @IndirimTutar AS IndirimTutar,
        @KdvTutar     AS KdvTutar,
        @GenelToplam  AS GenelToplam;
END;
GO
