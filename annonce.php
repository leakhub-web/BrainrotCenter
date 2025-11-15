<?php
require 'config/db.php';
require 'config/security.php';

// Vérifie le remember me
checkRememberMe();

$annonceId = (int)($_GET['id'] ?? 0);
if ($annonceId <= 0) {
    http_response_code(404);
    die('Annonce introuvable.');
}

// Récupère l'annonce + images + user
$stmt = $pdo->prepare("
    SELECT a.*, u.pseudo, u.avatar_path, u.description as user_desc,
           (SELECT COUNT(*) FROM messages WHERE annonce_id = a.id) as msg_count
    FROM annonces a
    JOIN users u ON a.user_id = u.id
    WHERE a.id = ? AND a.status = 'active'
");
$stmt->execute([$annonceId]);
$annonce = $stmt->fetch();

if (!$annonce) {
    http_response_code(404);
    die('Annonce introuvable ou supprimée.');
}

// Images
$imgStmt = $pdo->prepare("SELECT path FROM images WHERE annonce_id = ? ORDER BY `order`");
$imgStmt->execute([$annonceId]);
$images = $imgStmt->fetchAll(PDO::FETCH_COLUMN);

// Favoris ?
$isFavorite = false;
if (isset($_SESSION['user_id'])) {
    $favStmt = $pdo->prepare("SELECT 1 FROM favorites WHERE user_id = ? AND annonce_id = ?");
    $favStmt->execute([$_SESSION['user_id'], $annonceId]);
    $isFavorite = (bool)$favStmt->fetchColumn();
}

// Gestion POST
if ($_POST && isset($_SESSION['user_id'])) {
    verifyCsrf();

    // Contacter
    if (isset($_POST['action']) && $_POST['action'] === 'contact') {
        $message = trim($_POST['message']);
        if (strlen($message) < 5) {
            $error = "Message trop court.";
        } else {
            $stmt = $pdo->prepare("INSERT INTO messages (from_user_id, to_user_id, annonce_id, message) VALUES (?, ?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $annonce['user_id'], $annonceId, $message]);
            $success = "Message envoyé !";
        }
    }

    // Signaler
    if (isset($_POST['action']) && $_POST['action'] === 'report') {
        $reason = trim($_POST['reason']);
        if (strlen($reason) < 10) {
            $error = "Raison trop courte.";
        } else {
            $stmt = $pdo->prepare("INSERT INTO reports (user_id, annonce_id, reason) VALUES (?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $annonceId, $reason]);
            $success = "Annonce signalée. Merci !";
        }
    }

    // Favoris
    if (isset($_POST['action']) && $_POST['action'] === 'toggle_fav') {
        if ($isFavorite) {
            $pdo->prepare("DELETE FROM favorites WHERE user_id = ? AND annonce_id = ?")
                ->execute([$_SESSION['user_id'], $annonceId]);
            $isFavorite = false;
        } else {
            $pdo->prepare("INSERT INTO favorites (user_id, annonce_id) VALUES (?, ?)")
                ->execute([$_SESSION['user_id'], $annonceId]);
            $isFavorite = true;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($annonce['title']) ?> - BrainrotCenter</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .annonce-detail { max-width: 1000px; margin: 2rem auto; padding: 0 1rem; }
        .images-gallery { display: flex; flex-wrap: wrap; gap: 1rem; margin-bottom: 2rem; }
        .images-gallery img { 
            width: 100%; max-width: 400px; height: 300px; object-fit: cover; 
            border-radius: 12px; border: 2px solid #222; 
        }
        .annonce-info { display: grid; grid-template-columns: 1fr 300px; gap: 2rem; }
        .annonce-main { background: var(--card); padding: 1.5rem; border-radius: 12px; }
        .annonce-sidebar { background: var(--card); padding: 1.5rem; border-radius: 12px; height: fit-content; }
        .seller-info { display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem; }
        .seller-avatar { width: 60px; height: 60px; border-radius: 50%; object-fit: cover; background: #333; }
        .price-big { font-size: 2.5rem; font-weight: 700; color: var(--gold); }
        .meta-tags { display: flex; flex-wrap: wrap; gap: 0.5rem; margin: 1rem 0; }
        .tag { background: #222; padding: 0.4rem 0.8rem; border-radius: 20px; font-size: 0.8rem; }
        .action-btns { display: flex; gap: 1rem; flex-wrap: wrap; margin: 1.5rem 0; }
        .btn-contact { flex: 1; }
        .modal { display: none; }
        .modal.active { display: flex; }
        @media (max-width: 768px) {
            .annonce-info { grid-template-columns: 1fr; }
            .price-big { font-size: 2rem; }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="annonce-detail">
        <?php if (isset($success)) echo "<p style='color:var(--gold); text-align:center; font-weight:600;'>$success</p>"; ?>
        <?php if (isset($error)) echo "<p style='color:red; text-align:center;'>$error</p>"; ?>

        <!-- Galerie d'images -->
        <div class="images-gallery">
            <?php if ($images): ?>
                <?php foreach ($images as $img): ?>
                    <img src="<?= htmlspecialchars($img) ?>" alt="Image de l'annonce">
                <?php endforeach; ?>
            <?php else: ?>
                <img src="assets/placeholder-brainrot.jpg" alt="Pas d'image">
            <?php endif; ?>
        </div>

        <div class="annonce-info">
            <!-- Contenu principal -->
            <div class="annonce-main">
                <h1><?= htmlspecialchars($annonce['title']) ?></h1>
                <div class="price-big"><?= number_format($annonce['price'], 2) ?> €</div>

                <div class="meta-tags">
                    <span class="tag"><?= $annonce['category'] ?></span>
                    <span class="tag"><?= ucfirst($annonce['condition']) ?></span>
                    <span class="tag"><?= ucfirst($annonce['method']) ?></span>
                    <?php if ($annonce['location']): ?>
                        <span class="tag"><?= htmlspecialchars($annonce['location']) ?></span>
                    <?php endif; ?>
                    <span class="tag">Publié le <?= date('d/m/Y', strtotime($annonce['created_at'])) ?></span>
                </div>

                <h3>Description</h3>
                <p style="white-space: pre-wrap;"><?= nl2br(htmlspecialchars($annonce['description'])) ?></p>

                <!-- Boutons d'action -->
                <div class="action-btns">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            <input type="hidden" name="action" value="toggle_fav">
                            <button type="submit" class="btn <?= $isFavorite ? 'btn-outline' : '' ?>">
                                <?= $isFavorite ? 'Retirer des favoris' : 'Ajouter aux favoris' ?>
                            </button>
                        </form>

                        <button class="btn btn-contact open-modal" data-modal="modal-contact">Contacter le vendeur</button>

                        <button class="btn btn-outline open-modal" data-modal="modal-report">Signaler</button>
                    <?php else: ?>
                        <a href="login.php" class="btn">Se connecter pour contacter</a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sidebar vendeur -->
            <div class="annonce-sidebar">
                <div class="seller-info">
                    <img src="<?= $annonce['avatar_path'] ?: 'assets/default-avatar.png' ?>" alt="Avatar" class="seller-avatar">
                    <div>
                        <strong><?= htmlspecialchars($annonce['pseudo']) ?></strong><br>
                        <small>Membre depuis <?= date('M Y', strtotime($annonce['created_at'])) ?></small>
                    </div>
                </div>
                <?php if ($annonce['user_desc']): ?>
                    <p style="font-size:0.9rem; color:#aaa;"><?= nl2br(htmlspecialchars($annonce['user_desc'])) ?></p>
                <?php endif; ?>
                <a href="profil.php?user=<?= $annonce['user_id'] ?>" class="btn btn-outline" style="width:100%; margin-top:1rem;">
                    Voir le profil
                </a>
            </div>
        </div>
    </main>

    <!-- Modal Contact -->
    <div id="modal-contact" class="modal">
        <div class="modal-content">
            <h3>Envoyer un message</h3>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="action" value="contact">
                <textarea name="message" placeholder="Bonjour, je suis intéressé par votre brainrot..." required minlength="5" style="width:100%; height:120px; padding:1rem; background:#222; color:white; border:1px solid #333; border-radius:8px;"></textarea>
                <div style="display:flex; gap:1rem; margin-top:1rem;">
                    <button type="submit" class="btn">Envoyer</button>
                    <button type="button" class="close-modal btn-outline">Annuler</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Signalement -->
    <div id="modal-report" class="modal">
        <div class="modal-content">
            <h3>Signaler cette annonce</h3>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="action" value="report">
                <textarea name="reason" placeholder="Expliquez pourquoi vous signalez cette annonce..." required minlength="10" style="width:100%; height:120px; padding:1rem; background:#222; color:white; border:1px solid #333; border-radius:8px;"></textarea>
                <div style="display:flex; gap:1rem; margin-top:1rem;">
                    <button type="submit" class="btn">Signaler</button>
                    <button type="button" class="close-modal btn-outline">Annuler</button>
                </div>
            </form>
        </div>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script src="assets/js/app.js"></script>
    <script>
        // Ouvre les modals
        document.querySelectorAll('.open-modal').forEach(btn => {
            btn.addEventListener('click', () => {
                document.getElementById(btn.dataset.modal).classList.add('active');
            });
        });
        document.querySelectorAll('.close-modal').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.modal').forEach(m => m.classList.remove('active'));
            });
        });
    </script>
</body>
</html>
