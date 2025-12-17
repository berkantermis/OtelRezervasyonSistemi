<?php
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_login();

$u          = current_user();
$isPersonel = (bool)($u['personelMi'] ?? false);
if ($isPersonel) {
    header('Location: dashboard.php');
    exit;
}

// Oda uygunluk kontrolu
function find_available_room(int $odaTipiId, int $kisiSayisi, string $giris, string $cikis): ?array {
    $pdo = db();
    $odaStmt = $pdo->prepare("
        SELECT TOP 1 O.OdaID, O.OdaKapasitesi
        FROM Odalar O
        WHERE O.OdaTipiID = :t
          AND O.OdaKapasitesi >= :kisi
          AND NOT EXISTS (
                SELECT 1 FROM Rezervasyonlar R
                WHERE R.OdaID = O.OdaID
                  AND ISNULL(R.RezervasyonDurum, N'Aktif') NOT IN (N'İptal', N'Iptal')
                  AND R.GirisTarihi < :cikis
                  AND R.CikisTarihi > :giris
          )
        ORDER BY O.OdaID ASC
    ");
    $odaStmt->execute([':t' => $odaTipiId, ':kisi' => $kisiSayisi, ':giris' => $giris, ':cikis' => $cikis]);
    $oda = $odaStmt->fetch();
    return $oda ?: null;
}

function build_summary(array $pending): array {
    $pdo   = db();
    $oda   = null;
    $hList = [];
    try {
        $stmt = $pdo->prepare("SELECT OdaTipiAdi, OdaTipiFiyat FROM OdaTipi WHERE OdaTipiID=:id");
        $stmt->execute([':id' => $pending['oda_tipi']]);
        $oda = $stmt->fetch();
        if (!empty($pending['hizmetler'])) {
            $in    = implode(',', array_fill(0, count($pending['hizmetler']), '?'));
            $hStmt = $pdo->prepare("SELECT HizmetID, HizmetAdi, HizmetFiyati FROM Hizmetler WHERE HizmetID IN ($in)");
            $hStmt->execute($pending['hizmetler']);
            $hList = $hStmt->fetchAll();
        }
    } catch (Exception $e) {
    }
    return [
        'oda'       => $oda,
        'hizmetler' => $hList,
    ];
}

function recalc_fis_discount(PDO $pdo, int $rezId, int $fisId): ?array {
    $base = $pdo->prepare("
        SELECT R.GirisTarihi, R.CikisTarihi, R.MusteriID, OT.OdaTipiFiyat, F.KdvOrani
        FROM Rezervasyonlar R
        JOIN Odalar O ON O.OdaID = R.OdaID
        JOIN OdaTipi OT ON OT.OdaTipiID = O.OdaTipiID
        JOIN Fisler F ON F.RezervasyonID = R.RezervasyonID
        WHERE R.RezervasyonID = :r AND F.FisID = :f
    ");
    $base->execute([':r' => $rezId, ':f' => $fisId]);
    $row = $base->fetch();
    if (!$row) {
        return null;
    }
    $gece = (int)max(1, (strtotime((string)$row['CikisTarihi']) - strtotime((string)$row['GirisTarihi'])) / 86400);

    $kisiler = $pdo->prepare("SELECT Yas FROM RezervasyonKisileri WHERE RezervasyonID = :r");
    $kisiler->execute([':r' => $rezId]);
    $yaslar = $kisiler->fetchAll(PDO::FETCH_COLUMN);

    $toplamKatsayi = 1.0; // ana kullanici
    $kisiTamSayi   = 1;
    foreach ($yaslar as $yas) {
        $kisiTamSayi++;
        if ($yas === null || $yas === '') {
            $toplamKatsayi += 1.0;
            continue;
        }
        $y = (int)$yas;
        if ($y < 7) {
            $toplamKatsayi += 0.0;
        } elseif ($y <= 12) {
            $toplamKatsayi += 0.5;
        } elseif ($y < 18) {
            $toplamKatsayi += 0.8;
        } else {
            $toplamKatsayi += 1.0;
        }
    }

    $odaFiyat  = (float)$row['OdaTipiFiyat'];
    $kdvOrani  = $row['KdvOrani'] !== null ? (float)$row['KdvOrani'] : 10.0;
    $konakAra  = $gece * $odaFiyat * $toplamKatsayi;
    $tamFiyat  = $gece * $odaFiyat * $kisiTamSayi;
    $indirim   = $tamFiyat - $konakAra;

    // Personel indirimi ekle
    $pers = $pdo->prepare("SELECT 1 FROM Kullanicilar WHERE MusteriID = :m AND PersonelMi = 1 AND PersonelID IS NOT NULL");
    $pers->execute([':m' => (int)$row['MusteriID']]);
    if ($pers->fetch()) {
        $indirim += ($konakAra * 0.25);
    }

    $kdvTutar    = round($konakAra * $kdvOrani / 100.0, 2);
    $genelToplam = $konakAra + $kdvTutar - $indirim;
    if ($genelToplam < 0) {
        $genelToplam = 0;
    }

    $up = $pdo->prepare("UPDATE Fisler SET AraToplam = :ara, IndirimTutar = :ind, KdvTutar = :kdv, GenelToplam = :genel WHERE FisID = :f");
    $up->execute([
        ':ara'   => $konakAra,
        ':ind'   => $indirim,
        ':kdv'   => $kdvTutar,
        ':genel' => $genelToplam,
        ':f'     => $fisId,
    ]);

    return [
        'AraToplam'   => $konakAra,
        'IndirimTutar'=> $indirim,
        'KdvTutar'    => $kdvTutar,
        'GenelToplam' => $genelToplam,
    ];
}

$message      = '';
$error        = '';
$success      = false;
$fisId        = null;
$rezId        = null;
$pending      = $_SESSION['pending_rez'] ?? null;
$summary      = null;
$stage        = $_POST['stage'] ?? '';
$odemeYontemi = $_POST['odeme_yontemi'] ?? '';

if ($stage === 'start') {
    $_SESSION['pending_rez'] = null;
    $pending = null;
    $odaTipiId       = (int)($_POST['oda_tipi'] ?? 0);
    $giris           = $_POST['giris'] ?? '';
    $cikis           = $_POST['cikis'] ?? '';
    $seciliHizmetler = array_map('intval', $_POST['hizmetler'] ?? []);
    $misafirAd       = $_POST['misafir_ad'] ?? [];
    $misafirSoy      = $_POST['misafir_soyad'] ?? [];
    $misafirYas      = $_POST['misafir_yas'] ?? [];
    $misafirIliski   = $_POST['misafir_iliski'] ?? [];
    $today           = date('Y-m-d');

    $kisiSayisi = 1;
    foreach ($misafirAd as $idx => $ad) {
        if (trim((string)$ad) !== '') {
            $kisiSayisi++;
        }
    }

    if (!$odaTipiId || !$giris || !$cikis) {
        $error = 'Oda tipi ve tarih alanları zorunludur.';
    } elseif (strtotime($giris) < strtotime($today)) {
        $error = 'Giriş tarihi bugünden önce olamaz.';
    } elseif (strtotime($giris) >= strtotime($cikis)) {
        $error = 'Çıkış tarihi giriş tarihinden sonra olmalıdır.';
    } else {
        $oda = find_available_room($odaTipiId, $kisiSayisi, $giris, $cikis);
        if (!$oda) {
            $error = 'Seçilen kriterlerde uygun oda bulunamadı.';
        } else {
            $pendMisafir = [];
            foreach ($misafirAd as $i => $ad) {
                $ad  = trim((string)$ad);
                $soy = trim((string)($misafirSoy[$i] ?? ''));
                if ($ad === '' && $soy === '') {
                    continue;
                }
                $pendMisafir[] = [
                    'ad'     => $ad,
                    'soyad'  => $soy,
                    'yas'    => ($misafirYas[$i] ?? '') !== '' ? (int)$misafirYas[$i] : null,
                    'iliski' => trim((string)($misafirIliski[$i] ?? '')),
                ];
            }
            $pending = [
                'oda_tipi'    => $odaTipiId,
                'giris'       => $giris,
                'cikis'       => $cikis,
                'hizmetler'   => $seciliHizmetler,
                'misafirler'  => $pendMisafir,
                'kisi_sayisi' => $kisiSayisi,
                'prepared_at' => time(),
            ];
            $_SESSION['pending_rez'] = $pending;
            $message = 'Oda ve tarih bilgileriniz kaydedildi. Ödeme yöntemini seçin.';
        }
    }
} elseif ($stage === 'pay') {
    if (!$pending) {
        header('Location: dashboard.php?tab=rez-create');
        exit;
    }
    $odemeYontemi = $_POST['odeme_yontemi'] ?? '';
    $kartSahibi   = trim($_POST['kart_sahibi'] ?? '');
    $kartNo       = preg_replace('/\s+/', '', $_POST['kart_no'] ?? '');
    $expAy        = trim($_POST['exp_ay'] ?? '');
    $expYil       = trim($_POST['exp_yil'] ?? '');
    $cvv          = trim($_POST['cvv'] ?? '');
    $bankaAdi     = trim($_POST['banka_adi'] ?? '');

    if (!in_array($odemeYontemi, ['nakit', 'kart', 'eft'], true)) {
        $error = 'Lütfen bir ödeme yöntemi seçin.';
    } elseif ($odemeYontemi !== 'nakit') {
        if ($kartSahibi === '' || $kartNo === '' || $expAy === '' || $expYil === '' || $cvv === '') {
            $error = 'Kart bilgileri eksik.';
        } elseif (!preg_match('/^\d{16}$/', $kartNo)) {
            $error = 'Kart numarası 16 haneli olmalıdır.';
        } elseif (!preg_match('/^\d{2}$/', $expAy) || (int)$expAy < 1 || (int)$expAy > 12) {
            $error = 'Ay alanı 01-12 aralığında olmalıdır.';
        } elseif (!preg_match('/^\d{2}$/', $expYil)) {
            $error = 'Yıl alanı iki haneli olmalıdır.';
        } elseif (!preg_match('/^\d{3}$/', $cvv)) {
            $error = 'CVV 3 haneli olmalıdır.';
        } else {
            $expiry = DateTime::createFromFormat('Y-m-d H:i:s', '20' . $expYil . '-' . $expAy . '-01 00:00:00');
            if (!$expiry) {
                $error = 'Geçersiz son kullanma tarihi.';
            } else {
                $expiry->modify('last day of this month 23:59:59');
                if ($expiry < new DateTime()) {
                    $error = 'Kart son kullanma tarihi ileri bir tarih olmalıdır.';
                }
            }
        }
    }

    if ($error === '') {
        try {
            $pdo = db();
            $pdo->beginTransaction();

            $oda = find_available_room($pending['oda_tipi'], $pending['kisi_sayisi'], $pending['giris'], $pending['cikis']);
            if (!$oda) {
                throw new Exception('Seçilen oda tipi için uygun oda kalmadı. Lütfen farklı tarih deneyin.');
            }

            $rezIns = $pdo->prepare("INSERT INTO Rezervasyonlar (MusteriID, OdaID, GirisTarihi, CikisTarihi, RezervasyonDurum) VALUES (:m,:o,:g,:c,N'Aktif')");
            $rezIns->execute([
                ':m' => (int)$u['musteriId'],
                ':o' => (int)$oda['OdaID'],
                ':g' => $pending['giris'],
                ':c' => $pending['cikis'],
            ]);
            $rezId = (int)$pdo->lastInsertId();

            if (!empty($pending['misafirler'])) {
                $rk = $pdo->prepare("INSERT INTO RezervasyonKisileri (RezervasyonID, Ad, Soyad, Yas, IliskiTipi) VALUES (:r,:a,:s,:y,:i)");
                foreach ($pending['misafirler'] as $mis) {
                    $rk->execute([
                        ':r' => $rezId,
                        ':a' => $mis['ad'],
                        ':s' => $mis['soyad'],
                        ':y' => $mis['yas'],
                        ':i' => $mis['iliski'] === '' ? null : $mis['iliski'],
                    ]);
                }
            }

            if (!empty($pending['hizmetler'])) {
                $rh = $pdo->prepare("INSERT INTO RezervasyonHizmetleri (RezervasyonID, HizmetID) VALUES (:r,:h)");
                foreach ($pending['hizmetler'] as $hid) {
                    $rh->execute([':r' => $rezId, ':h' => (int)$hid]);
                }
            }

            if ($odemeYontemi !== 'nakit') {
                $fisProc = $pdo->prepare("EXEC sp_OlusturKonaklamaFisi @RezervasyonID=:r");
                $fisProc->execute([':r' => $rezId]);

                $fisRowStmt = $pdo->prepare("SELECT TOP 1 FisID, GenelToplam FROM Fisler WHERE RezervasyonID=:r ORDER BY FisID DESC");
                $fisRowStmt->execute([':r' => $rezId]);
                $fisRow = $fisRowStmt->fetch();
                if (!$fisRow) {
                    throw new Exception('Fiş oluşturulamadı.');
                }
                $recalc = recalc_fis_discount($pdo, $rezId, (int)$fisRow['FisID']);
                if ($recalc) {
                    $fisRow = array_merge($fisRow, $recalc);
                }
                $fisId = (int)$fisRow['FisID'];
                $son4  = substr($kartNo, -4);
                $odemeIns = $pdo->prepare("
                    INSERT INTO Odemeler (FisID, OdemeTarihi, OdemeYontemi, Tutar, KartSon4, KartSahibiAd, BankaAdi, IslemRefNo)
                    VALUES (:fis, GETDATE(), :yontem, :tutar, :son4, :sahip, :banka, :ref)
                ");
                $odemeIns->execute([
                    ':fis'    => $fisId,
                    ':yontem' => $odemeYontemi === 'kart' ? 'Kredi Kartı' : 'Havale/EFT',
                    ':tutar'  => (float)($fisRow['GenelToplam'] ?? 0),
                    ':son4'   => $son4,
                    ':sahip'  => $kartSahibi,
                    ':banka'  => $odemeYontemi === 'eft' ? ($bankaAdi !== '' ? $bankaAdi : 'Havale/EFT') : null,
                    ':ref'    => 'OD' . strtoupper(bin2hex(random_bytes(4))),
                ]);
                $message = 'Ödemeniz alındı, rezervasyonunuz oluşturuldu.';
            } else {
                $message = 'Rezervasyonunuz oluşturuldu. Ödemeyi çıkışta tamamlayabilirsiniz.';
            }

            $pdo->commit();
            $success = true;
            $_SESSION['pending_rez'] = null;
            unset($_SESSION['pending_rez']);
        } catch (Exception $ex) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'İşlem tamamlanamadı: ' . $ex->getMessage();
        }
    }
}

if ($pending) {
    $summary = build_summary($pending);
} else {
    $summary = ['oda' => null, 'hizmetler' => []];
}

if (!$pending && !$success && $stage !== 'start') {
    header('Location: dashboard.php?tab=rez-create');
    exit;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8" />
  <title>Ödeme</title>
  <link rel="stylesheet" href="assets/style.css" />
</head>
<body>
<div class="content" style="max-width: 960px; margin: 24px auto;">
  <a class="btn-link" href="dashboard.php?tab=rez-create">← Rezervasyona dön</a>

  <?php if ($message): ?><div class="alert success"><?= h($message) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert danger"><?= h($error) ?></div><?php endif; ?>

  <?php if ($success): ?>
    <div class="card" style="margin-top:12px;">
      <h2>İşlem Tamamlandı</h2>
      <p>Rezervasyonunuz başarıyla oluşturuldu.</p>
      <?php if ($fisId): ?>
        <a class="btn-link" href="fis.php?fis_id=<?= $fisId ?>">Fişi görüntüle</a>
      <?php else: ?>
        <p>Ödeme yöntemi: Nakit. Fiş çıkışta oluşturulacaktır.</p>
      <?php endif; ?>
      <a class="btn-link" href="dashboard.php">Panele dön</a>
    </div>
  <?php elseif (!$pending): ?>
    <div class="card" style="margin-top:12px;">
      <h2>Rezervasyon Bulunamadı</h2>
      <p>Ödeme adımı için gerekli rezervasyon bilgisi alınamadı. Lütfen yeniden seçim yapın.</p>
      <a class="btn-link" href="dashboard.php?tab=rez-create">Rezervasyon sayfasına dön</a>
    </div>
  <?php else: ?>
    <div class="cards">
      <div class="card">
        <h3>Oda Bilgisi</h3>
        <div class="big" style="font-size:18px;"><?= h($summary['oda']['OdaTipiAdi'] ?? 'Seçilmedi') ?></div>
        <div style="color:#6b7280;">Giriş: <?= h($pending['giris'] ?? '') ?> - Çıkış: <?= h($pending['cikis'] ?? '') ?></div>
        <div style="color:#6b7280; margin-top:4px;">Kişi: <?= h($pending['kisi_sayisi'] ?? 1) ?></div>
      </div>
      <div class="card">
        <h3>Hizmetler</h3>
        <?php if (!empty($summary['hizmetler'])): ?>
          <ul style="padding-left:18px; margin: 6px 0 0;">
            <?php foreach ($summary['hizmetler'] as $h): ?>
              <li><?= h($h['HizmetAdi']) ?> (<?= money_tr($h['HizmetFiyati']) ?>)</li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <p style="margin:4px 0;">Ek hizmet seçilmedi.</p>
        <?php endif; ?>
      </div>
    </div>

    <div class="card" style="margin-top:16px;">
      <h2>Ödeme Yöntemi</h2>
      <form method="POST" style="display:flex; flex-direction:column; gap:12px;">
        <input type="hidden" name="stage" value="pay" />
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
          <label class="chip">
            <input type="radio" name="odeme_yontemi" value="nakit" <?= $odemeYontemi === 'nakit' ? 'checked' : '' ?> required /> Nakit (çıkışta ödeme)
          </label>
          <label class="chip">
            <input type="radio" name="odeme_yontemi" value="kart" <?= $odemeYontemi === 'kart' ? 'checked' : '' ?> required /> Kredi Kartı
          </label>
          <label class="chip">
            <input type="radio" name="odeme_yontemi" value="eft" <?= $odemeYontemi === 'eft' ? 'checked' : '' ?> required /> Havale / EFT
          </label>
        </div>

        <div id="card-fields" <?= ($odemeYontemi === 'nakit') ? 'style="display:none;"' : '' ?>>
          <div class="form-row">
            <div class="field">
              <label>Kart Sahibi</label>
              <input class="input" type="text" name="kart_sahibi" value="<?= h($_POST['kart_sahibi'] ?? '') ?>" placeholder="Ad Soyad" />
            </div>
            <div class="field">
              <label>Kart Numarası</label>
              <input class="input" type="text" name="kart_no" value="<?= h($_POST['kart_no'] ?? '') ?>" maxlength="19" placeholder="16 haneli" />
            </div>
          </div>
          <div class="form-row">
            <div class="field">
              <label>Son Kullanma</label>
              <div style="display:flex; gap:8px;">
                <select class="input" name="exp_ay" style="flex:1; background:#fff;">
                  <option value="">Ay</option>
                  <?php for ($i=1; $i<=12; $i++):
                    $val = str_pad((string)$i, 2, '0', STR_PAD_LEFT);
                  ?>
                    <option value="<?= $val ?>" <?= (($expAy ?? '') === $val) ? 'selected' : '' ?>><?= $val ?></option>
                  <?php endfor; ?>
                </select>
                <select class="input" name="exp_yil" style="flex:1; background:#fff;">
                  <option value="">Yıl</option>
                  <?php $currentYear = (int)date('y'); for ($y = $currentYear; $y <= $currentYear + 10; $y++): $yy = str_pad((string)$y, 2, '0', STR_PAD_LEFT); ?>
                    <option value="<?= $yy ?>" <?= (($expYil ?? '') === $yy) ? 'selected' : '' ?>><?= $yy ?></option>
                  <?php endfor; ?>
                </select>
              </div>
            </div>
            <div class="field">
              <label>CVV</label>
              <input class="input" type="text" name="cvv" value="<?= h($_POST['cvv'] ?? '') ?>" maxlength="3" placeholder="3 hane" />
            </div>
          </div>
          <div class="field">
            <label>Banka Adı (Havale/EFT için)</label>
            <input class="input" type="text" name="banka_adi" value="<?= h($_POST['banka_adi'] ?? '') ?>" placeholder="Opsiyonel" />
          </div>
        </div>
        <button class="btn" type="submit">Ödemeyi Tamamla</button>
      </form>
    </div>
  <?php endif; ?>
</div>
</body>
<script>
(function() {
  const radios = document.querySelectorAll('input[name="odeme_yontemi"]');
  const cardFields = document.getElementById('card-fields');
  function toggle() {
    const val = document.querySelector('input[name="odeme_yontemi"]:checked');
    if (!val) return;
    cardFields.style.display = val.value === 'nakit' ? 'none' : 'block';
  }
  radios.forEach(r => r.addEventListener('change', toggle));
  toggle();
})();
</script>
</html>
