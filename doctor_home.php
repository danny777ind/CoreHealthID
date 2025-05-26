<?php
session_start();

if (!isset($_SESSION['doctor_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: index.php");
    exit;
}

$host = "localhost";
$dbname = "clinic_db";
$user = "root";
$pass = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

if (isset($_POST['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit;
}

$doctorUsername = $_SESSION['username'];
$stmtDoctorId = $pdo->prepare("SELECT id FROM doctor WHERE username = ?");
$stmtDoctorId->execute([$doctorUsername]);
$doctor = $stmtDoctorId->fetch(PDO::FETCH_ASSOC);
$doctorId = $doctor ? $doctor['id'] : 0;

// Ambil pasien dari tabel customer yg berelasi dengan dokter (doctor_customer)
$stmtPatients = $pdo->prepare("
    SELECT c.username, d.disease_name
    FROM customer c
    JOIN doctor_customer dc ON c.id = dc.customer_id
    JOIN diseases d ON dc.doctor_id = d.doctor_id
    WHERE dc.doctor_id = ?
");
$stmtPatients->execute([$doctorId]);
$patients = $stmtPatients->fetchAll(PDO::FETCH_ASSOC);

// Ambil data penyakit milik dokter
$stmtDiseases = $pdo->prepare("SELECT body_part, disease_name, severity FROM diseases WHERE doctor_id = ?");
$stmtDiseases->execute([$doctorId]);
$diseases = $stmtDiseases->fetchAll(PDO::FETCH_ASSOC);

// Ambil riwayat pasien dokter
$stmtHistory = $pdo->prepare("
    SELECT c.username, c.email, c.phone
    FROM customer c
    JOIN doctor_customer dc ON c.id = dc.customer_id
    WHERE dc.doctor_id = ?
");
$stmtHistory->execute([$doctorId]);
$patientHistory = $stmtHistory->fetchAll(PDO::FETCH_ASSOC);

// Tambah penyakit baru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_disease'])) {
    $bodyPart = trim($_POST['body_part'] ?? '');
    $diseaseName = trim($_POST['disease_name'] ?? '');
    $severity = (int)($_POST['severity'] ?? 0);

    if ($bodyPart && $diseaseName && $severity >= 1 && $severity <= 10) {
        $stmtInsert = $pdo->prepare("INSERT INTO diseases (doctor_id, body_part, disease_name, severity) VALUES (?, ?, ?, ?)");
        $stmtInsert->execute([$doctorId, $bodyPart, $diseaseName, $severity]);
        header("Location: doctor_home.php");
        exit;
    } else {
        $error = "Mohon isi semua kolom dengan benar (keparahan 1-10)";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Doctor Home - CoreHealth</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet" />
</head>
<body class="bg-gray-100 min-h-screen flex flex-col">

  <!-- Navbar -->
  <nav class="bg-white shadow py-6 px-4 flex justify-between items-center h-20">
    <div class="flex items-center">
      <img src="foto/logo.png" alt="Logo CoreHealth" class="w-20 h-20 object-contain" />
      <span class="text-2xl font-bold">CoreHealth Indonesia</span>
    </div>
    <div class="flex space-x-4 items-center">
      <a href="doctor_profile.php" class="p-3 rounded hover:bg-gray-300 flex items-center justify-center">
        <img src="foto/account.png" alt="Profile" class="w-6 h-6" />
      </a>
      <form method="POST" class="inline">
        <button type="submit" name="logout" class="p-3 rounded hover:bg-gray-300 text-sm">Logout</button>
      </form>
    </div>
  </nav>

  <!-- Main Content -->
  <main class="flex-grow container mx-auto px-4 py-8">
    <!-- Riwayat Pasien Dokter -->
    <section class="mb-8">
      <h2 class="text-2xl font-semibold mb-4">Riwayat Pasien Anda</h2>
      <?php if (count($patientHistory) === 0): ?>
        <p class="text-gray-500">Belum ada pasien yang berelasi dengan Anda.</p>
      <?php else: ?>
        <table class="w-full table-auto border-collapse border border-gray-300">
          <thead>
            <tr class="bg-gray-200">
              <th class="border border-gray-300 px-4 py-2 text-left">Username Pasien</th>
              <th class="border border-gray-300 px-4 py-2 text-left">Email</th>
              <th class="border border-gray-300 px-4 py-2 text-left">No. Telepon</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($patientHistory as $ph): ?>
              <tr>
                <td class="border border-gray-300 px-4 py-2"><?= htmlspecialchars($ph['username']) ?></td>
                <td class="border border-gray-300 px-4 py-2"><?= htmlspecialchars($ph['email']) ?></td>
                <td class="border border-gray-300 px-4 py-2"><?= htmlspecialchars($ph['phone']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </section>

    <!-- Tombol Add Penyakit -->
    <section class="mb-8">
      <button id="btnAddDisease" 
              class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded shadow transition">
        Add Penyakit
      </button>
    </section>

    <!-- Popout form Add Penyakit -->
    <div id="popoutForm" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden">
      <div class="bg-white rounded-lg p-6 w-96 shadow-lg relative">
        <h3 class="text-xl font-semibold mb-4">Tambah Penyakit Baru</h3>

        <?php if (!empty($error)): ?>
          <p class="text-red-600 mb-4"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <form method="POST" id="addDiseaseForm">
          <input type="hidden" name="add_disease" value="1" />
          <label class="block mb-2 font-medium">Bagian Tubuh</label>
          <select name="body_part" id="inputBodyPart" class="w-full border border-gray-300 rounded px-3 py-2 mb-4" required>
            <option value="">-- Pilih Bagian Tubuh --</option>
            <option value="Kepala">Kepala</option>
            <option value="Badan">Badan</option>
            <option value="Tangan">Tangan</option>
            <option value="Kaki">Kaki</option>
          </select>


          <label class="block mb-2 font-medium">Nama Penyakit</label>
          <input type="text" name="disease_name" id="inputDiseaseName" class="w-full border border-gray-300 rounded px-3 py-2 mb-4" placeholder="Contoh: Flu, Luka" required />

          <label class="block mb-2 font-medium">Keparahan (1-10)</label>
          <input type="number" name="severity" id="inputSeverity" min="1" max="10" class="w-full border border-gray-300 rounded px-3 py-2 mb-6" placeholder="Misal: 5" required />

          <div class="flex justify-end space-x-3">
            <button type="button" id="cancelBtn" class="px-4 py-2 rounded bg-gray-300 hover:bg-gray-400">Batal</button>
            <button type="submit" class="px-4 py-2 rounded bg-green-600 text-white hover:bg-green-700">Simpan</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Daftar Penyakit Dokter -->
    <section>
      <h2 class="text-2xl font-semibold mb-4">Penyakit yang Sudah Diterbitkan</h2>
      <ul id="diseaseList" class="list-disc pl-5 space-y-2">
        <?php if (count($diseases) === 0): ?>
          <li class="text-gray-500">Belum ada penyakit yang diterbitkan.</li>
        <?php else: ?>
          <?php foreach ($diseases as $d): ?>
            <li><?= htmlspecialchars($d['body_part']) ?> - <?= htmlspecialchars($d['disease_name']) ?> (Keparahan: <?= htmlspecialchars($d['severity']) ?>)</li>
          <?php endforeach; ?>
        <?php endif; ?>
      </ul>
    </section>

  </main>

<script>
  const popoutForm = document.getElementById('popoutForm');
  const btnAddDisease = document.getElementById('btnAddDisease');
  const cancelBtn = document.getElementById('cancelBtn');

  btnAddDisease.addEventListener('click', () => {
    popoutForm.classList.remove('hidden');
  });

  cancelBtn.addEventListener('click', () => {
    popoutForm.classList.add('hidden');
    document.getElementById('addDiseaseForm').reset();
  });
</script>

</body>
</html>
