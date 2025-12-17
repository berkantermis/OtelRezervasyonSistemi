USE OtelRezervasyonSistemi;
GO

/* =========================================================
   1) MÜŞTERİ BAŞINA REZERVASYON SAYISI
      - GROUP BY + HAVING
      - Sadece 1'den fazla rezervasyonu olan müşteriler
   ========================================================= */

SELECT 
    M.MusteriID,
    M.Isim,
    M.Soyisim,
    COUNT(R.RezervasyonID) AS RezervasyonSayisi
FROM Musteriler M
JOIN Rezervasyonlar R
    ON R.MusteriID = M.MusteriID   -- IX_Rezervasyonlar_MusteriID
GROUP BY 
    M.MusteriID,
    M.Isim,
    M.Soyisim
HAVING COUNT(R.RezervasyonID) > 1
ORDER BY RezervasyonSayisi DESC;
GO


/* =========================================================
   2) ODA TİPİNE GÖRE TOPLAM GELİR + ORTALAMA FİŞ TUTARI
      - GROUP BY
      - HAVING ile ortalamanın üstündekileri süz
   ========================================================= */

SELECT
    OT.OdaTipiAdi,
    COUNT(DISTINCT R.RezervasyonID)        AS RezervasyonSayisi,
    SUM(F.GenelToplam)                     AS ToplamGelir,
    AVG(F.GenelToplam)                     AS OrtalamaFisTutari
FROM Fisler F
JOIN Rezervasyonlar R  ON R.RezervasyonID = F.RezervasyonID
JOIN Odalar O          ON O.OdaID         = R.OdaID
JOIN OdaTipi OT        ON OT.OdaTipiID    = O.OdaTipiID
WHERE F.FisTipi = N'Normal'               -- IX_Fisler_FisTipi
GROUP BY OT.OdaTipiAdi
HAVING SUM(F.GenelToplam) >
       (SELECT AVG(GenelToplam) FROM Fisler WHERE FisTipi = N'Normal')
ORDER BY ToplamGelir DESC;
GO


/* =========================================================
   3) EXISTS ÖRNEĞİ:
      En az bir "Tamamlandı" rezervasyonu ve feedback'i olan müşteriler
      - EXISTS + correlated subquery
   ========================================================= */

SELECT 
    M.MusteriID,
    M.Isim,
    M.Soyisim,
    M.Email
FROM Musteriler M
WHERE EXISTS (
    SELECT 1
    FROM Rezervasyonlar R
    JOIN Feedback FB ON FB.RezervasyonID = R.RezervasyonID
    WHERE R.MusteriID = M.MusteriID
      AND R.RezervasyonDurum = N'Tamamlandı'
);
GO


/* =========================================================
   4) NOT EXISTS ÖRNEĞİ:
      Hiç rezervasyonu olmayan müşteriler
   ========================================================= */

SELECT 
    M.MusteriID,
    M.Isim,
    M.Soyisim,
    M.Email
FROM Musteriler M
WHERE NOT EXISTS (
    SELECT 1 
    FROM Rezervasyonlar R
    WHERE R.MusteriID = M.MusteriID
);
GO


/* =========================================================
   5) ANY / SOME ÖRNEĞİ:
      Fiyatı, herhangi bir "Economy" oda tipinden daha yüksek olan oda tipleri
      - ANY ve SOME SQL Server'da eş anlamlı
   ========================================================= */

SELECT 
    OT.OdaTipiID,
    OT.OdaTipiAdi,
    OT.OdaTipiFiyat
FROM OdaTipi OT
WHERE OT.OdaTipiFiyat > ANY (
    SELECT O2.OdaTipiFiyat
    FROM OdaTipi O2
    WHERE O2.OdaTipiAdi LIKE N'%Economy%'
)
ORDER BY OT.OdaTipiFiyat DESC;
GO


/* =========================================================
   6) İÇ İÇE GROUP BY + HAVING:
      Toplam geliri, tüm müşterilerin ortalama gelirinden yüksek olan müşteriler
   ========================================================= */

SELECT * 
FROM (
    SELECT
        M.MusteriID,
        M.Isim,
        M.Soyisim,
        SUM(F.GenelToplam) AS MusteriToplamGelir
    FROM Musteriler M
    JOIN Rezervasyonlar R ON R.MusteriID = M.MusteriID
    JOIN Fisler F         ON F.RezervasyonID = R.RezervasyonID
    WHERE F.FisTipi = N'Normal'
    GROUP BY M.MusteriID, M.Isim, M.Soyisim
) AS X
WHERE X.MusteriToplamGelir >
      (SELECT AVG(GenelToplam) FROM Fisler WHERE FisTipi = N'Normal')
ORDER BY X.MusteriToplamGelir DESC;
GO


/* =========================================================
   7) INNER JOIN ÖRNEĞİ:
      Tüm rezervasyon + müşteri + oda bilgisi
   ========================================================= */

SELECT
    R.RezervasyonID,
    M.Isim + N' ' + M.Soyisim AS Musteri,
    O.OdaNumarasi,
    OT.OdaTipiAdi,
    R.GirisTarihi,
    R.CikisTarihi,
    R.RezervasyonDurum
FROM Rezervasyonlar R
INNER JOIN Musteriler M ON M.MusteriID = R.MusteriID
INNER JOIN Odalar O     ON O.OdaID      = R.OdaID
INNER JOIN OdaTipi OT   ON OT.OdaTipiID = O.OdaTipiID
ORDER BY R.GirisTarihi;
GO


/* =========================================================
   8) LEFT JOIN ÖRNEĞİ:
      Tüm odalar + varsa aktif rezervasyonu, yoksa NULL
      - Boş odaları da görürsün.
   ========================================================= */

SELECT
    O.OdaID,
    O.OdaNumarasi,
    OT.OdaTipiAdi,
    O.OdaKapasitesi,
    R.RezervasyonID,
    R.GirisTarihi,
    R.CikisTarihi,
    R.RezervasyonDurum
FROM Odalar O
LEFT JOIN OdaTipi OT       ON OT.OdaTipiID    = O.OdaTipiID
LEFT JOIN Rezervasyonlar R ON R.OdaID        = O.OdaID
                            AND R.RezervasyonDurum <> N'İptal'
ORDER BY O.OdaNumarasi, R.GirisTarihi;
GO


/* =========================================================
   9) RIGHT JOIN ÖRNEĞİ:
      Hizmetlere göre kaç rezervasyonda kullanılmış?
      - Hizmet hiç kullanılmamış olsa bile görünsün.
   ========================================================= */

SELECT
    H.HizmetID,
    H.HizmetAdi,
    H.HizmetFiyati,
    COUNT(RH.RezervasyonID) AS KullanimSayisi
FROM RezervasyonHizmetleri RH
RIGHT JOIN Hizmetler H
    ON H.HizmetID = RH.HizmetID
GROUP BY H.HizmetID, H.HizmetAdi, H.HizmetFiyati
ORDER BY KullanimSayisi DESC;
GO


/* =========================================================
   10) FULL OUTER JOIN ÖRNEĞİ:
       Hem Musteriler hem de Kullanicilar üzerinden birleşik liste
       - Kullanicisi olmayan müşteri
       - MusteriID'si NULL olan kullanıcı (sadece personel)
   ========================================================= */

SELECT
    COALESCE(M.MusteriID, K.MusteriID)          AS MusteriID,
    M.Isim,
    M.Soyisim,
    K.Email,
    K.PersonelMi
FROM Musteriler M
FULL OUTER JOIN Kullanicilar K
    ON M.MusteriID = K.MusteriID
ORDER BY MusteriID, PersonelMi DESC;
GO


/* =========================================================
   11) VIEW KULLANIMI:
       Bugünden itibaren tüm aktif rezervasyonlar, kişi başı ortalama tutar ile
       - vw_AktifRezervasyonlar + Fisler
   ========================================================= */

SELECT
    A.RezervasyonID,
    A.MusteriAd,
    A.MusteriSoyad,
    A.OdaNumarasi,
    A.GirisTarihi,
    A.CikisTarihi,
    A.KisiSayisi,
    F.GenelToplam,
    CASE 
        WHEN A.KisiSayisi > 0 
        THEN F.GenelToplam / A.KisiSayisi
        ELSE NULL
    END AS KisiBasiTutar
FROM vw_AktifRezervasyonlar A
JOIN Fisler F
    ON F.RezervasyonID = A.RezervasyonID
WHERE A.CikisTarihi >= CAST(GETDATE() AS DATE)
  AND F.FisTipi = N'Normal'
ORDER BY A.GirisTarihi;
GO


/* =========================================================
   12) BEKLEYEN ÖDEMELİ FİŞLERDEN EN BÜYÜK BORÇLULAR
       - vw_BekleyenOdemeliFisler + GROUP BY
   ========================================================= */

SELECT
    B.MusteriID,
    B.MusteriAd,
    B.MusteriSoyad,
    COUNT(B.FisID) AS BekleyenFisSayisi,
    SUM(B.GenelToplam) AS ToplamBekleyenTutar
FROM vw_BekleyenOdemeliFisler B
GROUP BY B.MusteriID, B.MusteriAd, B.MusteriSoyad
HAVING SUM(B.GenelToplam) > 0
ORDER BY ToplamBekleyenTutar DESC;
GO


/* =========================================================
   13) FİŞ + ÖDEME ANALİZİ:
       Her fiş için tam/kısmi/hiç ödenmemiş durumu
       - EXISTS kullanılmıyor ama index dostu bir GROUP BY
   ========================================================= */

SELECT
    F.FisID,
    F.RezervasyonID,
    M.Isim + N' ' + M.Soyisim AS Musteri,
    F.GenelToplam,
    ISNULL(SUM(O.Tutar), 0) AS ToplamOdeme,
    CASE 
        WHEN ISNULL(SUM(O.Tutar), 0) = 0                  THEN N'Hiç Ödenmemiş'
        WHEN ISNULL(SUM(O.Tutar), 0) < F.GenelToplam      THEN N'Kısmi Ödenmiş'
        ELSE N'Tam Ödenmiş'
    END AS OdemeDurumu
FROM Fisler F
LEFT JOIN Odemeler  O ON O.FisID = F.FisID
JOIN Rezervasyonlar R ON R.RezervasyonID = F.RezervasyonID
JOIN Musteriler     M ON M.MusteriID     = R.MusteriID
WHERE F.FisTipi = N'Normal'
GROUP BY F.FisID, F.RezervasyonID, M.Isim, M.Soyisim, F.GenelToplam
ORDER BY F.FisID;
GO


/* =========================================================
   14) YAŞ GRUBU ANALİZİ:
       Her rezervasyondaki çocuk/genç/yetişkin sayısı
       ve en az 1 çocuk içeren rezervasyonlar (HAVING ile)
   ========================================================= */

SELECT
    R.RezervasyonID,
    R.GirisTarihi,
    R.CikisTarihi,
    SUM(CASE WHEN RK.Yas IS NOT NULL AND RK.Yas < 7  THEN 1 ELSE 0 END) AS Cocuk_0_6,
    SUM(CASE WHEN RK.Yas BETWEEN 7 AND 11 THEN 1 ELSE 0 END)            AS Cocuk_7_12,
    SUM(CASE WHEN RK.Yas BETWEEN 12 AND 17 THEN 1 ELSE 0 END)           AS Genc_12_18,
    SUM(CASE WHEN RK.Yas >= 18 THEN 1 ELSE 0 END)                       AS Yetiskin_18Ustu
FROM Rezervasyonlar R
LEFT JOIN RezervasyonKisileri RK ON RK.RezervasyonID = R.RezervasyonID
GROUP BY R.RezervasyonID, R.GirisTarihi, R.CikisTarihi
HAVING SUM(CASE WHEN RK.Yas IS NOT NULL AND RK.Yas < 18 THEN 1 ELSE 0 END) > 0
ORDER BY R.RezervasyonID;
GO


/* =========================================================
   15) FEEDBACK + REZERVASYON FİYATI BİRLİKTE:
       Puanı 4 ve üzeri olan müşterilerin ortalama harcaması
       - GROUP BY, HAVING, AVG ile
   ========================================================= */

SELECT
    M.MusteriID,
    M.Isim,
    M.Soyisim,
    AVG(FB.Puan * 1.0)             AS OrtalamaPuan,
    SUM(F.GenelToplam)             AS ToplamHarcama
FROM Musteriler M
JOIN Rezervasyonlar R ON R.MusteriID = M.MusteriID
JOIN Feedback      FB ON FB.RezervasyonID = R.RezervasyonID
JOIN Fisler        F  ON F.RezervasyonID  = R.RezervasyonID
WHERE F.FisTipi = N'Normal'
GROUP BY M.MusteriID, M.Isim, M.Soyisim
HAVING AVG(FB.Puan * 1.0) >= 4.0
ORDER BY ToplamHarcama DESC;
GO
