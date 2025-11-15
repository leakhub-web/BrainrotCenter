<?php
require_once 'db.php';

// CSRF Token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Rate limiting IP (5 tentatives / min pour login/register)
function rateLimit($action = 'login') {
    $ip = $_SERVER['REMOTE_ADDR'];
    $key = "rate_$action:" . inet_ntop(inet_pton($ip)); // IPv6 safe
    
    // Simple file cache (use Redis prod)
    $cacheFile = sys_get_temp_dir() . '/' . md5($key) . '.ratelimit';
    if (file_exists($cacheFile)) {
        $data = json_decode(file_get_contents($cacheFile), true);
        if (time() - $data['time'] < 60 && $data['count'] >= 5) {
            http_response_code(429);
            die('Trop de tentatives. Attends 1 min.');
        }
    }
    
    $data = ['count' => 1, 'time' => time()];
    if (file_exists($cacheFile)) {
        $data['count'] = json_decode(file_get_contents($cacheFile), true)['count'] + 1;
    }
    file_put_contents($cacheFile, json_encode($data));
}

// Remember Me sécurisé
function setRememberMe($userId) {
    $selector = base64_encode(random_bytes(9));
    $authenticator = random_bytes(33);
    
    setcookie('remember', $selector . ':' . base64_encode($authenticator), [
        'expires' => time() + 864000 * 30, // 30 jours
        'path' => '/',
        'secure' => true, // HTTPS only prod
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    
    $tokenHash = hash('sha256', $authenticator);
    $expires = date('Y-m-d H:i:s', time() + 864000 * 30);
    
    $stmt = $pdo->prepare("INSERT INTO auth_tokens (selector, token_hash, user_id, expires) VALUES (?, ?, ?, ?)");
    $stmt->execute([$selector, $tokenHash, $userId, $expires]);
}

function checkRememberMe() {
    if (isset($_COOKIE['remember'])) {
        [$selector, $authenticator] = explode(':', $_COOKIE['remember']);
        $authenticatorBin = base64_decode($authenticator);
        
        $stmt = $pdo->prepare("SELECT user_id FROM auth_tokens WHERE selector = ? AND expires > NOW()");
        $stmt->execute([$selector]);
        $row = $stmt->fetch();
        
        if ($row && hash_equals(hash('sha256', $authenticatorBin), /* DB hash */)) {
            $_SESSION['user_id'] = $row['user_id'];
            return true;
        }
    }
    return false;
}

// Vérif CSRF
function verifyCsrf() {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403);
        die('Token CSRF invalide');
    }
    // Rotate token
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Upload image sécurisé + resize
function uploadImage($file, $maxSize = 5*1024*1024, $maxDim = 800) {
    $allowed = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($file['type'], $allowed) || $file['size'] > $maxSize) {
        throw new Exception('Fichier invalide');
    }
    
    $imgInfo = getimagesize($file['tmp_name']);
    if (!$imgInfo) throw new Exception('Pas une image');
    
    // Resize GD
    $src = imagecreatefromstring(file_get_contents($file['tmp_name']));
    $width = imagesx($src);
    $height = imagesy($src);
    
    if ($width > $maxDim || $height > $maxDim) {
        $ratio = $maxDim / max($width, $height);
        $newW = $width * $ratio;
        $newH = $height * $ratio;
        $dst = imagecreatetruecolor($newW, $newH);
        imagecopyresampled($dst, $src, 0,0,0,0, $newW, $newH, $width, $height);
    } else {
        $dst = $src;
    }
    
    $path = 'assets/uploads/' . bin2hex(random_bytes(8)) . '.webp';
    imagewebp($dst, $path, 80); // Compress 80%
    imagedestroy($src);
    imagedestroy($dst);
    
    return $path;
}
?>
