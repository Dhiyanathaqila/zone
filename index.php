<?php
session_start();

$fav_file = __DIR__ . "/favorites.txt";
$favorites = file_exists($fav_file)
    ? array_filter(array_map("trim", file($fav_file)))
    : [];

/* OOP Log */
class TimeConverterLog {
    private $file;
    public function __construct($filePath) { $this->file = $filePath; }
    public function add($text) { file_put_contents($this->file, $text . PHP_EOL, FILE_APPEND); }
    public function readAll() { return file_exists($this->file) ? array_reverse(file($this->file, FILE_IGNORE_NEW_LINES)) : []; }
    public function clear() { file_put_contents($this->file, ""); }
}

$Log = new TimeConverterLog(__DIR__ . "/history.txt");

$zones = [
    "Asia/Jakarta"        => "Jakarta (UTC+7)",
    "Asia/Makassar"       => "Makassar (UTC+8)",
    "Asia/Jayapura"       => "Jayapura (UTC+9)",
    "Asia/Tokyo"          => "Tokyo (UTC+9)",
    "Europe/London"       => "London (UTC+0)",
    "Europe/Berlin"       => "Berlin (UTC+1)",
    "America/New_York"    => "New York (UTC-5)",
    "America/Los_Angeles" => "Los Angeles (UTC-8)",
    "Australia/Sydney"    => "Sydney (UTC+10)",
    "Asia/Dubai"          => "Dubai (UTC+4)",
    "Asia/Kolkata"        => "India (UTC+5:30)"
];

$history_file = __DIR__ . "/history.txt";

function zone_offset($zone) {
    $tz = new DateTimeZone($zone);
    return $tz->getOffset(new DateTime());
}

$convert_output = "";
$calendar_html  = "";
$daydiff_html   = "";
$format_output  = "";

/* Proses Konversi */
if (isset($_POST['convert'])) {

    $from = $_POST['from_zone'];
    $to   = $_POST['to_zone'];
    $date = $_POST['date_input'];
    $time = $_POST['time_input'] ?: "00:00";
    $input = "$date $time";

    if ($from === $to) {
        $convert_output = "<div class='output' style='border-left-color:#ff9800'>⚠️ Zona waktu asal dan tujuan sama — tidak ada perubahan waktu.</div>";
    } else {
        $dt = new DateTime($input, new DateTimeZone($from));
        $dt2 = clone $dt;
        $dt2->setTimezone(new DateTimeZone($to));

        $offset_from = zone_offset($from) / 3600;
        $offset_to   = zone_offset($to)   / 3600;
        $selisih_jam = $offset_to - $offset_from;
        $selisih_format = ($selisih_jam >= 0 ? "+" : "") . $selisih_jam . " jam";

        $convert_output = "
        <table border='1' cellpadding='8' style='border-collapse:collapse;width:100%;background:#fbf8ff'>
            <tr><th align='left'>Dari Zona</th><td>{$zones[$from]}</td></tr>
            <tr><th align='left'>Ke Zona</th><td>{$zones[$to]}</td></tr>
            <tr><th align='left'>Input</th><td>$input</td></tr>
            <tr><th align='left'>Hasil Konversi</th><td>".$dt2->format("Y-m-d H:i:s")."</td></tr>
            <tr><th align='left'>Selisih Zona Waktu</th><td><b>$selisih_format</b></td></tr>
        </table>";

        $hari_asal = $dt->format("Y-m-d");
        $hari_tuju = $dt2->format("Y-m-d");

        if ($hari_tuju > $hari_asal) {
            $convert_output .= "<div class='output' style='border-left-color:#2ecc71'>🔔 Waktu tujuan bergeser ke <b>hari berikutnya (+1)</b>.</div>";
        }
        else if ($hari_tuju < $hari_asal) {
            $convert_output .= "<div class='output' style='border-left-color:#e74c3c'>🔔 Waktu tujuan mundur ke <b>hari sebelumnya (–1)</b>.</div>";
        }
    }

    $line = date("Y-m-d H:i:s")
        . " | $from ➜ $to"
        . " | Hasil: " . (isset($dt2) ? $dt2->format("Y-m-d H:i") : $input)
        . " | Selisih: " . (isset($selisih_format) ? $selisih_format : "-");
    $Log->add($line);

    /* kalender */
    if (isset($dt2)) {
        $y = (int)$dt2->format("Y");
        $m = (int)$dt2->format("m");
        $selected = (int)$dt2->format("d");

        $first = new DateTime("$y-$m-01");
        $start_weekday = (int)$first->format("N");
        $days_in_month = cal_days_in_month(CAL_GREGORIAN, $m, $y);

        $calendar_html .= "<h3 class='cal-note'>Kalender: {$dt2->format('F Y')}</h3>";
        $calendar_html .= "<table class='mini-calendar'>";
        $calendar_html .= "<tr><th>Sen</th><th>Sel</th><th>Rab</th><th>Kam</th><th>Jum</th><th>Sab</th><th>Min</th></tr><tr>";

        for ($i=1; $i<$start_weekday; $i++) $calendar_html .= "<td></td>";

        for ($d=1; $d<=$days_in_month; $d++) {
            $class = ($d==$selected) ? "today" : "";
            $calendar_html .= "<td class='$class'>$d</td>";
            if ((($d+$start_weekday-1) % 7) == 0) $calendar_html .= "</tr><tr>";
        }

        $calendar_html .= "</tr></table>";
    }

    $d_input = DateTime::createFromFormat('Y-m-d', $date) ?: new DateTime($date);
    $d_now = new DateTime("now");
    $diff = $d_now->diff($d_input);
    $days = $diff->format('%r%a');
    $daydiff_html = "<div class='output' style='margin-top:10px'>Selisih tanggal input dengan hari ini: <b>{$days} hari</b></div>";
}

/* Format Custom */
if (isset($_POST['custom_format_btn'])) {
    $fmt_date = $_POST['date_input'] ?? date('Y-m-d');
    $fmt_time = $_POST['time_input'] ?? "00:00";
    $fmt_from = $_POST['from_zone'] ?? "Asia/Jakarta";
    $fmt_to   = $_POST['to_zone'] ?? "Asia/Jakarta";
    $fmt      = $_POST['fmt'] ?? "Y-m-d H:i:s";

    $dtmp = new DateTime("$fmt_date $fmt_time", new DateTimeZone($fmt_from));
    $dtmp->setTimezone(new DateTimeZone($fmt_to));
    $format_output = "<div class='output' style='margin-top:12px'>Hasil format custom (<b>{$fmt}</b>): <code>".$dtmp->format($fmt)."</code></div>";
}

/* Hapus Riwayat */
if (isset($_POST['clear_history'])) $Log->clear();

$history_lines = $Log->readAll();

/* Favorite */
if (isset($_POST["add_fav"])) {
    $zone = trim($_POST["add_fav"]);
    $fav_file = __DIR__ . "/favorites.txt";

    $favorites = file_exists($fav_file)
        ? array_filter(array_map("trim", file($fav_file)))
        : [];

    if (!in_array($zone, $favorites)) {
        file_put_contents($fav_file, $zone . PHP_EOL, FILE_APPEND);
        $Log->add(date("Y-m-d H:i:s") . " | ⭐ Favorite Added: $zone");
    }

    exit("OK");
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>TimeSky — Converter</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="style.css"> 
</head>

<body>

    <!-- mengubah tema  -->
    <div style="text-align:right;margin:10px 0">
        <button onclick="setTheme('light')" class="btn" style="padding:6px 14px">Light</button>
        <button onclick="setTheme('dark')"  class="btn" style="padding:6px 14px">Dark</button>
        <button onclick="setTheme('purple')" class="btn" style="padding:6px 14px">Purple</button>
    </div>

<!-- Judul -->
<h1 style="text-align:center;font-size:32px;font-weight:650;margin-top:20px;color:#696FC7;text-shadow:0 2px 6px rgba(211, 208, 216, 1)">TimeSky</h1>
<h1 style="
    text-align:center;
    font-size:18px;
    font-weight:500;
    margin-top:0;
    color:#696FC7;
    text-shadow:0 2px 6px rgba(195, 189, 195, 1);
    transform: translateY(-10px);
">
    Time Management & Smart Zone Key
</h1>

<div class="container">

<section class="card">

<h2>🕒 Jam Lokal (Live)</h2>
<div class="live-row">
    <div class="clock-large" id="localTime">--:--:--</div>

    <div class="map-box">
        <img src="https://upload.wikimedia.org/wikipedia/commons/8/80/World_map_-_low_resolution.svg">
    </div>
</div>
</section>

<section class="card">
<h2>🔁 Time & Date Converter</h2>

<!-- Form utama konversi zona waktu -->
<form method="POST" class="form-grid">

    <div class="field">
        <label>Dari Zona</label>
        <select name="from_zone" required id="from_zone_select">
            <?php foreach($zones as $k=>$v) {
                $sel = (isset($_POST['from_zone']) && $_POST['from_zone']==$k) ? 'selected' : '';
                echo "<option value='$k' $sel>$v</option>";
            } ?>
        </select>
    </div>

    <div class="field" style="position:relative;">
    
    <label>Ke Zona</label>

    <button type="button"
        onclick="reverseZones()"
        style="
            position:absolute;
            top: -20px;
            right:0;
            background:#6a4bff;
            color:#fff;
            border:none;
            padding:6px 14px;
            border-radius:6px;
            font-size:14px;
            cursor:pointer;
        ">
        Tukar
    </button>

    <button type="button" 
        onclick="addFavToZone()" 
        style="position:absolute; top:-20px; right:70px;
               background:#ffcc00;border:none;padding:6px 10px;
               font-size:14px;border-radius:6px;cursor:pointer;">
    ⭐
</button>

    <select name="to_zone" id="to_zone_select" required style="padding-right:90px;">
        <?php foreach ($zones as $k => $v): 
            $sel = (isset($_POST['to_zone']) && $_POST['to_zone']==$k) ? 'selected' : '';
        ?>
            <option value="<?= $k ?>" <?= $sel ?>><?= $v ?></option>
        <?php endforeach; ?>
    </select>

</div>

    <div class="field">
        <label>Tanggal</label>
        <input type="date" name="date_input" value="<?= htmlspecialchars($_POST['date_input'] ?? date('Y-m-d')) ?>" required>
    </div>

    <div class="field">
        <label>Waktu</label>
        <input type="time" name="time_input" value="<?= htmlspecialchars($_POST['time_input'] ?? '') ?>">
    </div>

    <div class="field full">
       <button class="btn" name="convert" style="margin-bottom:20px;">
        Konversi & Tampilkan Kalender </button>
    </div>
</form>

<?php
    if ($convert_output) echo $convert_output;
    if ($calendar_html) echo $calendar_html;
    if ($daydiff_html) echo $daydiff_html;
    if ($format_output) echo $format_output;
?>
</section>

<section class="card">
    <h2>⏳ Format Waktu Custom</h2>

    <form method="POST" class="form-grid">
    <input type="hidden" name="from_zone" value="<?= htmlspecialchars($_POST['from_zone'] ?? 'Asia/Jakarta') ?>">
    <input type="hidden" name="to_zone" value="<?= htmlspecialchars($_POST['to_zone'] ?? 'Asia/Jakarta') ?>">
    <input type="hidden" name="date_input" value="<?= htmlspecialchars($_POST['date_input'] ?? date('Y-m-d')) ?>">
    <input type="hidden" name="time_input" value="<?= htmlspecialchars($_POST['time_input'] ?? '') ?>">

    <div class="field full">
        <label>Pilih Format</label>
        <select name="fmt">
            <option value="Y-m-d H:i:s">Standar (Y-m-d H:i:s)</option>
            <option value="d/m/Y H:i">d/m/Y H:i</option>
            <option value="H:i:s">Jam:Menit:Detik</option>
            <option value="l, d F Y H:i">Lengkap (Hari, Tanggal)</option>
        </select>
    </div>

    <div class="field full">
        <button class="btn" name="custom_format_btn">Tampilkan Format Custom</button>
    </div>
</form>

    <?php if($format_output) echo $format_output; ?>
</section>

<section class="card">
<h2>📝 Riwayat Aktifitas</h2>

<div class="history-box">
<ol>
    <?php 
    foreach($history_lines as $h) echo "<li>".htmlspecialchars($h)."</li>"; 
    ?>
</ol>
</div>

<form method="POST" style="margin-top:12px">
    <button class="btn danger" name="clear_history">Hapus Riwayat</button>
</form>
</section>
</div>


<script>
setInterval(()=>{
    const el = document.getElementById("localTime");
    if(el) el.textContent = new Date().toLocaleTimeString("id-ID",{hour12:false});
},1000);

function reverseZones(){
    const a = document.querySelector('[name="from_zone"]');
    const b = document.querySelector('[name="to_zone"]');
    if(!a || !b) return;
    const tmp = a.value;
    a.value = b.value;
    b.value = tmp;
}

// Auto-detect timezone dari browser user
document.addEventListener("DOMContentLoaded", ()=>{
    const userTZ = Intl.DateTimeFormat().resolvedOptions().timeZone;
    const fromSel = document.querySelector("select[name='from_zone']");
    const isSubmitted = <?= json_encode(isset($_POST['from_zone'])) ?>; 

    if (!isSubmitted) {
        if (fromSel && fromSel.querySelector(`option[value="${userTZ}"]`)) {
            fromSel.value = userTZ;
        }
    }
    
    const saved = localStorage.getItem("tf_theme") || "purple";
    setTheme(saved);
});

// switch tema
function setTheme(mode){
    let primaryBg, secondaryBg;
    let textColor;

    if(mode === "light"){
        primaryBg = '#ffffff';
        secondaryBg = '#eef1ff';
        textColor = '#2b2640'; 
    }
    else if(mode === "dark"){
        primaryBg = '#1e1e1e';
        secondaryBg = '#0e0e0e';
        textColor = '#f5f5f5'; 
    }
    else {
        primaryBg = '#c8b8ff';
        secondaryBg = '#aed7ff';
        textColor = '#2b2640'; 
    }
    
    document.documentElement.style.setProperty('--bg1', primaryBg);
    document.documentElement.style.setProperty('--bg2', secondaryBg);
    document.documentElement.style.setProperty('--main-text-color', textColor);
    
    localStorage.setItem("tf_theme",mode);
}
</script>

<script>
const zonesJS = <?= json_encode($zones) ?>;
</script>

<script>

function getFavorites() {
    return JSON.parse(localStorage.getItem("fav_tozones") || "[]");
}

function saveFavorites(arr) {
    localStorage.setItem("fav_tozones", JSON.stringify(arr));
}

function addFavToZone() {
    const sel = document.getElementById("to_zone_select");
    let val = sel.value;

    let fav = getFavorites();
    if (!fav.includes(val)) fav.unshift(val);
    saveFavorites(fav);
    rebuildToZoneSelect();

    fetch("", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "add_fav=" + encodeURIComponent(val)
    }).then(() => {
        location.reload(); 
    });
}

function rebuildToZoneSelect() {
    const sel = document.getElementById("to_zone_select");
    const current = sel.value;

    let fav = getFavorites();

    let html = "";

    if (fav.length > 0) {
        html += "<optgroup label='★ Favorit'>";
        fav.forEach(f => {
            if (zonesJS[f])
                html += `<option value="${f}">★ ${zonesJS[f]}</option>`;
        });
        html += "</optgroup>";
    }

    html += "<optgroup label='Semua Zona'>";
    for (const k in zonesJS) {
        html += `<option value="${k}">${zonesJS[k]}</option>`;
    }
    html += "</optgroup>";

    sel.innerHTML = html;

    sel.value = current;
}
document.addEventListener("DOMContentLoaded", rebuildToZoneSelect);
</script>


</body>
</html>