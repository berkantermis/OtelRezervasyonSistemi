USE OtelRezervasyonSistemi;
GO

IF OBJECT_ID('RezervasyonHizmetleri', 'U') IS NOT NULL DROP TABLE RezervasyonHizmetleri;

IF OBJECT_ID('RezervasyonKisileri',   'U') IS NOT NULL DROP TABLE RezervasyonKisileri;

IF OBJECT_ID('Odemeler', 'U') IS NOT NULL DROP TABLE Odemeler;

IF OBJECT_ID('FisKalemleri', 'U') IS NOT NULL DROP TABLE FisKalemleri;

IF OBJECT_ID('Fisler', 'U') IS NOT NULL DROP TABLE Fisler;

IF OBJECT_ID('Feedback','U') IS NOT NULL DROP TABLE Feedback;

IF OBJECT_ID('OdaOzellikleri','U') IS NOT NULL DROP TABLE OdaOzellikleri;

IF OBJECT_ID('Rezervasyonlar', 'U') IS NOT NULL DROP TABLE Rezervasyonlar;

IF OBJECT_ID('Hizmetler', 'U') IS NOT NULL DROP TABLE Hizmetler;

IF OBJECT_ID('Odalar', 'U') IS NOT NULL DROP TABLE Odalar;

IF OBJECT_ID('OdaTipi', 'U') IS NOT NULL DROP TABLE OdaTipi;

IF OBJECT_ID('Kullanicilar', 'U') IS NOT NULL DROP TABLE Kullanicilar;

IF OBJECT_ID('Personeller', 'U') IS NOT NULL DROP TABLE Personeller;

IF OBJECT_ID('Musteriler',  'U') IS NOT NULL DROP TABLE Musteriler;

GO
