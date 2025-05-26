<?php
// Bagian PHP: proses submit form
$host = "localhost";
$dbname = "clinic_db";
$user = "root";
$pass = "";

$message = "";
$success = false;

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Koneksi gagal: " . $e->getMessage());
}

function generateID($pdo, $table, $prefix) {
    $stmt = $pdo->prepare("SELECT id FROM $table WHERE id LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $lastId = $stmt->fetchColumn();

    if (!$lastId) {
        return $prefix . "0001";
    } else {
        $num = (int)substr($lastId, strlen($prefix));
        $num++;
        return $prefix . str_pad($num, 4, "0", STR_PAD_LEFT);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $role = trim($_POST['role'] ?? '');

    if (!$username || !$email || !$phone || !$password || !in_array($role, ['Customer', 'Doctor'])) {
        $message = "Mohon isi semua kolom dengan benar.";
    } else {
        // Pilih tabel dan prefix sesuai role
        if ($role === "Customer") {
            $table = "customer";
            $prefix = "CUS";
        } else {
            $table = "doctor";
            $prefix = "DOC";
        }

        // Cek duplikat username atau email
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM $table WHERE username = ? OR email = ?");
        $stmtCheck->execute([$username, $email]);
        if ($stmtCheck->fetchColumn() > 0) {
            $message = "Username atau email sudah terdaftar.";
        } else {
            $id = generateID($pdo, $table, $prefix);
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $stmtInsert = $pdo->prepare("INSERT INTO $table (id, username, email, phone, password) VALUES (?, ?, ?, ?, ?)");
            if ($stmtInsert->execute([$id, $username, $email, $phone, $hashedPassword])) {
                $success = true;
                $message = "Registrasi berhasil sebagai $role.";
            } else {
                $message = "Registrasi gagal, coba lagi.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Register - CoreHealth</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet"/>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">

<div class="bg-white p-8 rounded-xl shadow-lg w-96">
  <h2 class="text-2xl font-bold text-center mb-6">Register</h2>

  <!-- Pilihan Role -->
  <div class="flex justify-center space-x-4 mb-6">
    <button id="btnCustomer" type="button" 
            class="px-6 py-2 rounded bg-blue-600 text-white hover:bg-blue-700 transition duration-300 focus:outline-none focus:ring-2 focus:ring-blue-400">
      Customer
    </button>
    <button id="btnDoctor" type="button" 
            class="px-6 py-2 rounded bg-gray-300 text-gray-700 hover:bg-gray-400 transition duration-300 focus:outline-none focus:ring-2 focus:ring-gray-500">
      Doctor
    </button>
  </div>

  <!-- Form Registrasi -->
  <form id="registerForm" method="POST" action="register.php">
    <input type="hidden" name="role" id="roleInput" value="Customer" />
    <input name="username" id="username" type="text" placeholder="Username" class="w-full px-4 py-2 mb-3 border rounded"/>
    <input name="email" id="email" type="email" placeholder="Email" class="w-full px-4 py-2 mb-3 border rounded"/>
    <input name="phone" id="phone" type="text" placeholder="No Telepon" class="w-full px-4 py-2 mb-3 border rounded"/>
    <input name="password" id="password" type="password" placeholder="Password" class="w-full px-4 py-2 mb-6 border rounded"/>
    <button type="submit" class="w-full bg-green-600 text-white py-3 rounded hover:bg-green-700 transition duration-300">Register</button>
  </form>

  <?php if ($message && !$success): ?>
  <p class="mt-4 text-center text-red-600"><?=htmlspecialchars($message)?></p>
  <?php endif; ?>

  <p class="text-sm text-center mt-6">
    Sudah punya akun? 
    <a href="index.php" class="text-blue-600 hover:underline">Login</a>
  </p>
</div>

<script>
  let selectedRole = 'Customer'; // default role

  const btnCustomer = document.getElementById('btnCustomer');
  const btnDoctor = document.getElementById('btnDoctor');
  const roleInput = document.getElementById('roleInput');

  function setRole(role) {
    selectedRole = role;
    roleInput.value = role;

    if(role === 'Customer') {
      btnCustomer.classList.add('bg-blue-600', 'text-white');
      btnCustomer.classList.remove('bg-gray-300', 'text-gray-700');
      btnDoctor.classList.remove('bg-blue-600', 'text-white');
      btnDoctor.classList.add('bg-gray-300', 'text-gray-700');
    } else {
      btnDoctor.classList.add('bg-blue-600', 'text-white');
      btnDoctor.classList.remove('bg-gray-300', 'text-gray-700');
      btnCustomer.classList.remove('bg-blue-600', 'text-white');
      btnCustomer.classList.add('bg-gray-300', 'text-gray-700');
    }
  }

  btnCustomer.addEventListener('click', () => setRole('Customer'));
  btnDoctor.addEventListener('click', () => setRole('Doctor'));

  setRole(selectedRole);
</script>

<script>
  <?php if ($message): ?>
    const success = <?= json_encode($success) ?>;
    const message = <?= json_encode($message) ?>;

    if (success) {
      alert(message);
      window.location.href = "index.php";
    }
  <?php endif; ?>
</script>

</body>
</html>
