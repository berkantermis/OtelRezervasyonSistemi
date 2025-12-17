<?php
require_once __DIR__ . '/../config.php';

// Güvenlik: Giriş yapılmış mı?
if (!current_user()) { 
    header('Location: login.php'); 
    exit; 
}

// URL'den Rezervasyon ID'sini al
$rezID = isset($_GET['rez_id']) ? (int)$_GET['rez_id'] : 0;

if (!$rezID) {
    die("Geçersiz işlem. Rezervasyon ID bulunamadı.");
}

try {
    // 1. Fiş ve Rezervasyon Bilgilerini Çek (rez_id'ye göre arıyoruz)
    $sql = "
        SELECT 
            F.*, 
            R.GirisTarihi, R.CikisTarihi, R.MusteriID as RezMusteriID,
            M.Isim, M.Soyisim, M.Email, M.TelefonNo, M.Ulke, M.Il,
            O.OdaNumarasi, OT.OdaTipiAdi
        FROM Fisler F
        JOIN Rezervasyonlar R ON R.RezervasyonID = F.RezervasyonID
        JOIN Musteriler M ON M.MusteriID = R.MusteriID
        JOIN Odalar O ON O.OdaID = R.OdaID
        JOIN OdaTipi OT ON OT.OdaTipiID = O.OdaTipiID
        WHERE F.RezervasyonID = :id
    ";
    
    $stmt = db()->prepare($sql);
    $stmt->execute([':id' => $rezID]);
    $fis = $stmt->fetch();

    if (!$fis) { 
        die("Bu rezervasyona ait henüz bir fiş oluşturulmamış."); 
    }

    // 2. Yetki Kontrolü: Müşteri ise sadece kendi fişini görebilir
    if (empty($_SESSION['kullanici']['personelMi'])) {
        $currentMusteriID = $_SESSION['kullanici']['musteriId'];
        
        if ($fis['RezMusteriID'] != $currentMusteriID) {
            die("HATA: Bu faturayı görüntüleme yetkiniz yok.");
        }
    }

    // 3. Fiş Kalemlerini (Hizmetleri) Çek
    $kalemler = db()->query("SELECT * FROM FisKalemleri WHERE FisID = " . $fis['FisID'])->fetchAll();

} catch (Exception $e) {
    die("Veritabanı hatası: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Fiş Görüntüle - <?= h($fis['FisNo']) ?></title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background: #555; padding: 20px; }
        .invoice-box {
            max-width: 800px; margin: 0 auto; padding: 30px; border: 1px solid #eee;
            background: white; box-shadow: 0 0 10px rgba(0, 0, 0, 0.15); font-size: 16px; line-height: 24px; color: #555;
        }
        .invoice-box table { width: 100%; line-height: inherit; text-align: left; }
        .invoice-box table td { padding: 5px; vertical-align: top; }
        .invoice-box table tr td:nth-child(2) { text-align: right; }
        .top-title { font-size: 45px; line-height: 45px; color: #333; }
        
        .heading td { background: #eee; border-bottom: 1px solid #ddd; font-weight: bold; }
        .item td { border-bottom: 1px solid #eee; }
        .total td:nth-child(2) { border-top: 2px solid #eee; font-weight: bold; }
        
        .badge { padding: 5px 10px; border-radius: 5px; color: white; font-size: 12px; font-weight: bold; }
        .paid { background: #2ecc71; }
        .unpaid { background: #e74c3c; }

        @media print {
            body { background: white; padding: 0; }
            .no-print { display: none; }
            .invoice-box { box-shadow: none; border: 0; margin: 0; width: 100%; }
        }
    </style>
</head>
<body>

    <div style="text-align: center; margin-bottom: 20px;" class="no-print">
        <button onclick="window.print()" style="padding: 10px 20px; background: #333; color: white; border: none; cursor: pointer; border-radius: 5px;">🖨️ Yazdır / PDF İndir</button>
        <button onclick="window.close()" style="padding: 10px 20px; background: #ddd; color: #333; border: none; cursor: pointer; border-radius: 5px;">Kapat</button>
    </div>

    <div class="invoice-box">
        <table cellpadding="0" cellspacing="0">
            <tr class="top">
                <td colspan="2">
                    <table>
                        <tr>
                            <td class="top-title">MARUN OTEL</td>
                            <td>
                                <strong>Fiş No:</strong> <?= h($fis['FisNo']) ?><br>
                                <strong>Tarih:</strong> <?= date('d.m.Y', strtotime($fis['FisTarihi'])) ?><br>
                                <strong>Durum:</strong> 
                                <?php if($fis['OdendiMi']): ?>
                                    <span class="badge paid">ÖDENDİ</span>
                                <?php else: ?>
                                    <span class="badge unpaid">ÖDENMEDİ</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr class="information">
                <td colspan="2">
                    <table>
                        <tr>
                            <td>
                                Marun Otel A.Ş.<br>
                                Bağdat Caddesi No: 123<br>
                                Kadıköy, İstanbul
                            </td>
                            <td>
                                <strong>Sayın:</strong><br>
                                <?= h($fis['Isim'] . ' ' . $fis['Soyisim']) ?><br>
                                <?= h($fis['Email']) ?><br>
                                <?= h($fis['Ulke']) ?>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr class="heading">
                <td>Hizmet / Açıklama</td>
                <td>Tutar</td>
            </tr>
            
            <?php foreach($kalemler as $kalem): ?>
            <tr class="item">
                <td><?= h($kalem['Aciklama']) ?> <?php if($kalem['Miktar'] > 1) echo " (x".(int)$kalem['Miktar'].")"; ?></td>
                <td><?= number_format((float)$kalem['SatirTutar'], 2) ?> TL</td>
            </tr>
            <?php endforeach; ?>

            <tr class="item">
                <td style="text-align:right; padding-top:20px;">Ara Toplam:</td>
                <td style="padding-top:20px;"><?= number_format((float)$fis['AraToplam'], 2) ?> TL</td>
            </tr>
            <tr class="item">
                <td style="text-align:right;">KDV (%<?= (int)$fis['KdvOrani'] ?>):</td>
                <td><?= number_format((float)$fis['KdvTutar'], 2) ?> TL</td>
            </tr>
            <?php if($fis['IndirimTutar'] > 0): ?>
            <tr class="item">
                <td style="text-align:right; color:red;">İndirim:</td>
                <td style="color:red;">-<?= number_format((float)$fis['IndirimTutar'], 2) ?> TL</td>
            </tr>
            <?php endif; ?>

            <tr class="total">
                <td style="text-align:right;">GENEL TOPLAM:</td>
                <td><?= number_format((float)$fis['GenelToplam'], 2) ?> TL</td>
            </tr>
        </table>
        
        <div style="margin-top: 30px; font-size: 12px; color: #777; text-align: center;">
            <p>Konaklama detayları yukarıdaki gibidir. Bizi tercih ettiğiniz için teşekkür ederiz.</p>
        </div>
    </div>
</body>
</html>