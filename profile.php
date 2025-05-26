<?php
session_start();
include 'database.php'; // koneksi ke DB, variabel: $conn

// Cek apakah user sudah login
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit;
}

$customerid = $_SESSION['customer_id'];
$query = $conn->prepare("SELECT username, email, phone, image FROM customer WHERE id = ?");
$query->bind_param("i", $customerid);
$query->execute();
$result = $query->get_result();
$data = $result->fetch_assoc();

// Proses update profil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $username = $_POST['username'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];

    // Handle upload gambar
    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
        $imgName = time() . "_" . basename($_FILES['image']['name']);
        $target = "foto/" . $imgName;
        move_uploaded_file($_FILES['image']['tmp_name'], $target);
    } else {
        $target = $data['image']; // pakai yang lama
    }

    $stmt = $conn->prepare("UPDATE customer SET username = ?, email = ?, phone = ?, image = ? WHERE id = ?");
    $stmt->bind_param("ssssi", $username, $email, $phone, $target, $customerid);
    $stmt->execute();
    header("Location: profile.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <title>Profil Customer</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet" />
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
  <div class="w-full max-w-4xl bg-white p-10 rounded-xl shadow-xl relative">
    <h1 class="text-4xl font-bold mb-10 text-center">Profil Customer</h1>

    <div class="flex flex-col md:flex-row items-center md:items-start justify-between gap-10 text-xl">
      <!-- Info Pengguna -->
      <div class="flex-1 space-y-5">
        <p><strong>Nama:</strong> <?= htmlspecialchars($data['username']) ?></p>
        <p><strong>No Telepon:</strong> <?= htmlspecialchars($data['phone']) ?></p>
        <p><strong>Email:</strong> <?= htmlspecialchars($data['email']) ?></p>

        <div class="mt-6 flex space-x-4">
          <button onclick="document.getElementById('editForm').classList.remove('hidden')" class="bg-blue-500 hover:bg-blue-600 text-white px-5 py-2 rounded shadow">Edit Profil</button>
          <a href="logout.php" class="bg-red-500 hover:bg-red-600 text-white px-5 py-2 rounded shadow">Logout</a>
        </div>
      </div>

      <!-- Foto Profil -->
      <div class="flex-shrink-0">
        <img src="<?= htmlspecialchars($data['image'] ?? 'foto/users.png') ?>" alt="Foto Profil" class="w-44 h-44 rounded-full object-cover border-4 border-blue-500 shadow-md" />
      </div>
    </div>

    <!-- Form Edit -->
    <div id="editForm" class="mt-10 hidden">
      <form method="POST" enctype="multipart/form-data" class="space-y-4">
        <input type="hidden" name="update_profile" value="1" />
        <div>
          <label class="block font-medium">Nama</label>
          <input type="text" name="username" value="<?= htmlspecialchars($data['username']) ?>" class="w-full border border-gray-300 rounded px-3 py-2" required />
        </div>

        <div>
          <label class="block font-medium">Email</label>
          <input type="email" name="email" value="<?= htmlspecialchars($data['email']) ?>" class="w-full border border-gray-300 rounded px-3 py-2" required />
        </div>

        <div>
          <label class="block font-medium">No Telepon</label>
          <input type="text" name="phone" value="<?= htmlspecialchars($data['phone']) ?>" class="w-full border border-gray-300 rounded px-3 py-2" required />
        </div>

        <div>
          <label class="block font-medium">Foto Profil (opsional)</label>
          <input type="file" name="image" accept="image/*" class="w-full" />
        </div>

        <div class="flex justify-end space-x-3 mt-4">
          <button type="button" onclick="document.getElementById('editForm').classList.add('hidden')" class="px-4 py-2 rounded bg-gray-300 hover:bg-gray-400">Batal</button>
          <button type="submit" class="px-4 py-2 rounded bg-blue-600 text-white hover:bg-blue-700">Simpan</button>
        </div>
      </form>
    </div>

    <!-- Navigasi -->
    <div class="mt-12 flex justify-between">
      <a href="home.php" class="bg-blue-500 text-white px-6 py-3 rounded-lg hover:bg-blue-600 transition">← Kembali ke Beranda</a>
    </div>
  </div>
</body>
</html>
