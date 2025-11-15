<?php
require 'config/security.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_POST) {
    verifyCsrf();
    
    $title = trim($_POST['title']);
    $desc = trim($_POST['description']);
    $cat = $_POST['category'];
    $price = (float)$_POST['price'];
    $location = trim($_POST['location']);
    $condition = $_POST['condition'];
    $method = $_POST['method'];
    
    if (strlen($title) < 5 || !in_array($cat, ['Fortnite','Roblox']) || $price <= 0) {
        $error = "Champs invalides";
    } else {
        $stmt = $pdo->prepare("INSERT INTO annonces (user_id, title, description, category, price, location, condition, method) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$_SESSION['user_id'], $title, $desc, $cat, $price, $location, $condition, $method])) {
            $annonceId = $pdo->lastInsertId();
            
            // Upload images
            if (!empty($_FILES['images']['name'][0])) {
                foreach ($_FILES['images']['tmp_name'] as $key => $tmp) {
                    if ($_FILES['images']['error'][$key] == 0) {
                        try {
                            $path = uploadImage(['tmp_name'=>$tmp, 'type'=>$_FILES['images']['type'][$key], 'size'=>$_FILES['images']['size'][$key]]);
                            $stmtImg = $pdo->prepare("INSERT INTO images (annonce_id, path, `order`) VALUES (?, ?, ?)");
                            $stmtImg->execute([$annonceId, $path, $key]);
                        } catch(Exception $e) {
                            // Log error
                        }
                    }
                }
            }
            header('Location: /?success=Annonce publiée !');
            exit;
        }
    }
}
?>

<!-- Formulaire avec preview images + CSRF -->
<form method="POST" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
    
    <input type="text" name="title" placeholder="Titre (ex: Renard OG Fortnite)" required maxlength="255">
    <textarea name="description" placeholder="Description détaillée..."></textarea>
    <select name="category" required>
        <option value="Fortnite">Fortnite</option>
        <option value="Roblox">Roblox</option>
    </select>
    <input type="number" name="price" placeholder="Prix (€)" step="0.01" min="0.01" required>
    <input type="text" name="location" placeholder="Ville (optionnel)">
    <select name="condition">
        <option value="neuf">Neuf</option>
        <option value="occasion">Occasion</option>
    </select>
    <select name="method">
        <option value="vente">Vente</option>
        <option value="echange">Échange</option>
        <option value="trade">Trade</option>
    </select>
    <input type="file" name="images[]" multiple accept="image/*" id="images">
    <div id="image-preview"></div>
    <button type="submit" class="btn">Poster l'annonce</button>
</form>
