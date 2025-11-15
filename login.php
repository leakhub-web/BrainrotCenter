<?php
require 'config/security.php';

rateLimit('login'); // Anti-brute force

if ($_POST) {
    verifyCsrf();
    
    if (isset($_POST['register'])) {
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        $pseudo = trim($_POST['pseudo']);
        $pass = $_POST['password'];
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pass) < 8) {
            $error = "Données invalides";
        } else {
            $hash = password_hash($pass, PASSWORD_ARGON2ID);
            $stmt = $pdo->prepare("INSERT INTO users (email, password_hash, pseudo, last_ip) VALUES (?, ?, ?, ?)");
            if ($stmt->execute([$email, $hash, $pseudo, $_SERVER['REMOTE_ADDR']])) {
                $_SESSION['user_id'] = $pdo->lastInsertId();
                if (isset($_POST['remember'])) setRememberMe($_SESSION['user_id']);
                header('Location: /');
                exit;
            } else {
                $error = "Email ou pseudo existe déjà";
            }
        }
    }
    
    if (isset($_POST['login'])) {
        $email = $_POST['email'];
        $pass = $_POST['password'];
        
        $stmt = $pdo->prepare("SELECT id, password_hash FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($pass, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $pdo->prepare("UPDATE users SET last_ip = ? WHERE id = ?")->execute([$_SERVER['REMOTE_ADDR'], $user['id']]);
            if (isset($_POST['remember'])) setRememberMe($user['id']);
            header('Location: /');
            exit;
        } else {
            $error = "Mauvais email/mot de passe";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<!-- HTML form avec CSRF + reCAPTCHA v3 (ajoute tes keys Google) -->
<form method="POST" id="auth-form">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    <!-- reCAPTCHA v3 -->
    <div class="g-recaptcha" data-sitekey="TA_SITE_KEY" data-action="login"></div>
    
    <h2>Se connecter</h2>
    <?php if(isset($error)) echo "<p style='color:red'>$error</p>"; ?>
    <input type="email" name="email" placeholder="Email" required>
    <input type="password" name="password" placeholder="Mot de passe" required>
    <label><input type="checkbox" name="remember"> Se souvenir de moi</label>
    <button type="submit" name="login">Se connecter</button>
    
    <h3>Pas de compte ?</h3>
    <input type="text" name="pseudo" placeholder="Pseudo">
    <button type="submit" name="register">Créer un compte</button>
</form>

<script src="https://www.google.com/recaptcha/api.js?render=TA_SITE_KEY"></script>
<!-- Vérif reCAPTCHA v3 avant submit -->
