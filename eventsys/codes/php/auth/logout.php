<?php
/**
 * Logout Page with Confirmation Modal
 * Handles user logout with confirmation dialog
 */

// Start session first
session_start();

// Get return URL (page to return to if cancelled)
$return_url = isset($_GET['return']) ? $_GET['return'] : '../dashboard/home.php';

// Sanitize the return URL to prevent XSS
$return_url = htmlspecialchars($return_url, ENT_QUOTES, 'UTF-8');

// If logout is confirmed via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_logout'])) {

    // Clear the session cookie
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 3600,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );

    // Destroy all session data
    $_SESSION = array();
    session_unset();
    session_destroy();

    // Start fresh session to prevent issues
    session_start();
    session_regenerate_id(true);

    // Set logout flag
    $_SESSION['just_logged_out'] = true;

    // Multiple redirect methods for compatibility
    echo '<script>window.location.href = "../components/landing_page.php";</script>';
    echo '<meta http-equiv="refresh" content="0;url=../components/landing_page.php">';
    header("Location: ../components/landing_page.php");
    exit();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../components/landing_page.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../../assets/fav-logo.png">
    <link rel="apple-touch-icon" href="../../assets/fav-logo.png">
    <title>Logout - CCF B1G</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #5a0016 0%, #1a1a1a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(5px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-container {
            background: white;
            border-radius: 20px;
            padding: 2.5rem;
            max-width: 450px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.4s ease;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #800020, #a6002a);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            color: white;
        }

        .modal-content h2 {
            font-size: 1.8rem;
            color: #1a1a1a;
            text-align: center;
            margin-bottom: 1rem;
            font-weight: 700;
        }

        .modal-content p {
            font-size: 1rem;
            color: #666;
            text-align: center;
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        .modal-buttons {
            display: flex;
            gap: 1rem;
        }

        .btn {
            flex: 1;
            padding: 0.9rem 1.5rem;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-family: 'Poppins', sans-serif;
        }

        .btn-cancel {
            background: #f0f0f0;
            color: #333;
        }

        .btn-cancel:hover {
            background: #e0e0e0;
            transform: translateY(-2px);
        }

        .btn-logout {
            background: linear-gradient(135deg, #800020, #5a0016);
            color: white;
            box-shadow: 0 4px 15px rgba(128, 0, 32, 0.3);
        }

        .btn-logout:hover {
            background: linear-gradient(135deg, #5a0016, #400011);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(128, 0, 32, 0.4);
        }

        .btn-logout.loading {
            position: relative;
            color: transparent;
            pointer-events: none;
        }

        .btn-logout.loading::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            border: 3px solid white;
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 0.6s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        @media (max-width: 480px) {
            .modal-container {
                padding: 2rem;
            }
            .modal-content h2 {
                font-size: 1.5rem;
            }
            .modal-buttons {
                flex-direction: column-reverse;
            }
        }
    </style>
</head>
<body>
    <div class="modal-overlay">
        <div class="modal-container">
            <div class="modal-icon">
                <i data-lucide="log-out" style="width: 40px; height: 40px;"></i>
            </div>

            <div class="modal-content">
                <h2>Confirm Logout</h2>
                <p>Are you sure you want to log out? You'll need to log in again to access your account.</p>

                <form method="POST" action="" id="logoutForm">
                    <input type="hidden" name="confirm_logout" value="1">
                    <div class="modal-buttons">
                        <button type="button" class="btn btn-cancel" onclick="window.location.href='<?= $return_url ?>'">
                            <i data-lucide="x" style="width: 18px; height: 18px;"></i>
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-logout" id="logoutBtn">
                            <i data-lucide="log-out" style="width: 18px; height: 18px;"></i>
                            Logout
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();

        document.getElementById('logoutForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const logoutBtn = document.getElementById('logoutBtn');
            logoutBtn.classList.add('loading');
            logoutBtn.disabled = true;

            // Submit form
            const formData = new FormData(this);

            fetch(window.location.href, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(() => {
                // Force redirect
                window.location.replace('../components/landing_page.php');
            })
            .catch(() => {
                // Fallback: still redirect
                window.location.replace('../components/landing_page.php');
            });
        });

        // ESC key returns to previous page
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                window.location.href = '<?= $return_url ?>';
            }
        });
    </script>
</body>
</html>