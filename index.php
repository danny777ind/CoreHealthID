<?php
session_start();

$host = "localhost";
$dbname = "clinic_db";
$user = "root";
$pass = "";

$error = "";
$selectedRole = "";

if (isset($_GET['role']) && in_array($_GET['role'], ['customer', 'doctor'])) {
    $selectedRole = $_GET['role'];
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

$inputUsername = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedRole = $_POST['role'] ?? '';
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $inputUsername = htmlspecialchars($username);

    if (!in_array($selectedRole, ['customer', 'doctor'])) {
        $error = "Role tidak valid.";
    } elseif (!$username || !$password) {
        $error = "Username dan password harus diisi.";
    } else {
        $table = ($selectedRole === 'customer') ? 'customer' : 'doctor';

        $stmt = $pdo->prepare("SELECT * FROM $table WHERE username = ?");
        $stmt->execute([$username]);
        $userData = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($userData && password_verify($password, $userData['password'])) {
            $_SESSION['username'] = $userData['username'];
            $_SESSION['role'] = $selectedRole;

            if ($selectedRole === 'doctor') {
                $_SESSION['doctor_id'] = $userData['id'];
                header("Location: doctor_home.php");
            } else {
                $_SESSION['customer_id'] = $userData['id'];
                header("Location: home.php");
            }
            exit;
        } else {
            $error = "Username atau password salah.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Login - CoreHealth</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet" />
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen px-4">

  <div class="bg-white p-8 rounded-xl shadow-lg w-full max-w-md">

    <h2 class="text-3xl font-bold text-center mb-8">Login</h2>

    <?php if (!$selectedRole): ?>
      <!-- Pilih role -->
      <div id="role-selection" class="flex justify-center gap-x-6 mb-10">
        <button
          onclick="selectRole('customer')"
          class="bg-blue-600 text-white px-8 py-3 rounded hover:bg-blue-700 transition duration-300"
        >
          Login sebagai Customer
        </button>
        <button
          onclick="selectRole('doctor')"
          class="bg-green-600 text-white px-8 py-3 rounded hover:bg-green-700 transition duration-300"
        >
          Login sebagai Doctor
        </button>
      </div>
    <?php endif; ?>

    <?php if ($error): ?>
      <p class="text-red-600 mb-4 text-center font-semibold"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <?php if ($selectedRole): ?>
    <!-- Form login -->
    <form id="login-form" class="flex flex-col space-y-5" method="POST" action="">
      <input type="hidden" name="role" value="<?= htmlspecialchars($selectedRole) ?>" />

      <input
        name="username"
        id="username"
        type="text"
        placeholder="Username"
        class="w-full px-4 py-3 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
        required
        autofocus
        value="<?= $inputUsername ?>"
      />
      <input
        name="password"
        id="password"
        type="password"
        placeholder="Password"
        class="w-full px-4 py-3 border rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
        required
      />
      <button
        type="submit"
        class="w-full bg-blue-600 text-white py-3 rounded hover:bg-blue-700 transition duration-300"
      >
        Login sebagai <?= ucfirst($selectedRole) ?>
      </button>

      <button
        type="button"
        onclick="backToRoleSelection()"
        class="w-full border border-gray-400 text-gray-600 py-2 rounded hover:bg-gray-100 transition duration-300"
      >
        Kembali Pilih Role
      </button>
    </form>
    <?php endif; ?>

    <p class="text-sm text-center mt-10">
      Belum punya akun? <a href="register.php" class="text-blue-600 hover:underline">Register</a>
    </p>

  </div>

  <script>
    function selectRole(role) {
      window.location.href = "?role=" + role;
    }

    function backToRoleSelection() {
      window.location.href = "index.php";
    }
  </script>

</body>
</html>
