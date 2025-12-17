<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';

// GÜVENLİK
if (!current_user()) { header('Location: login.php'); exit; }
if (empty($_SESSION['kullanici']['personelMi'])) { die("Yetkisiz Erişim!"); }

$message = ''; $error = ''; $sayfa = $_GET['sayfa'] ?? 'ozet';

// --- İŞLEMLER (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. Durum Güncelleme
    if (isset($_POST['action']) && $_POST['action'] === 'update_status') {
        try {
            $stmt = db()->prepare("UPDATE Rezervasyonlar SET RezervasyonDurum = ? WHERE RezervasyonID = ?");
            $stmt->execute([$_POST['yeni_durum'], $_POST['rez_id']]);
            if ($_POST['yeni_durum'] === 'Tamamlandı') {
                $odaID = db()->query("SELECT OdaID FROM Rezervasyonlar WHERE RezervasyonID = ".$_POST['rez_id'])->fetchColumn();
                db()->exec("UPDATE Odalar SET TemizlikDurumu = 'Kirli' WHERE OdaID = $odaID");
            }
            $message = "Durum başarıyla güncellendi.";
        } catch (Exception $e) { $error = $e->getMessage(); }
    }

    // 2. Oda Durumu
    if (isset($_POST['action']) && $_POST['action'] === 'oda_durum_guncelle') {
        try {
            db()->prepare("UPDATE Odalar SET TemizlikDurumu = ? WHERE OdaID = ?")->execute([$_POST['temizlik_durumu'], $_POST['oda_id']]);
            $message = "Oda durumu güncellendi.";
        } catch (Exception $e) { $error = $e->getMessage(); }
    }

    // 3. Yönetici Rezervasyon
    if (isset($_POST['action']) && $_POST['action'] === 'admin_rezervasyon_yap') {
        try {
            $db = db(); $db->beginTransaction();
            $mID = $_POST['mevcut_musteri_id'];
            if ($_POST['musteri_tipi'] === 'yeni') {
                $stmtM = $db->prepare("INSERT INTO Musteriler (Isim, Soyisim, TCKN, TelefonNo, Email, UlkeKodu, KayitTarihi) VALUES (?, ?, ?, ?, ?, '+90', GETDATE())");
                $stmtM->execute([$_POST['yeni_ad'], $_POST['yeni_soyad'], $_POST['yeni_tckn'], $_POST['yeni_tel'], $_POST['yeni_email']]);
                $mID = $db->lastInsertId();
            }
            $sqlOda = "SELECT TOP 1 O.OdaID FROM Odalar O WHERE O.OdaTipiID = ? AND O.OdaID NOT IN (SELECT R.OdaID FROM Rezervasyonlar R WHERE (R.GirisTarihi < ? AND R.CikisTarihi > ?) AND R.RezervasyonDurum <> 'İptal')";
            $stmt = $db->prepare($sqlOda); $stmt->execute([$_POST['oda_tipi_id'], $_POST['cikis_tarihi'], $_POST['giris_tarihi']]);
            $odaID = $stmt->fetchColumn();

            if ($odaID) {
                $stmtRez = $db->prepare("INSERT INTO Rezervasyonlar (MusteriID, OdaID, GirisTarihi, CikisTarihi, RezervasyonDurum) VALUES (?, ?, ?, ?, 'Aktif')");
                $stmtRez->execute([$mID, $odaID, $_POST['giris_tarihi'], $_POST['cikis_tarihi']]);
                $db->exec("EXEC sp_OlusturKonaklamaFisi @RezervasyonID = ".$db->lastInsertId());
                $db->commit(); $message = "Rezervasyon oluşturuldu. Oda: $odaID";
            } else { $db->rollBack(); $error = "Boş oda yok."; }
        } catch (Exception $e) { if($db->inTransaction()) $db->rollBack(); $error = $e->getMessage(); }
    }

    // 4. Tahsilat
    if (isset($_POST['action']) && $_POST['action'] === 'tahsil_et') {
        try {
            db()->prepare("INSERT INTO Odemeler (FisID, OdemeTarihi, OdemeYontemi, Tutar) VALUES (?, GETDATE(), 'Nakit', ?)")->execute([$_POST['fis_id'], $_POST['tutar']]);
            $message = "Tahsilat sisteme işlendi.";
        } catch (Exception $e) { $error = $e->getMessage(); }
    }

    // 5. Personel Ekle
    if (isset($_POST['action']) && $_POST['action'] === 'add_staff') {
        try {
            $db = db();
            $db->prepare("INSERT INTO Personeller (KullaniciAdi, KullaniciSoyadi, KullaniciRolu, PersonelSifre) VALUES (?, ?, ?, ?)")->execute([$_POST['ad'], $_POST['soyad'], $_POST['rol'], $_POST['sifre']]);
            $pID = $db->lastInsertId();
            $db->prepare("INSERT INTO Kullanicilar (Email, Sifre, PersonelMi, PersonelID) VALUES (?, ?, 1, ?)")->execute([$_POST['email'], password_hash($_POST['sifre'], PASSWORD_DEFAULT), $pID]);
            $message = "Personel eklendi.";
        } catch (Exception $e) { $error = $e->getMessage(); }
    }

    // 6. İstek Tamamlama
    if (isset($_POST['action']) && $_POST['action'] === 'istek_tamamla') {
        try {
            db()->prepare("UPDATE MusteriIstekleri SET Durum = 'Yapıldı' WHERE IstekID = ?")->execute([$_POST['istek_id']]);
            $message = "İstek tamamlandı.";
        } catch (Exception $e) { $error = $e->getMessage(); }
    }
}

// --- VERİ ÇEKME ---
$bugunGiris = db()->query("SELECT COUNT(*) FROM Rezervasyonlar WHERE GirisTarihi = CAST(GETDATE() AS DATE) AND RezervasyonDurum <> 'İptal'")->fetchColumn();
$bugunCikis = db()->query("SELECT COUNT(*) FROM Rezervasyonlar WHERE CikisTarihi = CAST(GETDATE() AS DATE) AND RezervasyonDurum <> 'İptal'")->fetchColumn();
$aktifMisafir = db()->query("SELECT COUNT(*) FROM Rezervasyonlar WHERE GirisTarihi <= CAST(GETDATE() AS DATE) AND CikisTarihi >= CAST(GETDATE() AS DATE) AND RezervasyonDurum = 'Aktif'")->fetchColumn();
$toplamCiro = db()->query("SELECT COALESCE(SUM(Tutar), 0) FROM Odemeler")->fetchColumn();

$bekleyenIstekler = db()->query("SELECT MI.*, O.OdaNumarasi, M.Isim, M.Soyisim FROM MusteriIstekleri MI JOIN Rezervasyonlar R ON R.RezervasyonID = MI.RezervasyonID JOIN Odalar O ON O.OdaID = R.OdaID JOIN Musteriler M ON M.MusteriID = R.MusteriID WHERE MI.Durum = 'Bekliyor' ORDER BY MI.IstekZamani ASC")->fetchAll();
$odalar = db()->query("SELECT O.*, OT.OdaTipiAdi, (SELECT TOP 1 M.Isim + ' ' + M.Soyisim FROM Rezervasyonlar R JOIN Musteriler M ON M.MusteriID = R.MusteriID WHERE R.OdaID = O.OdaID AND R.RezervasyonDurum = 'Aktif' AND CAST(GETDATE() AS DATE) BETWEEN R.GirisTarihi AND R.CikisTarihi) as MisafirAd FROM Odalar O JOIN OdaTipi OT ON OT.OdaTipiID = O.OdaTipiID ORDER BY O.OdaNumarasi")->fetchAll();
$rezervasyonlar = db()->query("SELECT R.*, M.Isim, M.Soyisim, O.OdaNumarasi, OT.OdaTipiAdi FROM Rezervasyonlar R JOIN Musteriler M ON M.MusteriID = R.MusteriID JOIN Odalar O ON O.OdaID = R.OdaID JOIN OdaTipi OT ON OT.OdaTipiID = O.OdaTipiID ORDER BY CASE WHEN R.RezervasyonDurum = 'Onay Bekliyor' THEN 0 ELSE 1 END, R.GirisTarihi DESC")->fetchAll();
$musteriler = db()->query("SELECT * FROM Musteriler ORDER BY Isim ASC")->fetchAll();
$odaTipleri = db()->query("SELECT * FROM OdaTipi")->fetchAll();
$personeller = db()->query("SELECT * FROM Personeller")->fetchAll();
$borclular = db()->query("SELECT TOP 20 * FROM vw_BekleyenOdemeliFisler ORDER BY GenelToplam DESC")->fetchAll();
$yorumlar = db()->query("SELECT TOP 20 F.*, M.Isim, M.Soyisim FROM Feedback F JOIN Musteriler M ON M.MusteriID = F.MusteriID ORDER BY F.GonderiTarihi DESC")->fetchAll();

function yildizla($puan) { return str_repeat('⭐', (int)$puan); }
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8"><title>Yönetim Paneli</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    /* ORTAK MODERN TEMA (Müşteri Paneli ile Uyumlu) */
    :root { 
        --bg-sidebar: #0f172a; 
        --bg-body: #f8fafc; 
        --bg-card: #ffffff; 
        --text-primary: #1e293b; 
        --text-secondary: #64748b; 
        --accent: #d4a373; 
        --accent-hover: #b58b62; 
        --success: #10b981; 
        --danger: #ef4444; 
        --warning: #f59e0b; 
        --info: #3b82f6; 
        --border: #e2e8f0; 
    }
    
    * { box-sizing: border-box; }
    body { margin: 0; font-family: 'Inter', sans-serif; background: var(--bg-body); color: var(--text-primary); display: flex; height: 100vh; overflow: hidden; }
    
    /* SIDEBAR */
    .sidebar { width: 260px; background: var(--bg-sidebar); color: white; display: flex; flex-direction: column; padding: 25px; box-shadow: 4px 0 24px rgba(0,0,0,0.05); z-index: 10; }
    .brand { font-size: 1.5rem; font-weight: 700; margin-bottom: 40px; color: var(--accent); letter-spacing: 1px; display: flex; align-items: center; gap: 10px; }
    .nav-item { display: flex; align-items: center; gap: 12px; padding: 12px 15px; color: #94a3b8; text-decoration: none; margin-bottom: 5px; border-radius: 12px; transition: 0.3s; font-weight: 500; font-size: 0.95rem; }
    .nav-item:hover, .nav-item.active { background: rgba(255,255,255,0.1); color: white; }
    .nav-item.active { background: var(--accent); color: white; box-shadow: 0 4px 12px rgba(212, 163, 115, 0.3); }
    .nav-item i { font-size: 1.1rem; width: 20px; text-align: center; }
    .nav-bottom { margin-top: auto; }

    /* MAIN */
    .main { flex: 1; padding: 30px 40px; overflow-y: auto; }
    .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
    .page-title h1 { margin: 0; font-size: 1.8rem; font-weight: 700; color: var(--text-primary); }
    .user-info { display: flex; align-items: center; gap: 15px; background: var(--bg-card); padding: 8px 15px; border-radius: 50px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); border: 1px solid var(--border); }
    .user-avatar { width: 35px; height: 35px; background: var(--bg-sidebar); color: var(--accent); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; }

    /* CARDS */
    .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px; }
    .kpi-card { background: var(--bg-card); padding: 25px; border-radius: 16px; border: 1px solid var(--border); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
    .kpi-value { font-size: 2rem; font-weight: 700; color: var(--bg-sidebar); margin-bottom: 5px; }
    .kpi-title { font-size: 0.9rem; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; }
    .kpi-sub { font-size: 0.8rem; margin-top: 5px; color: var(--success); font-weight: 500; }

    /* TABLES */
    .table-container { background: var(--bg-card); border-radius: 16px; overflow: hidden; border: 1px solid var(--border); box-shadow: 0 4px 20px rgba(0,0,0,0.05); margin-bottom: 30px; }
    .panel-header { padding: 20px 25px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; background: #fcfcfc; }
    .panel-title { margin: 0; font-size: 1.1rem; font-weight: 600; color: var(--text-primary); }
    
    table { width: 100%; border-collapse: collapse; }
    th { background: #f8fafc; color: var(--text-secondary); font-weight: 600; text-transform: uppercase; font-size: 0.75rem; padding: 15px 25px; text-align: left; letter-spacing: 0.5px; border-bottom: 1px solid var(--border); }
    td { padding: 15px 25px; border-bottom: 1px solid var(--border); font-size: 0.9rem; color: var(--text-primary); vertical-align: middle; }
    tr:last-child td { border-bottom: none; }
    tr:hover { background: #f8fafc; }

    /* BUTTONS & BADGES */
    .btn { padding: 8px 16px; border-radius: 8px; border: none; cursor: pointer; font-size: 0.85rem; font-weight: 600; transition: 0.2s; display: inline-flex; align-items: center; gap: 5px; }
    .btn-green { background: rgba(16, 185, 129, 0.1); color: var(--success); } .btn-green:hover { background: var(--success); color: white; }
    .btn-red { background: rgba(239, 68, 68, 0.1); color: var(--danger); } .btn-red:hover { background: var(--danger); color: white; }
    .btn-blue { background: rgba(59, 130, 246, 0.1); color: var(--info); } .btn-blue:hover { background: var(--info); color: white; }
    .btn-full { width: 100%; padding: 12px; background: var(--accent); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; }
    .btn-full:hover { background: var(--accent-hover); }

    .badge { padding: 5px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; }
    .badge.Aktif { background: rgba(212, 163, 115, 0.15); color: var(--accent); }
    .badge.Onay { background: rgba(59, 130, 246, 0.15); color: var(--info); }
    .badge.Tamamlandı { background: rgba(16, 185, 129, 0.15); color: var(--success); }
    .badge.İptal, .badge.odendi { background: rgba(239, 68, 68, 0.15); color: var(--danger); }

    /* ROOM GRID */
    .room-filter button { background: white; border: 1px solid var(--border); color: var(--text-secondary); padding: 8px 16px; border-radius: 20px; cursor: pointer; margin-right: 10px; font-weight: 500; transition:0.2s; }
    .room-filter button.active { background: var(--accent); color: white; border-color: var(--accent); }
    .room-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 20px; }
    .room-box { background: var(--bg-card); padding: 20px; border-radius: 16px; border: 1px solid var(--border); cursor: pointer; transition: 0.3s; position: relative; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
    .room-box:hover { transform: translateY(-5px); border-color: var(--accent); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
    .status-bar { height: 4px; width: 100%; position: absolute; top: 0; left: 0; }
    .r-dolu .status-bar { background: var(--danger); } .r-bos .status-bar { background: var(--success); } .r-kirli .status-bar { background: var(--warning); } .r-tadilat .status-bar { background: var(--text-secondary); }
    
    .room-num { font-size: 1.5rem; font-weight: 700; margin-bottom: 5px; display: flex; justify-content: space-between; color: var(--bg-sidebar); }
    .room-detail { margin-top: 15px; padding-top: 15px; border-top: 1px solid var(--border); font-size: 0.85rem; color: var(--text-secondary); }
    
    .content-card { background: var(--bg-card); padding: 30px; border-radius: 16px; border: 1px solid var(--border); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
    .staff-form { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    .form-control { width: 100%; padding: 10px 15px; border: 1px solid var(--border); border-radius: 8px; font-size: 0.95rem; background: #f8fafc; color: var(--text-primary); }
    .form-control:focus { outline: none; border-color: var(--accent); background: white; }
    .full-width { grid-column: span 2; }

    /* MODAL */
    .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.8); z-index: 100; backdrop-filter: blur(5px); justify-content: center; align-items: center; }
    .modal-box { background: white; padding: 30px; border-radius: 16px; width: 450px; position: relative; box-shadow: 0 20px 50px rgba(0,0,0,0.2); }
    .close-modal { position: absolute; top: 15px; right: 20px; font-size: 1.5rem; cursor: pointer; color: var(--text-secondary); }

    .request-card { background: var(--bg-card); padding: 20px; border-radius: 12px; border: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); border-left: 4px solid var(--warning); }
    .alert { padding: 15px; border-radius: 10px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
    .alert-success { background: rgba(16, 185, 129, 0.1); color: var(--success); border: 1px solid var(--success); }
    .alert-error { background: rgba(239, 68, 68, 0.1); color: var(--danger); border: 1px solid var(--danger); }
</style>
</head>
<body>

    <div class="sidebar">
        <div class="brand"><i class="fas fa-hotel"></i> MARUN ADMIN</div>
        <a href="?sayfa=ozet" class="nav-item <?= $sayfa=='ozet'?'active':'' ?>"><i class="fas fa-chart-line"></i> Özet Panel</a>
        <a href="?sayfa=istekler" class="nav-item <?= $sayfa=='istekler'?'active':'' ?>" style="color:<?= count($bekleyenIstekler)>0?!'var(--accent)':'' ?>"><i class="fas fa-bell"></i> Müşteri İstekleri (<?= count($bekleyenIstekler) ?>)</a>
        <a href="?sayfa=yeni_rezervasyon" class="nav-item <?= $sayfa=='yeni_rezervasyon'?'active':'' ?>"><i class="fas fa-plus-circle"></i> Yeni Rezervasyon</a>
        <a href="?sayfa=odalar" class="nav-item <?= $sayfa=='odalar'?'active':'' ?>"><i class="fas fa-door-open"></i> Oda Yönetimi</a>
        <a href="?sayfa=rezervasyonlar" class="nav-item <?= $sayfa=='rezervasyonlar'?'active':'' ?>"><i class="fas fa-calendar-alt"></i> Rezervasyonlar</a>
        <a href="?sayfa=muhasebe" class="nav-item <?= $sayfa=='muhasebe'?'active':'' ?>"><i class="fas fa-wallet"></i> Muhasebe</a>
        <a href="?sayfa=yorumlar" class="nav-item <?= $sayfa=='yorumlar'?'active':'' ?>"><i class="fas fa-comments"></i> Yorumlar</a>
        <a href="?sayfa=personel" class="nav-item <?= $sayfa=='personel'?'active':'' ?>"><i class="fas fa-users"></i> Personel</a>
        <div class="nav-bottom"><a href="logout.php" class="nav-item logout"><i class="fas fa-sign-out-alt"></i> Çıkış Yap</a></div>
    </div>

    <div class="main">
        <div class="header">
            <div class="page-title"><h1>
                <?php 
                    if($sayfa=='ozet') echo 'Genel Bakış';
                    elseif($sayfa=='odalar') echo 'Oda & Housekeeping';
                    elseif($sayfa=='rezervasyonlar') echo 'Rezervasyon Listesi';
                    elseif($sayfa=='muhasebe') echo 'Finansal Durum';
                    elseif($sayfa=='yeni_rezervasyon') echo 'Rezervasyon Oluştur';
                    elseif($sayfa=='istekler') echo 'Dijital Resepsiyon';
                    elseif($sayfa=='yorumlar') echo 'Müşteri Yorumları';
                    elseif($sayfa=='personel') echo 'İK Yönetimi';
                ?>
            </h1></div>
            <div class="user-info">
                <div class="user-avatar"><?= substr($_SESSION['kullanici']['ad'],0,1) ?></div>
                <span style="font-weight:500;"><?= h($_SESSION['kullanici']['ad']) ?></span>
            </div>
        </div>

        <?php if($message): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= h($message) ?></div><?php endif; ?>
        <?php if($error): ?><div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?= h($error) ?></div><?php endif; ?>

        <?php if ($sayfa === 'ozet'): ?>
            <div class="kpi-grid">
                <div class="kpi-card"><div class="kpi-value"><?= $bugunGiris ?></div><div class="kpi-title">Bugünkü Girişler</div><div class="kpi-sub">Beklenen Misafir</div></div>
                <div class="kpi-card"><div class="kpi-value"><?= $bugunCikis ?></div><div class="kpi-title">Bugünkü Çıkışlar</div><div class="kpi-sub">Oda Boşalacak</div></div>
                <div class="kpi-card"><div class="kpi-value"><?= $aktifMisafir ?></div><div class="kpi-title">Aktif Misafir</div><div class="kpi-sub">Şu an otelde</div></div>
                <div class="kpi-card"><div class="kpi-value" style="color:var(--success);"><?= money_tr($toplamCiro) ?></div><div class="kpi-title">Toplam Tahsilat</div><div class="kpi-sub">Kasa</div></div>
            </div>
            <div class="table-container">
                <div class="panel-header">Son Hareketler</div>
                <table><thead><tr><th>Misafir</th><th>Oda</th><th>Durum</th></tr></thead><tbody>
                <?php foreach(array_slice($rezervasyonlar,0,5) as $r): ?><tr><td><b><?= h($r['Isim']) ?></b></td><td><?= h($r['OdaNumarasi']) ?></td><td><span class="badge <?= explode(' ',$r['RezervasyonDurum'])[0] ?>"><?= $r['RezervasyonDurum'] ?></span></td></tr><?php endforeach; ?>
                </tbody></table>
            </div>
        <?php endif; ?>

        <?php if ($sayfa === 'odalar'): ?>
            <div class="room-filter">
                <button class="filter-btn active" onclick="filter('all')">Tümü</button><button class="filter-btn" onclick="filter('bos')">Boş</button><button class="filter-btn" onclick="filter('dolu')">Dolu</button><button class="filter-btn" onclick="filter('kirli')">Kirli</button>
            </div>
            <div class="room-grid">
                <?php foreach($odalar as $oda): 
                    $isDolu = !empty($oda['MisafirAd']); 
                    $cls = 'r-bos'; $txt = 'Müsait'; $tag='bos'; $icon='fa-check-circle';
                    if($oda['TemizlikDurumu']=='Kirli') { $cls='r-kirli'; $txt='KİRLİ'; $tag='kirli'; $icon='fa-broom'; }
                    if($oda['TemizlikDurumu']=='Tadilat') { $cls='r-tadilat'; $txt='TADİLAT'; $tag='tadilat'; $icon='fa-tools'; }
                    if($isDolu) { $cls='r-dolu'; $txt='DOLU'; $tag='dolu'; $icon='fa-user'; }
                ?>
                <div class="room-box <?= $cls ?> r-item" data-tag="<?= $tag ?>" onclick="openRoomModal(<?= $oda['OdaID'] ?>, '<?= $oda['OdaNumarasi'] ?>')">
                    <div class="status-bar"></div>
                    <div class="room-num"><span><?= h($oda['OdaNumarasi']) ?></span> <i class="fas <?= $icon ?>" style="opacity:0.3;"></i></div>
                    <small style="color:var(--text-secondary); text-transform:uppercase; font-size:0.75rem;"><?= h($oda['OdaTipiAdi']) ?></small>
                    <div class="room-detail">
                        <div style="font-weight:600; color:var(--text-primary);"><?= $txt ?></div>
                        <?php if($isDolu): ?><div style="color:var(--accent); font-size:0.85rem; margin-top:2px;">👤 <?= h($oda['MisafirAd']) ?></div><?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div id="roomModal" class="modal-overlay"><div class="modal-box"><span class="close-modal" onclick="document.getElementById('roomModal').style.display='none'">&times;</span><h3 style="margin-top:0;">Oda <span id="mRoomNum"></span> Durumu</h3><form method="POST"><input type="hidden" name="action" value="oda_durum_guncelle"><input type="hidden" name="oda_id" id="mRoomID"><div style="margin-bottom:15px;"><label>Yeni Durum</label><select name="temizlik_durumu" class="form-control"><option value="Temiz">✨ Temiz (Müsait Yap)</option><option value="Kirli">🧹 Kirli</option><option value="Tadilat">🛠️ Tadilat</option></select></div><button class="btn-full">GÜNCELLE</button></form></div></div>
            <script>
                function filter(tag){ document.querySelectorAll('.r-item').forEach(el => el.style.display = (tag==='all' || el.dataset.tag===tag) ? 'block' : 'none'); }
                function openRoomModal(id, num){ document.getElementById('mRoomID').value=id; document.getElementById('mRoomNum').innerText=num; document.getElementById('roomModal').style.display='flex'; }
            </script>
        <?php endif; ?>

        <?php if ($sayfa === 'rezervasyonlar'): ?>
            <div class="table-container">
                <div class="panel-header"><div class="panel-title">Tüm Rezervasyonlar</div></div>
                <table><thead><tr><th>Misafir</th><th>Oda</th><th>Tarih</th><th>Durum</th><th>İşlem</th></tr></thead><tbody>
                <?php foreach($rezervasyonlar as $r): ?><tr><td><b><?= h($r['Isim']) ?></b></td><td><?= h($r['OdaNumarasi']) ?></td><td><?= $r['GirisTarihi'] ?></td><td><span class="badge <?= explode(' ',$r['RezervasyonDurum'])[0] ?>"><?= $r['RezervasyonDurum'] ?></span></td><td>
                    <?php if($r['RezervasyonDurum']=='Onay Bekliyor'): ?><form method="POST" style="display:inline;"><input type="hidden" name="action" value="update_status"><input type="hidden" name="rez_id" value="<?= $r['RezervasyonID'] ?>"><input type="hidden" name="yeni_durum" value="Aktif"><button class="btn btn-blue">✅ Onayla</button></form>
                    <form method="POST" style="display:inline;"><input type="hidden" name="action" value="update_status"><input type="hidden" name="rez_id" value="<?= $r['RezervasyonID'] ?>"><input type="hidden" name="yeni_durum" value="İptal"><button class="btn btn-red">❌</button></form><?php endif; ?>
                    <?php if($r['RezervasyonDurum']=='Aktif'): ?><form method="POST" style="display:inline;"><input type="hidden" name="action" value="update_status"><input type="hidden" name="rez_id" value="<?= $r['RezervasyonID'] ?>"><input type="hidden" name="yeni_durum" value="Tamamlandı"><button class="btn btn-green">Çıkış Yap</button></form><?php endif; ?>
                    <a href="fis.php?rez_id=<?= $r['RezervasyonID'] ?>" target="_blank" class="btn btn-blue" style="text-decoration:none;"><i class="fas fa-file-invoice"></i></a>
                </td></tr><?php endforeach; ?></tbody></table></div>
        <?php endif; ?>

        <?php if ($sayfa === 'yeni_rezervasyon'): ?>
            <div class="content-card" style="max-width:700px;">
                <h3 style="margin-top:0; margin-bottom:20px;">Resepsiyon: Hızlı Rezervasyon</h3>
                <form method="POST" class="staff-form">
                    <input type="hidden" name="action" value="admin_rezervasyon_yap">
                    <div class="full-width"><label>İşlem Türü</label><select name="musteri_tipi" class="form-control" onchange="toggleM()"><option value="mevcut">📂 Kayıtlı Müşteri Seç</option><option value="yeni">➕ Yeni Müşteri Kaydı</option></select></div>
                    <div id="divMevcut" class="full-width"><label>Müşteri Seçin</label><select name="mevcut_musteri_id" class="form-control"><?php foreach($musteriler as $m): ?><option value="<?= $m['MusteriID'] ?>"><?= h($m['Isim'].' '.$m['Soyisim']) ?> (<?= h($m['TCKN']) ?>)</option><?php endforeach; ?></select></div>
                    <div id="divYeni" style="display:none; grid-column:span 2; grid-template-columns:1fr 1fr; gap:20px; background:#f8fafc; padding:20px; border-radius:10px; border:1px solid var(--border);"><input type="text" name="yeni_ad" placeholder="Ad" class="form-control"><input type="text" name="yeni_soyad" placeholder="Soyad" class="form-control"><input type="text" name="yeni_tckn" placeholder="TCKN" class="form-control"><input type="text" name="yeni_tel" placeholder="Telefon" class="form-control"><input type="email" name="yeni_email" placeholder="E-Posta" class="form-control full-width"></div>
                    <div class="full-width"><label>Oda Tipi</label><select name="oda_tipi_id" class="form-control"><?php foreach($odaTipleri as $o): ?><option value="<?= $o['OdaTipiID'] ?>"><?= h($o['OdaTipiAdi']) ?> - <?= h($o['Kapasite']) ?> Kişilik</option><?php endforeach; ?></select></div>
                    <div><label>Giriş</label><input type="date" name="giris_tarihi" class="form-control" required></div><div><label>Çıkış</label><input type="date" name="cikis_tarihi" class="form-control" required></div>
                    <button class="btn-full full-width">REZERVASYONU OLUŞTUR</button>
                </form>
                <script>function toggleM(){var v=document.querySelector('[name=musteri_tipi]').value; document.getElementById('divMevcut').style.display=v=='yeni'?'none':'block'; document.getElementById('divYeni').style.display=v=='yeni'?'grid':'none';}</script>
            </div>
        <?php endif; ?>

        <?php if ($sayfa === 'istekler'): ?>
            <div class="table-container">
                <div class="panel-header"><div class="panel-title">Bekleyen İstekler</div></div>
                <div style="padding:20px;">
                    <?php if(empty($bekleyenIstekler)): ?><p style="color:var(--success); font-weight:500;">Bekleyen istek yok, her şey yolunda!</p><?php else: foreach($bekleyenIstekler as $i): ?>
                        <div class="request-card">
                            <div><div style="font-weight:700; color:var(--text-primary);">Oda <?= $i['OdaNumarasi'] ?> <span style="color:var(--accent);">→ <?= h($i['IstekTuru']) ?></span></div><div style="color:var(--text-secondary); margin-top:5px;">"<?= h($i['Aciklama']) ?>"</div><small style="color:#94a3b8;"><?= h($i['Isim']) ?> • <?= date('H:i', strtotime($i['IstekZamani'])) ?></small></div>
                            <form method="POST"><input type="hidden" name="action" value="istek_tamamla"><input type="hidden" name="istek_id" value="<?= $i['IstekID'] ?>"><button class="btn btn-green">Tamamla</button></form>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($sayfa === 'muhasebe'): ?>
            <div class="table-container"><div class="panel-header" style="border-left:4px solid var(--danger);"><div class="panel-title" style="color:var(--danger);">Tahsilat Bekleyenler</div></div><table><thead><tr><th>Müşteri</th><th>Tutar</th><th>İşlem</th></tr></thead><tbody><?php foreach($borclular as $b): ?><tr><td><b><?= h($b['MusteriAd']) ?></b></td><td><?= money_tr($b['GenelToplam']) ?></td><td><form method="POST"><input type="hidden" name="action" value="tahsil_et"><input type="hidden" name="fis_id" value="<?= $b['FisID'] ?>"><input type="hidden" name="tutar" value="<?= $b['GenelToplam'] ?>"><button class="btn btn-blue"><i class="fas fa-hand-holding-usd"></i> Nakit Al</button></form></td></tr><?php endforeach; ?></tbody></table></div>
        <?php endif; ?>

        <?php if ($sayfa === 'personel'): ?>
            <div style="display:flex; justify-content:flex-end; margin-bottom:20px;"><button onclick="document.getElementById('staffModal').style.display='flex'" class="btn btn-full" style="width:auto;"><i class="fas fa-user-plus"></i> Yeni Personel</button></div>
            <div class="table-container"><div class="panel-header"><div class="panel-title">Personel Listesi</div></div><table><thead><tr><th>Ad Soyad</th><th>Rol</th><th>Kullanıcı Adı</th></tr></thead><tbody><?php foreach($personeller as $p): ?><tr><td><?= h($p['KullaniciAdi'].' '.$p['KullaniciSoyadi']) ?></td><td><span class="badge Aktif"><?= h($p['KullaniciRolu']) ?></span></td><td>@<?= h($p['KullaniciAdi']) ?></td></tr><?php endforeach; ?></tbody></table></div>
            <div id="staffModal" class="modal-overlay"><div class="modal-box"><span class="close-modal" onclick="document.getElementById('staffModal').style.display='none'">&times;</span><h3>Yeni Personel</h3><form method="POST" class="staff-form" style="margin-top:20px;"><input type="hidden" name="action" value="add_staff"><input type="text" name="ad" placeholder="Ad" class="form-control" required><input type="text" name="soyad" placeholder="Soyad" class="form-control" required><input type="text" name="rol" placeholder="Rol" class="form-control full-width" required><input type="email" name="email" placeholder="Email" class="form-control full-width" required><input type="password" name="sifre" placeholder="Şifre" class="form-control full-width" required><button class="btn-full full-width">KAYDET</button></form></div></div>
        <?php endif; ?>
        
        <?php if ($sayfa === 'yorumlar'): ?><div class="table-container"><div class="panel-header"><div class="panel-title">Geri Bildirimler</div></div><table><?php foreach($yorumlar as $y): ?><tr><td width="200"><b><?= h($y['Isim']) ?></b></td><td width="100"><?= yildizla($y['Puan']) ?></td><td style="color:var(--text-secondary);">"<?= h($y['Yorum']) ?>"</td></tr><?php endforeach; ?></table></div><?php endif; ?>
    </div>
</body>
</html>