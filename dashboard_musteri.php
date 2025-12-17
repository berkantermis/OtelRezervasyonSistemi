<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';

// GÜVENLİK
if (!current_user()) { header('Location: login.php'); exit; }
if (!empty($_SESSION['kullanici']['personelMi'])) { header('Location: dashboard.php'); exit; }

$musteriID = $_SESSION['kullanici']['musteriId'];
$message   = '';
$error     = '';
$sayfa     = $_GET['sayfa'] ?? 'panel';

$filterGiris = $_POST['giris_tarihi'] ?? date('Y-m-d');
$filterCikis = $_POST['cikis_tarihi'] ?? date('Y-m-d', strtotime('+1 day'));

// --- İŞLEMLER ---

// 1. REZERVASYON YAPMA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'rezervasyon_yap') {
    $giris = $_POST['giris_tarihi'];
    $cikis = $_POST['cikis_tarihi'];
    $odaTipiID = $_POST['oda_tipi_id'] ?? null;
    $odemeTuru = $_POST['odeme_turu'];
    
    if (!$odaTipiID) {
        $error = "Lütfen bir oda seçiniz.";
    } else {
        $secilenHizmetler = $_POST['hizmetler'] ?? []; 
        try {
            $db = db();
            // Müsait Oda Bul
            $sqlOdaBul = "SELECT TOP 1 O.OdaID FROM Odalar O WHERE O.OdaTipiID = ? AND O.OdaID NOT IN (SELECT R.OdaID FROM Rezervasyonlar R WHERE (R.GirisTarihi < ? AND R.CikisTarihi > ?) AND R.RezervasyonDurum <> 'İptal')";
            $stmtBul = $db->prepare($sqlOdaBul);
            $stmtBul->execute([$odaTipiID, $cikis, $giris]);
            $bulunanOda = $stmtBul->fetchColumn();

            if ($bulunanOda) {
                $db->beginTransaction();
                $stmtEkle = $db->prepare("INSERT INTO Rezervasyonlar (MusteriID, OdaID, GirisTarihi, CikisTarihi, RezervasyonDurum) VALUES (?, ?, ?, ?, 'Onay Bekliyor')");
                $stmtEkle->execute([$musteriID, $bulunanOda, $giris, $cikis]);
                $sonRezID = $db->lastInsertId();

                if (!empty($secilenHizmetler)) {
                    $stmtHizmet = $db->prepare("INSERT INTO RezervasyonHizmetleri (RezervasyonID, HizmetID) VALUES (?, ?)");
                    foreach ($secilenHizmetler as $hizmetID) { $stmtHizmet->execute([$sonRezID, $hizmetID]); }
                }
                
                $db->exec("EXEC sp_OlusturKonaklamaFisi @RezervasyonID = $sonRezID");

                if ($odemeTuru === 'online_kart') {
                    $fisID = $db->query("SELECT FisID FROM Fisler WHERE RezervasyonID = $sonRezID")->fetchColumn();
                    $toplamTutar = $db->query("SELECT GenelToplam FROM Fisler WHERE FisID = $fisID")->fetchColumn();
                    $stmtOdeme = $db->prepare("INSERT INTO Odemeler (FisID, OdemeTarihi, OdemeYontemi, Tutar) VALUES (?, GETDATE(), 'Kredi Kartı', ?)");
                    $stmtOdeme->execute([$fisID, $toplamTutar]);
                    $message = "Ödeme alındı! Rezervasyon onay bekliyor.";
                } else {
                    $message = "Talep alındı! Ödemeyi otelde yapabilirsiniz.";
                }
                $db->commit();
                $sayfa = 'rezervasyonlarim'; 
            } else {
                $error = "Seçilen tarihlerde bu oda dolu.";
            }
        } catch (Exception $e) { if ($db->inTransaction()) $db->rollBack(); $error = "Hata: " . $e->getMessage(); }
    }
}

// 2. RESEPSİYON İSTEK GÖNDERME
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'istek_gonder') {
    try {
        $stmt = db()->prepare("INSERT INTO MusteriIstekleri (RezervasyonID, IstekTuru, Aciklama) VALUES (?, ?, ?)");
        $stmt->execute([$_POST['rez_id'], $_POST['istek_turu'], $_POST['aciklama']]);
        $message = "Talebiniz iletildi.";
    } catch (Exception $e) { $error = "Hata: " . $e->getMessage(); }
}

// 3. YORUM YAPMA
if (isset($_POST['yorum_yap'])) {
    try {
        $stmt = db()->prepare("INSERT INTO Feedback (MusteriID, RezervasyonID, Puan, Yorum) VALUES (?, ?, ?, ?)");
        $stmt->execute([$musteriID, $_POST['rez_id'], $_POST['puan'], $_POST['yorum_metni']]);
        $message = "Yorum kaydedildi.";
    } catch (Exception $e) { $error = "Hata: " . $e->getMessage(); }
}

// --- VERİ ÇEKME ---
$hizmetler = db()->query("SELECT * FROM Hizmetler")->fetchAll();
$odaTipleri = db()->query("SELECT OT.*, (SELECT COUNT(*) FROM Odalar O WHERE O.OdaTipiID = OT.OdaTipiID AND O.OdaID NOT IN (SELECT R.OdaID FROM Rezervasyonlar R WHERE (R.GirisTarihi < '$filterCikis' AND R.CikisTarihi > '$filterGiris') AND R.RezervasyonDurum <> 'İptal')) as BosOdaSayisi, (SELECT TOP 1 OdaKapasitesi FROM Odalar WHERE OdaTipiID = OT.OdaTipiID) as Kapasite FROM OdaTipi OT")->fetchAll();

$aktifRezervasyonlar = db()->query("SELECT R.RezervasyonID, O.OdaNumarasi FROM Rezervasyonlar R JOIN Odalar O ON O.OdaID = R.OdaID WHERE R.MusteriID = $musteriID AND R.RezervasyonDurum IN ('Aktif', 'Onay Bekliyor') AND CAST(GETDATE() AS DATE) BETWEEN R.GirisTarihi AND R.CikisTarihi")->fetchAll();

$musteriBilgi = db()->query("SELECT * FROM Musteriler WHERE MusteriID = $musteriID")->fetch();
$kullaniciBilgi = db()->query("SELECT Email FROM Kullanicilar WHERE MusteriID = $musteriID")->fetch();
$toplamRez = db()->query("SELECT COUNT(*) FROM Rezervasyonlar WHERE MusteriID = $musteriID")->fetchColumn();
$toplamHarcama = db()->query("SELECT COALESCE(SUM(GenelToplam), 0) FROM Fisler F JOIN Rezervasyonlar R ON R.RezervasyonID = F.RezervasyonID WHERE R.MusteriID = $musteriID")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<title>Marun Otel | Müşteri Paneli</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
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
        --border: #e2e8f0;
    }
    
    * { box-sizing: border-box; }
    body { margin: 0; font-family: 'Inter', sans-serif; background: var(--bg-body); color: var(--text-primary); display: flex; height: 100vh; overflow: hidden; }
    
    /* SIDEBAR */
    .sidebar { width: 260px; background: var(--bg-sidebar); color: white; display: flex; flex-direction: column; padding: 25px; box-shadow: 4px 0 24px rgba(0,0,0,0.05); z-index: 10; }
    .brand { font-size: 1.5rem; font-weight: 700; margin-bottom: 40px; color: var(--accent); letter-spacing: 1px; display: flex; align-items: center; gap: 10px; }
    .nav-link { display: flex; align-items: center; gap: 12px; padding: 14px 15px; color: #94a3b8; text-decoration: none; margin-bottom: 5px; border-radius: 12px; transition: 0.3s; font-weight: 500; font-size: 0.95rem; }
    .nav-link:hover, .nav-link.active { background: rgba(255,255,255,0.1); color: white; }
    .nav-link.active { background: var(--accent); color: white; box-shadow: 0 4px 12px rgba(212, 163, 115, 0.3); }
    .nav-link i { font-size: 1.1rem; width: 20px; text-align: center; }
    .logout { margin-top: auto; color: #ef4444; background: rgba(239, 68, 68, 0.1); }
    .logout:hover { background: rgba(239, 68, 68, 0.2); color: #ef4444; }

    /* MAIN */
    .main { flex: 1; padding: 30px 40px; overflow-y: auto; }
    .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
    .welcome-text h2 { margin: 0; font-size: 1.8rem; font-weight: 700; color: var(--text-primary); }
    .welcome-text p { margin: 5px 0 0; color: var(--text-secondary); }
    .user-profile { display: flex; align-items: center; gap: 15px; background: var(--bg-card); padding: 8px 15px; border-radius: 50px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); border: 1px solid var(--border); }
    .user-avatar { width: 35px; height: 35px; background: var(--bg-sidebar); color: var(--accent); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; }

    /* CARDS & PANELS */
    .hero-card {
        background: linear-gradient(rgba(15, 23, 42, 0.6), rgba(15, 23, 42, 0.8)), url('images/otel1.jpg');
        background-size: cover; background-position: center;
        border-radius: 20px; padding: 50px; color: white; margin-bottom: 30px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.15); display: flex; flex-direction: column; justify-content: center;
    }
    .hero-card h1 { font-size: 2.5rem; margin: 0 0 15px 0; font-weight: 700; }
    .hero-card p { font-size: 1.1rem; opacity: 0.9; margin-bottom: 25px; max-width: 600px; }
    
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px; }
    .stat-box { background: var(--bg-card); padding: 25px; border-radius: 16px; border: 1px solid var(--border); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); transition: 0.3s; }
    .stat-box:hover { transform: translateY(-3px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); border-color: var(--accent); }
    .stat-title { font-size: 0.9rem; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 10px; }
    .stat-value { font-size: 2rem; font-weight: 700; color: var(--bg-sidebar); }

    /* FORMS */
    .content-card { background: var(--bg-card); padding: 30px; border-radius: 16px; border: 1px solid var(--border); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
    .form-group label { display: block; margin-bottom: 8px; font-weight: 500; color: var(--text-primary); font-size: 0.9rem; }
    .form-control { width: 100%; padding: 12px 15px; border: 1px solid var(--border); border-radius: 10px; font-size: 1rem; transition: 0.3s; background: #f8fafc; color: var(--text-primary); }
    .form-control:focus { outline: none; border-color: var(--accent); background: white; box-shadow: 0 0 0 3px rgba(212, 163, 115, 0.1); }
    
    .btn { padding: 12px 25px; border-radius: 10px; border: none; cursor: pointer; font-weight: 600; transition: 0.3s; font-size: 1rem; display: inline-flex; align-items: center; justify-content: center; gap: 8px; }
    .btn-primary { background: var(--accent); color: white; width: 100%; }
    .btn-primary:hover { background: var(--accent-hover); box-shadow: 0 4px 12px rgba(212, 163, 115, 0.4); }
    .btn-secondary { background: white; color: var(--text-primary); border: 1px solid var(--border); }

    /* ROOM CARDS */
    .room-scroller { display: flex; gap: 20px; overflow-x: auto; padding-bottom: 20px; scrollbar-width: thin; }
    .room-card-label { min-width: 320px; background: var(--bg-card); border-radius: 16px; overflow: hidden; cursor: pointer; border: 2px solid transparent; transition: 0.3s; position: relative; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
    .room-radio:checked + .room-card-label { border-color: var(--accent); box-shadow: 0 8px 25px rgba(212, 163, 115, 0.25); transform: translateY(-5px); }
    .room-img { height: 180px; width: 100%; object-fit: cover; }
    .room-info { padding: 20px; }
    .room-price { color: var(--accent); font-weight: 700; font-size: 1.2rem; margin-top: 5px; }
    .badge { position: absolute; top: 15px; right: 15px; padding: 6px 12px; border-radius: 30px; font-size: 0.75rem; font-weight: 700; color: white; }
    .bg-green { background: var(--success); } .bg-red { background: var(--danger); }

    /* MODAL */
    .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.8); z-index: 100; backdrop-filter: blur(5px); justify-content: center; align-items: center; }
    .modal-box { background: white; padding: 40px; border-radius: 20px; width: 450px; box-shadow: 0 20px 50px rgba(0,0,0,0.2); }

    .alert { padding: 15px; border-radius: 10px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
    .alert-success { background: rgba(16, 185, 129, 0.1); color: var(--success); border: 1px solid var(--success); }
    .alert-error { background: rgba(239, 68, 68, 0.1); color: var(--danger); border: 1px solid var(--danger); }
</style>
</head>
<body>

    <div class="sidebar">
        <div class="brand"><i class="fas fa-hotel"></i> MARUN OTEL</div>
        <a href="?sayfa=panel" class="nav-link <?= $sayfa=='panel'?'active':'' ?>"><i class="fas fa-home"></i> Genel Bakış</a>
        <a href="?sayfa=rezervasyon_yap" class="nav-link <?= $sayfa=='rezervasyon_yap'?'active':'' ?>"><i class="fas fa-calendar-plus"></i> Rezervasyon Yap</a>
        <a href="?sayfa=rezervasyonlarim" class="nav-link <?= $sayfa=='rezervasyonlarim'?'active':'' ?>"><i class="fas fa-calendar-check"></i> Rezervasyonlarım</a>
        <a href="?sayfa=resepsiyon" class="nav-link <?= $sayfa=='resepsiyon'?'active':'' ?>"><i class="fas fa-concierge-bell"></i> Dijital Resepsiyon</a>
        <a href="?sayfa=bilgilerim" class="nav-link <?= $sayfa=='bilgilerim'?'active':'' ?>"><i class="fas fa-user-circle"></i> Profilim</a>
        <a href="logout.php" class="nav-link logout"><i class="fas fa-sign-out-alt"></i> Çıkış Yap</a>
    </div>

    <div class="main">
        <div class="header">
            <div class="welcome-text">
                <h2>Hoş geldin, <?= h($musteriBilgi['Isim']) ?></h2>
                <p>Otel deneyimini yönetmek artık çok daha kolay.</p>
            </div>
            <div class="user-profile">
                <div class="user-avatar"><?= substr($musteriBilgi['Isim'],0,1) ?></div>
                <span style="font-weight:500; font-size:0.9rem;"><?= h($kullaniciBilgi['Email']) ?></span>
            </div>
        </div>

        <?php if($message): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= h($message) ?></div><?php endif; ?>
        <?php if($error): ?><div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?= h($error) ?></div><?php endif; ?>

        <?php if ($sayfa === 'panel'): ?>
            <div class="hero-card">
                <h1>Ayrıcalıklı Dünyaya Hoş Geldin.</h1>
                <p>Sizin için hazırladığımız özel teklifleri ve konforu keşfetmeye hazır mısınız?</p>
                <a href="?sayfa=rezervasyon_yap" class="btn btn-primary" style="width: auto; padding: 15px 40px;">Hemen Rezervasyon Yap</a>
            </div>
            <div class="stats-grid">
                <div class="stat-box">
                    <div class="stat-title">Toplam Konaklama</div>
                    <div class="stat-value"><?= $toplamRez ?> <small style="font-size:1rem; color:#94a3b8;">Kez</small></div>
                </div>
                <div class="stat-box">
                    <div class="stat-title">Toplam Harcama</div>
                    <div class="stat-value"><?= money_tr($toplamHarcama) ?></div>
                </div>
                <div class="stat-box" style="border-color: var(--accent);">
                    <div class="stat-title" style="color:var(--accent);">Sıradaki Tatilin</div>
                    <?php $nextRez = db()->query("SELECT TOP 1 * FROM Rezervasyonlar WHERE MusteriID=$musteriID AND RezervasyonDurum IN ('Aktif','Onay Bekliyor') ORDER BY GirisTarihi")->fetch(); ?>
                    <div class="stat-value" style="font-size:1.5rem;"><?= $nextRez ? date('d.m.Y', strtotime($nextRez['GirisTarihi'])) : 'Planlanmadı' ?></div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($sayfa === 'rezervasyon_yap'): ?>
            <form method="POST" id="rezForm">
                <input type="hidden" name="action" value="rezervasyon_yap">
                
                <h3 style="margin-bottom:15px; color:var(--text-secondary);">1. Tarih Seçimi</h3>
                <div class="content-card" style="display:flex; gap:20px; margin-bottom:30px;">
                    <div style="flex:1;"><label>Giriş Tarihi</label><input type="date" id="giris" name="giris_tarihi" value="<?= $filterGiris ?>" class="form-control" onchange="this.form.submit()"></div>
                    <div style="flex:1;"><label>Çıkış Tarihi</label><input type="date" id="cikis" name="cikis_tarihi" value="<?= $filterCikis ?>" class="form-control" onchange="this.form.submit()"></div>
                </div>

                <h3 style="margin-bottom:15px; color:var(--text-secondary);">2. Oda Seçimi</h3>
                <div class="room-scroller" style="margin-bottom:30px;">
                    <?php foreach ($odaTipleri as $oda): ?>
                        <?php 
                            $musait = $oda['BosOdaSayisi'] > 0;
                            $badge = $musait ? '<span class="badge bg-green">MÜSAİT</span>' : '<span class="badge bg-red">DOLU</span>';
                            $style = $musait ? '' : 'filter:grayscale(1); opacity:0.7;';
                        ?>
                        <label class="room-card-label" style="<?= $style ?>">
                            <input type="radio" name="oda_tipi_id" value="<?= $oda['OdaTipiID'] ?>" class="room-radio" style="display:none;" data-fiyat="<?= $oda['OdaTipiFiyat'] ?>" onchange="hesapla()" <?= $musait?'':'disabled' ?>>
                            <?= $badge ?>
                            <img src="<?= h($oda['GorselYolu'] ?? 'images/otel1.jpg') ?>" class="room-img" onerror="this.src='images/otel1.jpg'">
                            <div class="room-info">
                                <b style="font-size:1.1rem;"><?= h($oda['OdaTipiAdi']) ?></b>
                                <div class="room-price"><?= money_tr($oda['OdaTipiFiyat']) ?></div>
                                <div style="margin-top:5px; font-size:0.85rem; color:#64748b;"><i class="fas fa-user-friends"></i> <?= h($oda['Kapasite']) ?> Kişilik</div>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>

                <div class="content-card">
                    <h3 style="margin-top:0; margin-bottom:20px;">3. Ek Hizmetler & Ödeme</h3>
                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:15px; margin-bottom:30px;">
                        <?php foreach ($hizmetler as $h): ?>
                            <label style="background:#f8fafc; padding:15px; border-radius:10px; border:1px solid var(--border); cursor:pointer; display:flex; align-items:center; gap:10px;">
                                <input type="checkbox" name="hizmetler[]" value="<?= $h['HizmetID'] ?>" data-fiyat="<?= $h['HizmetFiyati'] ?>" onchange="hesapla()" style="width:18px; height:18px; accent-color:var(--accent);">
                                <div><div style="font-weight:600;"><?= h($h['HizmetAdi']) ?></div><small style="color:var(--accent);">+<?= money_tr($h['HizmetFiyati']) ?></small></div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    
                    <div style="background:#fff7ed; padding:20px; border-radius:12px; border:1px solid #ffedd5; margin-bottom:25px;">
                        <div style="display:flex; justify-content:space-between; margin-bottom:5px;"><span>Oda Fiyatı:</span><span id="txtOda" style="font-weight:600;">0 ₺</span></div>
                        <div style="display:flex; justify-content:space-between; margin-bottom:10px; padding-bottom:10px; border-bottom:1px solid rgba(0,0,0,0.05);"><span>Hizmetler:</span><span id="txtHizmet" style="font-weight:600;">0 ₺</span></div>
                        <div style="display:flex; justify-content:space-between; font-size:1.2rem; font-weight:700; color:var(--text-primary);"><span>TOPLAM:</span><span id="txtToplam">0 ₺</span></div>
                    </div>

                    <div style="display:flex; gap:15px; margin-bottom:25px;">
                        <label style="flex:1; border:2px solid var(--border); padding:15px; border-radius:12px; text-align:center; cursor:pointer; transition:0.2s;">
                            <input type="radio" name="odeme_turu" value="online_kart" id="pay_card" checked> <div style="font-weight:600; margin-top:5px;">💳 Hemen Öde</div>
                        </label>
                        <label style="flex:1; border:2px solid var(--border); padding:15px; border-radius:12px; text-align:center; cursor:pointer; transition:0.2s;">
                            <input type="radio" name="odeme_turu" value="otel_nakit" id="pay_cash"> <div style="font-weight:600; margin-top:5px;">💵 Otelde Öde</div>
                        </label>
                    </div>
                    
                    <button type="button" onclick="checkPayment()" class="btn btn-primary" style="padding:18px;">REZERVASYONU TAMAMLA</button>
                </div>
            </form>

            <div id="paymentOverlay" class="modal-overlay">
                <div class="modal-box">
                    <h3 style="margin-top:0;">Güvenli Ödeme</h3>
                    <p style="margin-bottom:20px; color:#64748b;">Toplam Tutar: <span id="modalTutar" style="color:var(--text-primary); font-weight:700;"></span></p>
                    <div class="form-group"><label>Kart Sahibi</label><input type="text" class="form-control" placeholder="Ad Soyad"></div>
                    <div class="form-group" style="margin-top:15px;"><label>Kart Numarası</label><input type="text" class="form-control" placeholder="0000 0000 0000 0000"></div>
                    <div style="display:flex; gap:15px; margin-top:15px;">
                        <div class="form-group" style="flex:1;"><label>SKT</label><input type="text" class="form-control" placeholder="AA/YY"></div>
                        <div class="form-group" style="flex:1;"><label>CVV</label><input type="text" class="form-control" placeholder="123"></div>
                    </div>
                    <button onclick="submitForm()" class="btn btn-primary" style="margin-top:25px;">Ödemeyi Onayla</button>
                    <button onclick="closeModal()" class="btn btn-secondary" style="width:100%; margin-top:10px;">İptal</button>
                </div>
            </div>

            <script>
                function hesapla() {
                    let gun = (new Date(document.getElementById('cikis').value) - new Date(document.getElementById('giris').value)) / 86400000;
                    if(gun<1) gun=1;
                    let odaFiyat = 0;
                    let secili = document.querySelector('input[name="oda_tipi_id"]:checked');
                    if(secili) odaFiyat = parseFloat(secili.dataset.fiyat) * gun;
                    let hizmetFiyat = 0;
                    document.querySelectorAll('input[name="hizmetler[]"]:checked').forEach(c => hizmetFiyat += parseFloat(c.dataset.fiyat));
                    document.getElementById('txtOda').innerText = odaFiyat.toLocaleString('tr-TR') + ' ₺';
                    document.getElementById('txtHizmet').innerText = hizmetFiyat.toLocaleString('tr-TR') + ' ₺';
                    document.getElementById('txtToplam').innerText = (odaFiyat+hizmetFiyat).toLocaleString('tr-TR') + ' ₺';
                    document.getElementById('modalTutar').innerText = (odaFiyat+hizmetFiyat).toLocaleString('tr-TR') + ' ₺';
                }
                function checkPayment() {
                    if(!document.querySelector('input[name="oda_tipi_id"]:checked')) { alert('Lütfen bir oda seçiniz.'); return; }
                    if(document.getElementById('pay_card').checked) document.getElementById('paymentOverlay').style.display='flex';
                    else if(confirm('Ödemeyi otelde nakit olarak yapacaksınız. Onaylıyor musunuz?')) submitForm();
                }
                function closeModal() { document.getElementById('paymentOverlay').style.display='none'; }
                function submitForm() { document.getElementById('rezForm').submit(); }
            </script>
        <?php endif; ?>

        <?php if ($sayfa === 'resepsiyon'): ?>
            <div class="content-card">
                <h2 style="margin-top:0;">🛎️ Dijital Resepsiyon</h2>
                <p style="color:#64748b; margin-bottom:25px;">Odanıza ekstra isteklerinizi buradan iletebilirsiniz.</p>
                <?php if(empty($aktifRezervasyonlar)): ?>
                    <div style="background:#fef2f2; color:#b91c1c; padding:20px; border-radius:10px; border:1px solid #fecaca; text-align:center;">
                        <i class="fas fa-door-closed" style="font-size:2rem; margin-bottom:10px; display:block;"></i>
                        Şu an aktif veya onaylanmış bir konaklamanız görünmüyor.
                    </div>
                <?php else: ?>
                    <form method="POST">
                        <input type="hidden" name="action" value="istek_gonder">
                        <div style="display:flex; gap:20px; margin-bottom:20px;">
                            <div class="form-group" style="flex:1;">
                                <label>Hangi Odadasınız?</label>
                                <select name="rez_id" class="form-control">
                                    <?php foreach($aktifRezervasyonlar as $rez): ?>
                                        <option value="<?= $rez['RezervasyonID'] ?>">Oda <?= $rez['OdaNumarasi'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group" style="flex:1;">
                                <label>İsteğiniz Nedir?</label>
                                <select name="istek_turu" class="form-control">
                                    <option>Oda Temizliği</option><option>Ekstra Havlu</option><option>Teknik Servis</option><option>Taksi</option><option>Diğer</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Notunuz (Opsiyonel)</label>
                            <textarea name="aciklama" class="form-control" rows="3" placeholder="Örn: 2 adet yüz havlusu rica ediyorum..."></textarea>
                        </div>
                        <button class="btn btn-primary" style="margin-top:15px;">İsteği Gönder</button>
                    </form>
                <?php endif; ?>
                
                <h3 style="margin-top:40px; border-bottom:1px solid var(--border); padding-bottom:10px;">Geçmiş İstekler</h3>
                <?php $istekler = db()->query("SELECT * FROM MusteriIstekleri WHERE RezervasyonID IN (SELECT RezervasyonID FROM Rezervasyonlar WHERE MusteriID=$musteriID) ORDER BY IstekZamani DESC")->fetchAll(); ?>
                <div style="margin-top:20px;">
                    <?php if(empty($istekler)): ?><p style="color:#94a3b8;">Henüz bir istekte bulunmadınız.</p><?php else: ?>
                    <?php foreach($istekler as $i): ?>
                        <div style="background:#f8fafc; padding:15px; margin-bottom:10px; border-radius:10px; border:1px solid var(--border); display:flex; justify-content:space-between; align-items:center;">
                            <div>
                                <div style="font-weight:600;"><?= h($i['IstekTuru']) ?></div>
                                <small style="color:#64748b;"><?= h($i['Aciklama']) ?></small>
                            </div>
                            <div style="text-align:right;">
                                <span class="badge <?= $i['Durum']=='Bekliyor'?'bg-red':'bg-green' ?>" style="position:static; display:inline-block;"><?= $i['Durum'] ?></span>
                                <div style="font-size:0.75rem; color:#94a3b8; margin-top:5px;"><?= date('d.m H:i', strtotime($i['IstekZamani'])) ?></div>
                            </div>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($sayfa === 'rezervasyonlarim'): ?>
            <h2 style="margin-bottom:20px;">Rezervasyonlarım</h2>
            <?php $rezListesi = db()->query("SELECT R.*, O.OdaNumarasi, OT.OdaTipiAdi, F.GenelToplam, F.OdendiMi FROM Rezervasyonlar R JOIN Odalar O ON O.OdaID = R.OdaID JOIN OdaTipi OT ON OT.OdaTipiID = O.OdaTipiID LEFT JOIN Fisler F ON F.RezervasyonID = R.RezervasyonID WHERE R.MusteriID = $musteriID ORDER BY R.GirisTarihi DESC")->fetchAll(); ?>
            <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;">
                <?php foreach($rezListesi as $r): ?>
                    <div class="content-card" style="padding:20px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                            <span class="badge <?= $r['RezervasyonDurum']=='Onay Bekliyor'?'bg-red':'bg-green' ?>" style="position:static;"><?= $r['RezervasyonDurum'] ?></span>
                            <span style="font-weight:700; color:var(--text-primary);"><?= money_tr($r['GenelToplam']) ?></span>
                        </div>
                        <h3 style="margin:0 0 5px 0;">Oda <?= h($r['OdaNumarasi']) ?></h3>
                        <p style="color:#64748b; font-size:0.9rem; margin-bottom:15px;"><?= h($r['OdaTipiAdi']) ?></p>
                        <div style="background:#f1f5f9; padding:10px; border-radius:8px; font-size:0.85rem; color:#475569; display:flex; gap:10px; align-items:center;">
                            <i class="far fa-calendar-alt"></i> <?= date('d.m.Y', strtotime($r['GirisTarihi'])) ?> - <?= date('d.m.Y', strtotime($r['CikisTarihi'])) ?>
                        </div>
                        <div style="margin-top:20px; display:flex; gap:10px;">
                            <a href="fis.php?rez_id=<?= $r['RezervasyonID'] ?>" target="_blank" class="btn btn-secondary" style="flex:1; padding:8px; font-size:0.9rem;">Fiş</a>
                            <?php if($r['RezervasyonDurum'] == 'Tamamlandı'): ?> 
                                <a href="?sayfa=rezervasyonlarim&yorum=<?= $r['RezervasyonID'] ?>" class="btn btn-primary" style="flex:1; padding:8px; font-size:0.9rem;">Yorum Yap</a>
                            <?php endif; ?>
                        </div>
                        <?php if(isset($_GET['yorum']) && $_GET['yorum']==$r['RezervasyonID']): ?>
                            <form method="POST" style="margin-top:15px; border-top:1px solid var(--border); padding-top:15px;">
                                <input type="hidden" name="yorum_yap" value="1"><input type="hidden" name="rez_id" value="<?= $r['RezervasyonID'] ?>">
                                <div style="display:flex; gap:10px;">
                                    <input type="number" name="puan" min="1" max="5" value="5" class="form-control" style="width:70px;">
                                    <input type="text" name="yorum_metni" placeholder="Yorumunuz..." class="form-control">
                                    <button class="btn btn-primary"><i class="fas fa-paper-plane"></i></button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($sayfa === 'bilgilerim'): ?>
            <div class="content-card" style="max-width:700px;">
                <h2 style="margin-top:0; margin-bottom:25px; border-bottom:1px solid var(--border); padding-bottom:15px;">Profil Bilgileri</h2>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-bottom:20px;">
                    <div class="form-group"><label>Ad</label><input type="text" value="<?= h($musteriBilgi['Isim']) ?>" class="form-control" disabled></div>
                    <div class="form-group"><label>Soyad</label><input type="text" value="<?= h($musteriBilgi['Soyisim']) ?>" class="form-control" disabled></div>
                </div>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-bottom:20px;">
                    <div class="form-group"><label>Telefon</label><input type="text" value="<?= h($musteriBilgi['TelefonNo'] ?? '-') ?>" class="form-control" disabled></div>
                    <div class="form-group"><label>E-Posta</label><input type="text" value="<?= h($kullaniciBilgi['Email']) ?>" class="form-control" disabled></div>
                </div>
                <div class="form-group"><label>Kayıt Tarihi</label><input type="text" value="<?= date('d.m.Y', strtotime($musteriBilgi['KayitTarihi'])) ?>" class="form-control" disabled></div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>