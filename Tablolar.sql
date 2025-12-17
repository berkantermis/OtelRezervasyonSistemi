USE OtelRezervasyonSistemi;
GO

/* =========================================================
   1) MÜŞTERİLER
   ========================================================= */

CREATE TABLE Musteriler (
    MusteriID   INT IDENTITY(1,1) PRIMARY KEY,
    Isim        NVARCHAR(50) NOT NULL,
    Soyisim     NVARCHAR(50) NOT NULL,

    -- Yerli / yabancı kimlik
    TCKN        BIGINT NULL,          -- Yerli için 11 hane
    PasaportNo  NVARCHAR(20) NULL,    -- Yabancı için pasaport numarası

    Email       NVARCHAR(100),

    UlkeKodu    NVARCHAR(5),          -- Örn: +90
    TelefonNo   NVARCHAR(15),         -- Örn: 5321234567

    Ulke        NVARCHAR(50),
    Il          NVARCHAR(50),
    Ilce        NVARCHAR(50),
    Mahalle     NVARCHAR(50),

    KayitTarihi DATE DEFAULT GETDATE(),

    -- TCKN 11 haneli ise geçerli
    CONSTRAINT CK_Musteriler_TCKN_11Hane
        CHECK (TCKN IS NULL OR (TCKN BETWEEN 10000000000 AND 99999999999)),

    -- Pasaport basit format: 5–20 uzunluk, sadece harf/rakam
    CONSTRAINT CK_Musteriler_Pasaport_Format
        CHECK (
            PasaportNo IS NULL
            OR (
                LEN(PasaportNo) BETWEEN 5 AND 20
                AND PasaportNo NOT LIKE '%[^0-9A-Za-z]%'
            )
        ),

    -- TCKN veya Pasaport'tan en az biri zorunlu
    CONSTRAINT CK_Musteriler_Kimlik_Zorunlu
        CHECK (TCKN IS NOT NULL OR PasaportNo IS NOT NULL),

    -- Telefon sadece rakam, makul uzunlukta
    CONSTRAINT CK_Musteriler_Telefon_Format
        CHECK (
            TelefonNo IS NULL
            OR (
                TelefonNo NOT LIKE '%[^0-9]%'
                AND LEN(TelefonNo) BETWEEN 7 AND 12
            )
        ),

    -- Ülke kodu: + ile başlasın, rakam içersin
    CONSTRAINT CK_Musteriler_UlkeKodu_Format
        CHECK (
            UlkeKodu IS NULL
            OR (
                UlkeKodu LIKE '+[0-9]%'
                AND LEN(UlkeKodu) BETWEEN 2 AND 5
            )
        ),

    -- Email temel format: x@y.z ve boşluk yok
    CONSTRAINT CK_Musteriler_Email_Format
        CHECK (
            Email IS NULL
            OR (
                Email LIKE '%_@_%._%'
                AND Email NOT LIKE '% %'
            )
        )
);

/* =========================================================
   2) ODA TİPİ
   ========================================================= */

CREATE TABLE OdaTipi (
    OdaTipiID    INT IDENTITY(1,1) PRIMARY KEY,
    OdaTipiAdi   NVARCHAR(50) NOT NULL,
    OdaTipiFiyat BIGINT NOT NULL
);

/* =========================================================
   3) ODALAR
   ========================================================= */

CREATE TABLE Odalar (
    OdaID         INT IDENTITY(1,1) PRIMARY KEY,
    OdaNumarasi   NVARCHAR(10) NOT NULL,
    OdaTipiID     INT NOT NULL,
    OdaKapasitesi INT NOT NULL,
    OdaDurum      BIT DEFAULT 0,      -- 0: boş, 1: dolu

    CONSTRAINT FK_Odalar_OdaTipi
        FOREIGN KEY (OdaTipiID) REFERENCES OdaTipi(OdaTipiID),

    CONSTRAINT CK_Odalar_Kapasite_Pozitif
        CHECK (OdaKapasitesi > 0)
);

/* =========================================================
   4) PERSONELLER
   ========================================================= */

CREATE TABLE Personeller (
    PersonelID      INT IDENTITY(1,1) PRIMARY KEY,
    KullaniciAdi    NVARCHAR(50) NOT NULL UNIQUE,
    KullaniciSoyadi NVARCHAR(50),
    KullaniciRolu   NVARCHAR(50),       -- Örn: Resepsiyonist, Yönetici
    PersonelSifre   NVARCHAR(100) NOT NULL
);

/* =========================================================
   5) HİZMETLER
   ========================================================= */

CREATE TABLE Hizmetler (
    HizmetID     INT IDENTITY(1,1) PRIMARY KEY,
    HizmetAdi    NVARCHAR(100) NOT NULL,
    HizmetFiyati DECIMAL(10, 2) NOT NULL
);

/* =========================================================
   6) REZERVASYONLAR
   ========================================================= */

CREATE TABLE Rezervasyonlar (
    RezervasyonID    INT IDENTITY(1,1) PRIMARY KEY,
    MusteriID        INT NOT NULL,
    OdaID            INT NOT NULL,
    GirisTarihi      DATE NOT NULL,
    CikisTarihi      DATE NOT NULL,
    RezervasyonDurum NVARCHAR(50),      -- Örn: 'Aktif', N'İptal', 'Tamamlandı'
    OdemeDurumu      INT DEFAULT 0,     -- 0: ödenmemiş, 1: ödenmiş (legacy / istersen kaldırırsın)

    CONSTRAINT FK_Rezervasyonlar_Musteri
        FOREIGN KEY (MusteriID) REFERENCES Musteriler(MusteriID),

    CONSTRAINT FK_Rezervasyonlar_Oda
        FOREIGN KEY (OdaID) REFERENCES Odalar(OdaID)
);

/* =========================================================
   7) REZERVASYON HİZMETLERİ
   ========================================================= */

CREATE TABLE RezervasyonHizmetleri (
    RezervasyonHizmetID INT IDENTITY(1,1) PRIMARY KEY,
    RezervasyonID       INT NOT NULL,
    HizmetID            INT NOT NULL,

    CONSTRAINT FK_RezHiz_Rez
        FOREIGN KEY (RezervasyonID) REFERENCES Rezervasyonlar(RezervasyonID),

    CONSTRAINT FK_RezHiz_Hiz
        FOREIGN KEY (HizmetID) REFERENCES Hizmetler(HizmetID)
);

/* =========================================================
   8) FİŞLER / FATURA
   ========================================================= */

CREATE TABLE Fisler (
    FisID         INT IDENTITY(1,1) PRIMARY KEY,
    RezervasyonID INT NOT NULL,

    FisNo         NVARCHAR(50) NOT NULL UNIQUE,    -- Örn: FIS-1-20251205123000123
    FisTarihi     DATETIME2 NOT NULL DEFAULT SYSDATETIME(),

    AraToplam     DECIMAL(10,2) NOT NULL,          -- KDV öncesi
    KdvOrani      DECIMAL(5,2) NOT NULL DEFAULT 10, -- Örn: 10.00 => %10
    KdvTutar      DECIMAL(10,2) NOT NULL,
    IndirimTutar  DECIMAL(10,2) NOT NULL DEFAULT 0,

    GenelToplam   DECIMAL(10,2) NOT NULL,          -- AraToplam + KdvTutar - IndirimTutar

    OdendiMi      BIT NOT NULL DEFAULT 0,          -- 0: bekliyor, 1: tamamen ödenmiş
    OdemeTarihi   DATETIME2 NULL,                  -- Tamamı ödendiği tarih (varsa)

    FisTipi       NVARCHAR(20) NOT NULL DEFAULT N'Normal',  -- 'Normal' veya 'İptal'

    CONSTRAINT FK_Fisler_Rezervasyon
        FOREIGN KEY (RezervasyonID) REFERENCES Rezervasyonlar(RezervasyonID)
);

/* =========================================================
   9) FİŞ KALEMLERİ
   ========================================================= */

CREATE TABLE FisKalemleri (
    FisKalemID  INT IDENTITY(1,1) PRIMARY KEY,
    FisID       INT NOT NULL,
    HizmetID    INT NULL,                 -- Oda ücreti için NULL, ekstra hizmetler için dolu
    Aciklama    NVARCHAR(200) NOT NULL,   -- Örn: 'Oda Ücreti (2 Gece)', 'SPA Paketi'
    Miktar      INT NOT NULL DEFAULT 1,
    BirimFiyat  DECIMAL(10,2) NOT NULL,

    SatirTutar  AS (Miktar * BirimFiyat) PERSISTED,

    CONSTRAINT FK_FisKalemleri_Fis
        FOREIGN KEY (FisID) REFERENCES Fisler(FisID),

    CONSTRAINT FK_FisKalemleri_Hizmet
        FOREIGN KEY (HizmetID) REFERENCES Hizmetler(HizmetID)
);

/* =========================================================
   10) ÖDEMELER
   ========================================================= */

CREATE TABLE Odemeler (
    OdemeID      INT IDENTITY(1,1) PRIMARY KEY,
    FisID        INT NOT NULL,

    OdemeTarihi  DATETIME2 NOT NULL DEFAULT SYSDATETIME(),
    OdemeYontemi NVARCHAR(50) NOT NULL,   -- 'Nakit', 'Kredi Kartı', 'Havale/EFT' vs.
    Tutar        DECIMAL(10,2) NOT NULL,

    KartSon4     NVARCHAR(4) NULL,        -- Sadece son 4 hane
    KartSahibiAd NVARCHAR(100) NULL,
    BankaAdi     NVARCHAR(100) NULL,
    IslemRefNo   NVARCHAR(100) NULL,      -- POS / banka referans numarası

    CONSTRAINT FK_Odemeler_Fis
        FOREIGN KEY (FisID) REFERENCES Fisler(FisID)
);

/* =========================================================
   11) FEEDBACK
   ========================================================= */

CREATE TABLE Feedback (
    FeedbackID    INT IDENTITY(1,1) PRIMARY KEY,
    MusteriID     INT NOT NULL,
    RezervasyonID INT NOT NULL,
    Yorum         NVARCHAR(255),
    Puan          INT CHECK (Puan BETWEEN 1 AND 5),
    GonderiTarihi DATE DEFAULT GETDATE(),

    CONSTRAINT FK_Feedback_Musteri
        FOREIGN KEY (MusteriID) REFERENCES Musteriler(MusteriID),

    CONSTRAINT FK_Feedback_Rez
        FOREIGN KEY (RezervasyonID) REFERENCES Rezervasyonlar(RezervasyonID)
);

/* =========================================================
   13) ODA ÖZELLİKLERİ
   ========================================================= */

CREATE TABLE OdaOzellikleri (
    OdaOzellikID INT IDENTITY(1,1) PRIMARY KEY,
    OdaID        INT NOT NULL,
    OzellikAdi   NVARCHAR(100) NOT NULL,   -- Örn: 'Deniz Manzaralı', 'Klima'

    CONSTRAINT FK_OdaOzellikleri_Oda
        FOREIGN KEY (OdaID) REFERENCES Odalar(OdaID)
);

/* =========================================================
   14) REZERVASYON KİŞİLERİ (AİLE BİREYLERİ vb.)
   ========================================================= */

CREATE TABLE RezervasyonKisileri (
    RezervasyonKisiID INT IDENTITY(1,1) PRIMARY KEY,
    RezervasyonID     INT NOT NULL,

    MusteriID         INT NULL,              -- İsterse sistem müşterisiyle eşleştirilir
    Ad                NVARCHAR(50) NOT NULL,
    Soyad             NVARCHAR(50) NOT NULL,
    Yas               INT NULL,              -- Yaş -> indirim için kullanılacak

    IliskiTipi        NVARCHAR(20) NULL,     -- 'AnaMusteri', 'Es', 'Cocuk', 'Arkadas' vb.

    CONSTRAINT FK_RezKisiler_Rez
        FOREIGN KEY (RezervasyonID) REFERENCES Rezervasyonlar(RezervasyonID),

    CONSTRAINT FK_RezKisiler_Musteri
        FOREIGN KEY (MusteriID) REFERENCES Musteriler(MusteriID)
);

/* =========================================================
   15) KULLANICILAR (LOGIN – EMAIL İLE)
   ========================================================= */

CREATE TABLE Kullanicilar (
    KullaniciID INT IDENTITY(1,1) PRIMARY KEY,

    Email       NVARCHAR(255) NOT NULL UNIQUE,  -- Giriş buradan
    Sifre       NVARCHAR(255) NOT NULL,         -- Hash saklaman önerilir

    -- 0 = Müşteri hesabı, 1 = Personel hesabı
    PersonelMi  BIT NOT NULL DEFAULT 0,

    MusteriID   INT NULL,
    PersonelID  INT NULL,

    CONSTRAINT CK_Kullanicilar_Email_Format
        CHECK (
            Email LIKE '%_@_%._%'
            AND Email NOT LIKE '% %'
        ),

    CONSTRAINT FK_Kullanicilar_Musteri
        FOREIGN KEY (MusteriID) REFERENCES Musteriler(MusteriID),

    CONSTRAINT FK_Kullanicilar_Personel
        FOREIGN KEY (PersonelID) REFERENCES Personeller(PersonelID),

    -- KURAL:
    -- PersonelMi = 0 → müşteri hesabı: MusteriID dolu, PersonelID boş
    -- PersonelMi = 1 → personel hesabı: PersonelID dolu, MusteriID ister boş ister dolu
    CONSTRAINT CK_Kullanici_Tip CHECK (
        (PersonelMi = 0 AND MusteriID IS NOT NULL AND PersonelID IS NULL)
     OR (PersonelMi = 1 AND PersonelID IS NOT NULL)
    )
);
GO
