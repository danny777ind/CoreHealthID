<?php
session_start();

// Misal customer yang login, ambil dari session (bisa disesuaikan)
$customer_id = $_SESSION['customer_id'] ?? 1; // Default to 1 for testing

// --- LOGIKA LOGOUT BARU DIMULAI DI SINI ---
if (isset($_POST['logout'])) {
    // Hancurkan semua data sesi
    $_SESSION = array(); // Kosongkan array $_SESSION

    // Jika menggunakan cookie sesi, hapus juga cookie-nya.
    // Ini akan menghancurkan sesi, dan bukan hanya data sesi.
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    session_destroy(); // Hancurkan sesi

    // Arahkan pengguna ke halaman login atau halaman utama
    header("Location: index.php"); // Ganti 'login.php' dengan halaman login Anda
    exit;
}

$host = 'localhost';
$db   = 'clinic_db';
$user = 'root';
$pass = '';
$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (Exception $e) {
    die("Koneksi DB gagal: " . $e->getMessage());
}

// *** Hapus bagian AJAX untuk getDiseasesByBodyPart atau searchDiseases jika ada di sini,
// *** karena kita tidak lagi menggunakan seleksi dinamis penyakit di modal.

// Ambil daftar bagian tubuh unik
$stmtBodyParts = $pdo->query("SELECT DISTINCT body_part FROM diseases ORDER BY body_part ASC");
$body_parts = $stmtBodyParts->fetchAll(PDO::FETCH_ASSOC);

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected_body_part = $_POST['body_part'] ?? ''; // Ambil bagian tubuh yang dipilih

    if (!empty($selected_body_part)) {
        // Ambil semua penyakit yang terkait dengan body_part yang dipilih
        $stmtGetDiseases = $pdo->prepare("SELECT id AS disease_id, doctor_id FROM diseases WHERE body_part = ?");
        $stmtGetDiseases->execute([$selected_body_part]);
        $diseases_to_add = $stmtGetDiseases->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($diseases_to_add)) {
            $pdo->beginTransaction(); // Mulai transaksi untuk kinerja lebih baik

            try {
                foreach ($diseases_to_add as $disease) {
                    $disease_id = $disease['disease_id'];
                    $doctor_id = $disease['doctor_id'];

                    // 1. Masukkan ke doctor_customer (jika belum ada relasi)
                    $stmtCheckRel = $pdo->prepare("SELECT id FROM doctor_customer WHERE doctor_id = ? AND customer_id = ?");
                    $stmtCheckRel->execute([$doctor_id, $customer_id]);
                    if (!$stmtCheckRel->fetch()) {
                        $stmtInsertRel = $pdo->prepare("INSERT INTO doctor_customer (doctor_id, customer_id) VALUES (?, ?)");
                        $stmtInsertRel->execute([$doctor_id, $customer_id]);
                    }

                    // 2. Masukkan ke customer_diseases (catat sebagai keluhan)
                    // Periksa apakah keluhan ini sudah pernah dilaporkan oleh customer
                    $stmtCheckComplaint = $pdo->prepare("SELECT id FROM customer_diseases WHERE customer_id = ? AND disease_id = ?");
                    $stmtCheckComplaint->execute([$customer_id, $disease_id]);
                    if (!$stmtCheckComplaint->fetch()) { // Hanya masukkan jika belum ada
                         $stmtInsertComplaint = $pdo->prepare("INSERT INTO customer_diseases (customer_id, disease_id, reported_at) VALUES (?, ?, NOW())");
                         $stmtInsertComplaint->execute([$customer_id, $disease_id]);
                    }
                }
                $pdo->commit(); // Selesaikan transaksi

                header("Location: " . $_SERVER['PHP_SELF'] . "?success=true");
                exit;

            } catch (Exception $e) {
                $pdo->rollBack(); // Batalkan transaksi jika ada error
                error_log("Error adding multiple complaints: " . $e->getMessage()); // Log error untuk debugging
                header("Location: " . $_SERVER['PHP_SELF'] . "?error=db_error");
                exit;
            }
        } else {
            header("Location: " . $_SERVER['PHP_SELF'] . "?error=no_diseases_found"); // Tidak ada penyakit untuk bagian tubuh ini
            exit;
        }
    } else {
        // Handle invalid body_part
        header("Location: " . $_SERVER['PHP_SELF'] . "?error=invalid_body_part");
        exit;
    }
}

// Ambil daftar penyakit yang sudah diderita customer dari tabel `customer_diseases`
$stmtKeluhan = $pdo->prepare("SELECT cd.reported_at, ds.body_part, ds.disease_name, ds.severity, d.username AS doctor_name
                              FROM customer_diseases cd
                              JOIN diseases ds ON ds.id = cd.disease_id
                              JOIN doctor d ON d.id = ds.doctor_id
                              WHERE cd.customer_id = ?
                              ORDER BY ds.body_part, ds.severity, cd.reported_at DESC");
$stmtKeluhan->execute([$customer_id]);
$keluhan_list = $stmtKeluhan->fetchAll(PDO::FETCH_ASSOC);

// Kelompokkan data berdasarkan body_part
$grouped_diseases = [];
foreach ($keluhan_list as $keluhan) {
    $body_part = $keluhan['body_part'];
    if (!isset($grouped_diseases[$body_part])) {
        $grouped_diseases[$body_part] = [];
    }
    $grouped_diseases[$body_part][] = $keluhan;
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <title>Home - CoreHealth</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet" />
    <style>
        body{
          overflow-x: hidden;
        }
        .square {
            width: 250px;
            height: 250px;
            position: relative;
            overflow: hidden;
            border-radius: 0.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            flex-shrink: 0;
            cursor: pointer;
        }
        .square img {
            position: absolute;
            top: 0; left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .horizontal-scroll {
            display: flex;
            gap: 1rem;
            overflow-x: auto;
            scroll-behavior: smooth;
            padding: 1rem 0;
            scrollbar-width: thin;
        }
        .horizontal-scroll::-webkit-scrollbar {
            height: 8px;
        }
        .horizontal-scroll::-webkit-scrollbar-thumb {
            background-color: rgba(100, 100, 100, 0.5);
            border-radius: 4px;
        }
        .carousel-wrapper {
            width: 100vw;
            margin-left: calc(-50vw + 50%);
            background: white;
            padding: 0 1rem; 
            box-sizing: border-box;
            box-shadow: 0 1px 5px rgba(0,0,0,0.1);
        }

        /* Modal overlay */
        .modal {
            position: fixed;
            top: 0; left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0,0,0,0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 100;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s ease;
        }
        .modal.show {
            opacity: 1;
            pointer-events: auto;
        }

        /* Modal content box */
        .modal-content {
            background: white;
            border-radius: 0.5rem;
            width: 90%;
            max-width: 480px;
            max-height: 90vh;
            overflow-y: auto;
            padding: 1.5rem 2rem;
            box-shadow: 0 10px 25px rgba(0,0,0,0.3);
            position: relative;
        }

        /* Close button */
        .modal-close {
            position: absolute;
            top: 0.75rem;
            right: 0.75rem;
            background: transparent;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #555;
            transition: color 0.2s ease;
        }
        .modal-close:hover {
            color: #111;
        }
        /* Notification style */
        .notification {
            position: fixed;
            top: 1rem;
            right: 1rem;
            background-color: #4CAF50; /* Green */
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 0.5rem;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            z-index: 1000;
            opacity: 0;
            transform: translateY(-20px);
            animation: fadeinout 5s forwards;
        }

        @keyframes fadeinout {
            0% { opacity: 0; transform: translateY(-20px); }
            10% { opacity: 1; transform: translateY(0); }
            90% { opacity: 1; transform: translateY(0); }
            100% { opacity: 0; transform: translateY(-20px); }
        }
    </style>
</head>
<body class="bg-gray-100 text-gray-800 min-h-screen">

<?php if (isset($_GET['success'])): ?>
    <div class="notification bg-green-500">Keluhan berhasil ditambahkan!</div>
<?php elseif (isset($_GET['error'])): ?>
    <div class="notification bg-red-500">Terjadi kesalahan: 
        <?php 
          echo 'Pendaftaran keluhan gagal.';
        ?>
    </div>
<?php endif; ?>

<nav class="bg-white shadow py-6 px-4 flex justify-between items-center h-20">
    <div class="flex items-center">
        <img src="foto/logo.png" alt="Logo CoreHealth" class="w-20 h-20 object-contain" />
        <span class="text-2xl font-bold">CoreHealth Indonesia</span>
    </div>
    <div class="flex space-x-4 items-center">
        <a href="profile.php" class="p-3 rounded hover:bg-gray-300 flex items-center justify-center">
            <img src="foto/account.png" alt="Profile" class="w-6 h-6" />
        </a>
        <form method="POST" class="inline">
          <button type="submit" name="logout" class="p-3 rounded hover:bg-gray-300 text-sm">Logout</button>
      </form>
    </div>
</nav>

<div class="carousel-wrapper">
    <div id="carousel" class="horizontal-scroll">
        <div class="square" onclick="openWiki('Jantung')">
            <img src="foto/jantung.webp" alt="Jantung" />
        </div>
        <div class="square" onclick="openWiki('Lambung')">
            <img src="foto/lambung.jpeg" alt="Lambung" />
        </div>
        <div class="square" onclick="openWiki('Hati')">
            <img src="foto/hati.webp" alt="Hati" />
        </div>
        <div class="square" onclick="openWiki('Paru-paru')">
            <img src="foto/paru-paru.webp" alt="Paru-Paru" />
        </div>
        <div class="square" onclick="openWiki('Ginjal')">
            <img src="foto/Ginjal.webp" alt="Ginjal" />
        </div>
        <div class="square" onclick="openWiki('Pankreas')">
            <img src="foto/Pankreas.jpg" alt="Pankreas" />
        </div>
        <div class="square" onclick="openWiki('Usus_halus')">
            <img src="foto/usus-halus.png" alt="Usus Halus" />
        </div>
        <div class="square" onclick="openWiki('Usus_besar')">
            <img src="foto/usus-besar.jpg" alt="Usus Besar" />
        </div>
        <div class="square" onclick="openWiki('Kandung_kemih')">
            <img src="foto/kandung-kemih.png" alt="Kandung Kemih" />
        </div>
        <div class="square" onclick="openWiki('Otak')">
            <img src="foto/otak.png" alt="Otak" />
        </div>
        <div class="square" onclick="openWiki('Penyakit_radang_mulut')">
            <img src="foto/Mulut.webp" alt="Mulut" />
        </div>
    </div>
</div>

<div class="flex justify-center space-x-4 mt-4 mb-8">
    <button onclick="scrollPrev()" class="bg-blue-500 text-white px-6 py-2 rounded hover:bg-blue-600">Prev</button>
    <button onclick="scrollNext()" class="bg-blue-500 text-white px-6 py-2 rounded hover:bg-blue-600">Next</button>
</div>

<main class="max-w-4xl mx-auto p-6">
    <div id="symptom-box" class="bg-white p-6 rounded shadow mb-6">
        <h2 class="text-xl font-semibold mb-4">Gejala yang Mungkin Terjadi</h2>
        <div id="symptom-content" class="text-gray-600 mb-4">
            <?php if (empty($grouped_diseases)): ?>
                <p>Tidak ada data keluhan</p>
            <?php else: ?>
                <?php foreach ($grouped_diseases as $body_part => $diseases): ?>
                    <div class="mb-4">
                        <h3 class="font-bold text-lg mb-2"><?= htmlspecialchars($body_part) ?>:</h3>
                        <ul class="ml-4 space-y-1">
                            <?php foreach ($diseases as $disease): ?>
                                <li>
                                    <?= htmlspecialchars($disease['disease_name']) ?> - 
                                    Keparahan: <?= htmlspecialchars($disease['severity']) ?>, 
                                    Ditulis oleh: Dr. <?= htmlspecialchars($disease['doctor_name']) ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <button id="open-form-btn" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
            Isi Keluhan
        </button>
    </div>
</main>

<div id="modal" class="modal" role="dialog" aria-modal="true" aria-labelledby="modal-title" aria-hidden="true">
    <div class="modal-content">
        <button class="modal-close" aria-label="Close modal" id="close-modal">&times;</button>
        <h3 id="modal-title" class="text-xl font-semibold mb-4">Pilih Bagian Tubuh</h3>
        <form id="complaint-form" method="post" class="space-y-4">
            <div>
                <label for="body_part" class="block font-medium mb-2">Pilih Bagian Tubuh</label>
                <select id="body_part" name="body_part" required class="w-full border border-gray-300 rounded p-2">
                    <option value="">-- Pilih Bagian Tubuh --</option>
                    <?php foreach ($body_parts as $part): ?>
                        <option value="<?= htmlspecialchars($part['body_part']) ?>"><?= htmlspecialchars($part['body_part']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 w-full">
                    Simpan Keluhan
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openWiki(topic) {
        window.open(`https://id.wikipedia.org/wiki/${topic}`, '_blank');
    }

    const carousel = document.getElementById('carousel');
    const scrollAmount = 520;

    function scrollNext() {
        carousel.scrollBy({ left: scrollAmount, behavior: 'smooth' });
    }
    function scrollPrev() {
        carousel.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
    }

    // Modal elements
    const modal = document.getElementById('modal');
    const openFormBtn = document.getElementById('open-form-btn');
    const closeModalBtn = document.getElementById('close-modal');

    openFormBtn.addEventListener('click', () => {
        modal.classList.add('show');
        modal.setAttribute('aria-hidden', 'false');
        // Reset form on open
        document.getElementById('complaint-form').reset();
    });

    closeModalBtn.addEventListener('click', () => {
        modal.classList.remove('show');
        modal.setAttribute('aria-hidden', 'true');
    });

    // Close modal if click outside modal-content
    modal.addEventListener('click', (e) => {
        if(e.target === modal) {
            modal.classList.remove('show');
            modal.setAttribute('aria-hidden', 'true');
        }
    });

    // Auto-hide notifikasi setelah 5 detik
    setTimeout(() => {
        const notifications = document.querySelectorAll('.notification');
        notifications.forEach(notif => {
            notif.style.display = 'none';
        });
    }, 5000);
</script>

</body>
</html>