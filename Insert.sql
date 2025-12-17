USE OtelRezervasyonSistemi;
GO

/* =========================================================
   1) MÜŞTERİLER
   ========================================================= */

INSERT INTO Musteriler (
    Isim, Soyisim, TCKN, PasaportNo, Email,
    UlkeKodu, TelefonNo, Ulke, Il, Ilce, Mahalle
)
VALUES
-- 1: Ahmet – yerli müşteri (referans / yetişkin)
(N'Ahmet',  N'Yılmaz', 12345678901, NULL, N'ahmet.yilmaz@example.com',
 N'+90', N'5321112233', N'Türkiye', N'İstanbul', N'Kadıköy', N'Moda'),

-- 2: Elena – yabancı müşteri (pasaportlu)
(N'Elena',  N'Novak',  NULL, N'P1234567', N'elena.novak@example.com',
 N'+420', N'777123456', N'Çekya', N'Prag', N'Prag 1', N'Stare Mesto'),

-- 3: Mert – genç müşteri (gelecekteki rezervasyon)
(N'Mert',   N'Demir',  98765432109, NULL, N'mert.demir@example.com',
 N'+90', N'5539876543', N'Türkiye', N'Ankara', N'Çankaya', N'Kızılay'),

-- 4: Ali – 0–7 yaş çocuk indirimi senaryosu için aile babası
(N'Ali',    N'Çetin',  55566677788, NULL, N'ali.cetin@example.com',
 N'+90', N'5301114455', N'Türkiye', N'İzmir', N'Karşıyaka', N'Bostanlı'),

-- 5: Burcu – 7–12 yaş çocuk indirimi senaryosu için
(N'Burcu',  N'Kaya',   11122233344, NULL, N'burcu.kaya@example.com',
 N'+90', N'5312225566', N'Türkiye', N'Antalya', N'Muratpaşa', N'Lara'),

-- 6: Kemal – 12–18 yaş genç indirimi senaryosu için
(N'Kemal',  N'Şahin',  22233344455, NULL, N'kemal.sahin@example.com',
 N'+90', N'5323336677', N'Türkiye', N'Eskişehir', N'Tepebaşı', N'Bahçelievler'),

-- 7: Zeynep – hem personel hem müşteri (personel indirimi senaryosu)
(N'Zeynep', N'Kara',   33344455566, NULL, N'zeynep.kara@example.com',
 N'+90', N'5334447788', N'Türkiye', N'İstanbul', N'Beşiktaş', N'Levent');
GO

/* =========================================================
   2) PERSONELLER
   ========================================================= */

INSERT INTO Personeller (KullaniciAdi, KullaniciSoyadi, KullaniciRolu, PersonelSifre)
VALUES
-- 1
(N'Zeynep', N'Kara',  N'Resepsiyonist', N'pass123'),
-- 2
(N'Alper',  N'Aydın', N'Yönetici',      N'pass456');
GO

/* =========================================================
   3) KULLANICILAR (LOGIN)
   - Ahmet, Elena, Mert, Ali, Burcu, Kemal: müşteri hesabı
   - Zeynep: hem personel hem müşteri → PersonelMi = 1 + MusteriID & PersonelID dolu
   ========================================================= */

INSERT INTO Kullanicilar (Email, Sifre, PersonelMi, MusteriID, PersonelID)
VALUES
(N'ahmet.yilmaz@example.com',  N'ahmet123', 0, 1, NULL),
(N'elena.novak@example.com',   N'elena123', 0, 2, NULL),
(N'mert.demir@example.com',    N'mert123',  0, 3, NULL),
(N'ali.cetin@example.com',     N'ali123',   0, 4, NULL),
(N'burcu.kaya@example.com',    N'burcu123', 0, 5, NULL),
(N'kemal.sahin@example.com',   N'kemal123', 0, 6, NULL),

-- Zeynep: PersonelMi = 1, hem Personel hem Musteri
(N'zeynep.kara@example.com',   N'zeynep123', 1, 7, 1);
GO

/* =========================================================
   4) ODA TİPLERİ (SABİT VERİLER – UI'DAN EKLENMEYECEK)
   ========================================================= */

INSERT INTO OdaTipi (OdaTipiAdi, OdaTipiFiyat)
VALUES
-- 1
(N'Economy Tek Kişilik',        800),
-- 2
(N'Standart Çift Kişilik',     1200),
-- 3
(N'Aile Odası',                2000),
-- 4
(N'Suit Oda',                  3500),
-- 5
(N'Deluxe Deniz Manzaralı',    2800);
GO

/* =========================================================
   5) ODALAR (SABİT)
   ========================================================= */

INSERT INTO Odalar (OdaNumarasi, OdaTipiID, OdaKapasitesi, OdaDurum)
VALUES
-- Economy tek kişilikler
(N'101', 1, 1, 0),
(N'102', 1, 1, 0),

-- Standart çift kişilikler
(N'201', 2, 2, 0),
(N'202', 2, 2, 0),

-- Aile odaları (4 kişi)
(N'301', 3, 4, 0),
(N'302', 3, 4, 0),

-- Suit odalar (3 kişi)
(N'401', 4, 3, 0),
(N'402', 4, 3, 0),

-- Deluxe deniz manzaralı (3 kişi)
(N'501', 5, 3, 0),
(N'502', 5, 3, 0);
GO

/* =========================================================
   6) ODA ÖZELLİKLERİ (SABİT)
   ========================================================= */

INSERT INTO OdaOzellikleri (OdaID, OzellikAdi)
VALUES
(1, N'Klima'),
(1, N'Fransız Balkon'),
(2, N'Klima'),
(2, N'Bahçe Manzaralı'),

(3, N'Klima'),
(3, N'Şehir Manzaralı'),
(4, N'Klima'),
(4, N'Çift Yatak'),

(5, N'Aile için Uygun'),
(5, N'Ekstra Yatak'),
(6, N'Aile için Uygun'),
(6, N'Balkon'),

(7, N'Jakuzi'),
(7, N'Oturma Alanı'),
(8, N'Jakuzi'),
(8, N'Panoramik Manzara'),

(9, N'Deniz Manzaralı'),
(9, N'King Size Yatak'),
(10,N'Deniz Manzaralı'),
(10,N'Üst Kat');
GO

/* =========================================================
   7) HİZMETLER (SABİT)
   ========================================================= */

INSERT INTO Hizmetler (HizmetAdi, HizmetFiyati)
VALUES
( N'Açık Büfe Kahvaltı', 150.00),
( N'Spa Paketi',         600.00),
( N'Havalimanı Transfer',350.00),
( N'Oda Servisi',        100.00),
( N'Minik Kulüp (Çocuk)',200.00);
GO

/* =========================================================
   8) REZERVASYONLAR
   Her biri farklı indirim senaryosunu gösterecek:

   RezID 1 → Sadece yetişkin (referans, indirim yok)
   RezID 2 → 0–7 yaş çocuk var (çocuk ücretsiz)
   RezID 3 → 7–12 yaş çocuk var (%50)
   RezID 4 → 12–18 yaş genç var (%80)
   RezID 5 → Personel indirimi (%25 ekstra)
   RezID 6 → Gelecek tarihli aktif rezervasyon (örnek)
   ========================================================= */

INSERT INTO Rezervasyonlar (
    MusteriID, OdaID, GirisTarihi, CikisTarihi, RezervasyonDurum, OdemeDurumu
)
VALUES
-- RezID 1: Ahmet – 2 yetişkin, Aile Odası – Tamamlandı, referans fiyat
(1, 5, '2025-06-10', '2025-06-15', N'Tamamlandı', 0),

-- RezID 2: Ali – 2 yetişkin + 1 çocuk (5 yaş, 0–7 indirim)
(4, 6, '2025-06-20', '2025-06-24', N'Tamamlandı', 0),

-- RezID 3: Burcu – 1 yetişkin + 1 çocuk (10 yaş, 7–12 indirim)
(5, 7, '2025-07-05', '2025-07-10', N'Tamamlandı', 0),

-- RezID 4: Kemal – 2 yetişkin + 1 genç (16 yaş, 12–18 indirim)
(6, 8, '2025-08-01', '2025-08-06', N'Tamamlandı', 0),

-- RezID 5: Zeynep – personel indirimi (%25), 2 yetişkin
(7, 9, '2025-09-10', '2025-09-14', N'Tamamlandı', 0),

-- RezID 6: Mert – gelecekteki rezervasyon (aktif, henüz kalmamış)
(3, 3, '2025-12-20', '2025-12-25', N'Aktif', 0);
GO

/* =========================================================
   9) REZERVASYON KİŞİLERİ
   Kapasiteyi aşmayacak şekilde yaşlara dikkat ettim.
   ========================================================= */

INSERT INTO RezervasyonKisileri (
    RezervasyonID, MusteriID, Ad, Soyad, Yas, IliskiTipi
)
VALUES
-- RezID 1 (Ahmet) – 2 yetişkin (indirim yok)
(1, 1,    N'Ahmet', N'Yılmaz', 35, N'AnaMusteri'),
(1, NULL, N'Ayşe',  N'Yılmaz', 33, N'Es'),

-- RezID 2 (Ali) – 2 yetişkin + 1 çocuk (5 yaş → 0 katsayı)
(2, 4,    N'Ali',   N'Çetin',  38, N'AnaMusteri'),
(2, NULL, N'Esra',  N'Çetin',  36, N'Es'),
(2, NULL, N'Efe',   N'Çetin',   5, N'Cocuk'),

-- RezID 3 (Burcu) – 1 yetişkin + 1 çocuk (10 yaş → 0.5 katsayı)
(3, 5,    N'Burcu', N'Kaya',   40, N'AnaMusteri'),
(3, NULL, N'Deniz', N'Kaya',   10, N'Cocuk'),

-- RezID 4 (Kemal) – 2 yetişkin + 1 genç (16 yaş → 0.8 katsayı)
(4, 6,    N'Kemal', N'Şahin',  45, N'AnaMusteri'),
(4, NULL, N'Selin', N'Şahin',  43, N'Es'),
(4, NULL, N'Can',   N'Şahin',  16, N'Cocuk'),

-- RezID 5 (Zeynep) – 2 yetişkin (personel indirimi var çünkü Zeynep hem personel hem müşteri)
(5, 7,    N'Zeynep', N'Kara',  30, N'AnaMusteri'),
(5, NULL, N'Emre',   N'Kara',  32, N'Es'),

-- RezID 6 (Mert) – 2 yetişkin (gelecek tarih, aktif)
(6, 3,    N'Mert', N'Demir',   24, N'AnaMusteri'),
(6, NULL, N'Duygu',N'Demir',   23, N'Es');
GO

/* =========================================================
   10) REZERVASYON HİZMETLERİ
   Sadece bir kısmına birkaç hizmet bağlayalım.
   ========================================================= */

INSERT INTO RezervasyonHizmetleri (RezervasyonID, HizmetID)
VALUES
-- Rez1: Kahvaltı + Spa
(1, 1),
(1, 2),

-- Rez2: Kahvaltı + Minik Kulüp
(2, 1),
(2, 5),

-- Rez3: Kahvaltı
(3, 1),

-- Rez4: Spa + Transfer
(4, 2),
(4, 3),

-- Rez5: Sadece kahvaltı
(5, 1),

-- Rez6: Oda servisi
(6, 4);
GO

/* =========================================================
   11) TÜM REZERVASYONLAR İÇİN FİŞ OLUŞTUR
   - sp_OlusturKonaklamaFisi, yaş ve personel durumuna göre indirimi otomatik uygular.
   - Her rezervasyon için bir “Konaklama Ücreti” fişi ve fiş kalemi ekler.
   ========================================================= */

EXEC sp_OlusturKonaklamaFisi @RezervasyonID = 1, @KdvOrani = 10.0; -- Yetişkin referans
EXEC sp_OlusturKonaklamaFisi @RezervasyonID = 2, @KdvOrani = 10.0; -- 0–7 yaş çocuk indirimi
EXEC sp_OlusturKonaklamaFisi @RezervasyonID = 3, @KdvOrani = 10.0; -- 7–12 yaş çocuk indirimi
EXEC sp_OlusturKonaklamaFisi @RezervasyonID = 4, @KdvOrani = 10.0; -- 12–18 yaş genç indirimi
EXEC sp_OlusturKonaklamaFisi @RezervasyonID = 5, @KdvOrani = 10.0; -- Personel indirimi
EXEC sp_OlusturKonaklamaFisi @RezervasyonID = 6, @KdvOrani = 10.0; -- Gelecekteki rezervasyon örneği
GO


/* =========================================================
   12) TÜM REZERVASYONLARA AİT HİZMETLERİ FİŞ KALEMİNE EKLE
   - sp_OlusturKonaklamaFisi sadece "Konaklama Ücreti" satırını ekledi.
   - Burada RezervasyonHizmetleri üzerinden ekstra satırlar ekliyoruz.
   ========================================================= */

INSERT INTO FisKalemleri (FisID, HizmetID, Aciklama, Miktar, BirimFiyat)
SELECT
    F.FisID,
    H.HizmetID,
    H.HizmetAdi,
    1 AS Miktar,
    H.HizmetFiyati
FROM Fisler F
JOIN RezervasyonHizmetleri RH ON RH.RezervasyonID = F.RezervasyonID
JOIN Hizmetler H             ON H.HizmetID       = RH.HizmetID
WHERE F.FisTipi = N'Normal';
GO

/* 
   Bu insert'ten sonra TR_FisKalemleri_FisToplam trigger'ı çalışacak,
   Fisler tablosundaki AraToplam / KDV / GenelToplam yeniden hesaplanacak.
*/


/* =========================================================
   13) ÖDEME SENARYOLARI
   - Her indirim türünün faturasında farklı ödeme durumu olsun:
     Rez1 (yetişkin referans): Tam ödenmiş
     Rez2 (0–7 çocuk indirimi): Hiç ödenmemiş
     Rez3 (7–12 çocuk indirimi): Kısmen ödenmiş
     Rez4 (12–18 genç indirimi): Tam ödenmiş
     Rez5 (Personel indirimi): Tam ödenmiş
     Rez6 (gelecek rezervasyon): Ödeme yok (doğal)
   ========================================================= */

DECLARE @FisID1 INT, @FisID2 INT, @FisID3 INT, @FisID4 INT, @FisID5 INT, @FisID6 INT;

SELECT 
    @FisID1 = MAX(CASE WHEN RezervasyonID = 1 THEN FisID END),
    @FisID2 = MAX(CASE WHEN RezervasyonID = 2 THEN FisID END),
    @FisID3 = MAX(CASE WHEN RezervasyonID = 3 THEN FisID END),
    @FisID4 = MAX(CASE WHEN RezervasyonID = 4 THEN FisID END),
    @FisID5 = MAX(CASE WHEN RezervasyonID = 5 THEN FisID END),
    @FisID6 = MAX(CASE WHEN RezervasyonID = 6 THEN FisID END)
FROM Fisler
WHERE FisTipi = N'Normal';

------------------------------------------------------------
-- Rez1: Tam ödeme (Nakit)
------------------------------------------------------------
INSERT INTO Odemeler (FisID, OdemeTarihi, OdemeYontemi, Tutar, KartSon4, KartSahibiAd, BankaAdi, IslemRefNo)
SELECT 
    @FisID1,
    SYSDATETIME(),
    N'Nakit',
    GenelToplam,
    NULL, NULL, NULL,
    N'REZ1-NAKIT'
FROM Fisler
WHERE FisID = @FisID1;

------------------------------------------------------------
-- Rez2: Ödeme yok → bekleyen borç
-- (bilerek ödeme eklemiyoruz)
------------------------------------------------------------

------------------------------------------------------------
-- Rez3: Kısmi ödeme (Kredi kartı ile bir kısmı ödensin)
------------------------------------------------------------
INSERT INTO Odemeler (FisID, OdemeTarihi, OdemeYontemi, Tutar, KartSon4, KartSahibiAd, BankaAdi, IslemRefNo)
SELECT
    @FisID3,
    SYSDATETIME(),
    N'Kredi Kartı',
    F.GenelToplam * 0.4,           -- %40'ını ödesin
    N'4242',
    N'Burcu Kaya',
    N'Bank A',
    N'REZ3-KK-1'
FROM Fisler F
WHERE F.FisID = @FisID3;

------------------------------------------------------------
-- Rez4: Tam ödeme (Havale/EFT)
------------------------------------------------------------
INSERT INTO Odemeler (FisID, OdemeTarihi, OdemeYontemi, Tutar, KartSon4, KartSahibiAd, BankaAdi, IslemRefNo)
SELECT
    @FisID4,
    SYSDATETIME(),
    N'Havale/EFT',
    GenelToplam,
    NULL,
    N'Kemal Şahin',
    N'Bank B',
    N'REZ4-EFT-1'
FROM Fisler
WHERE FisID = @FisID4;

------------------------------------------------------------
-- Rez5: Personel indirimi olan fiş → Tam ödeme (Kredi Kartı, tek çekim)
------------------------------------------------------------
INSERT INTO Odemeler (FisID, OdemeTarihi, OdemeYontemi, Tutar, KartSon4, KartSahibiAd, BankaAdi, IslemRefNo)
SELECT
    @FisID5,
    SYSDATETIME(),
    N'Kredi Kartı',
    GenelToplam,
    N'1111',
    N'Zeynep Kara',
    N'Bank C',
    N'REZ5-KK-1'
FROM Fisler
WHERE FisID = @FisID5;

------------------------------------------------------------
-- Rez6: Gelecekteki rezervasyon → şimdilik ödeme yok
------------------------------------------------------------

GO

/* 
   Bu ödemeler girilince TR_Odemeler_FisDurumu trigger'ı çalışacak:
   - Tam ödenenlerde OdendiMi = 1, OdemeTarihi dolu
   - Kısmi ve ödenmemişlerde OdendiMi = 0, OdemeTarihi NULL kalacak
*/


/* =========================================================
   14) FEEDBACK (sadece tamamlanmış rezervasyonlar için)
   - Rez1, Rez2, Rez3, Rez4, Rez5: "Tamamlandı"
   - Rez6: 'Aktif' → henüz feedback yok
   ========================================================= */

INSERT INTO Feedback (MusteriID, RezervasyonID, Yorum, Puan)
VALUES
-- Rez1: Yetişkin referans
(1, 1, N'Oda çok temizdi, ailece memnun kaldık.', 5),

-- Rez2: 0–7 çocuk indirimi
(4, 2, N'Çocuk alanları çok iyiydi, teşekkürler.', 5),

-- Rez3: 7–12 çocuk indirimi
(5, 3, N'Fiyat-performans dengesi güzel ama Wi-Fi biraz yavaştı.', 4),

-- Rez4: 12–18 genç indirimi
(6, 4, N'Gençler için aktiviteler güzeldi, tekrar gelmeyi düşünüyoruz.', 5),

-- Rez5: Personel indirimi
(7, 5, N'Personel indirimi çok iyi, çalışma arkadaşlarına tavsiye ederim :)', 5);
GO
