<?php
require_once __DIR__ . '/vendor/autoload.php';

use chillerlan\QRCode\{QRCode, QROptions};
use chillerlan\QRCode\Data\QRMatrix;
use chillerlan\QRCode\Output\QRGdImagePNG;

// Classe personnalisée pour gérer les logos
class CustomQROutput extends QRGdImagePNG {
    
    protected function createImage(): void {
        parent::createImage();
    }
    
    public function addLogoImage(string $logoPath): void {
        if (!file_exists($logoPath)) {
            return;
        }
        
        $logoInfo = getimagesize($logoPath);
        
        switch ($logoInfo[2]) {
            case IMAGETYPE_PNG:
                $logo = imagecreatefrompng($logoPath);
                break;
            case IMAGETYPE_JPEG:
                $logo = imagecreatefromjpeg($logoPath);
                break;
            case IMAGETYPE_GIF:
                $logo = imagecreatefromgif($logoPath);
                break;
            default:
                return;
        }
        
        $qrWidth = imagesx($this->image);
        $qrHeight = imagesy($this->image);
        $logoWidth = imagesx($logo);
        $logoHeight = imagesy($logo);
        
        // Le logo fait 1/5 de la taille du QR Code
        $logoQrWidth = $qrWidth / 5;
        $scale = $logoQrWidth / $logoWidth;
        $logoQrHeight = $logoHeight * $scale;
        
        $fromWidth = ($qrWidth - $logoQrWidth) / 2;
        $fromHeight = ($qrHeight - $logoQrHeight) / 2;
        
        imagecopyresampled($this->image, $logo, $fromWidth, $fromHeight, 0, 0, 
            $logoQrWidth, $logoQrHeight, $logoWidth, $logoHeight);
        
        imagedestroy($logo);
    }
}

// Traitement du formulaire
$qrCodeImage = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Récupération des données du formulaire
        $type = $_POST['type'] ?? 'URL';
        $content = '';
        
        // Génération du contenu selon le type
        switch ($type) {
            case 'SMS':
                $phone = $_POST['sms_phone'] ?? '';
                $message = $_POST['sms_message'] ?? '';
                $content = "SMSTO:$phone:$message";
                break;
                
            case 'URL':
                $content = $_POST['url'] ?? '';
                break;
                
            case 'VCARD':
                $name = $_POST['vcard_name'] ?? '';
                $phone = $_POST['vcard_phone'] ?? '';
                $email = $_POST['vcard_email'] ?? '';
                $org = $_POST['vcard_org'] ?? '';
                $content = "BEGIN:VCARD\nVERSION:3.0\nFN:$name\nTEL:$phone\nEMAIL:$email\nORG:$org\nEND:VCARD";
                break;
                
            case 'GEOLOC':
                $lat = $_POST['geo_lat'] ?? '';
                $lng = $_POST['geo_lng'] ?? '';
                $content = "geo:$lat,$lng";
                break;
        }
        
        if (empty($content)) {
            throw new Exception("Le contenu du QR Code ne peut pas être vide");
        }
        
        // Configuration de base
        $options = new QROptions([
            'version' => QRCode::VERSION_AUTO,
            'outputType' => QRCode::OUTPUT_IMAGE_PNG,
            'eccLevel' => QRCode::ECC_M,
            'scale' => 10,
            'imageBase64' => false,
            'drawCircularModules' => isset($_POST['module_style']) && $_POST['module_style'] === 'circle',
            'circleRadius' => 0.45,
            'keepAsSquare' => [
                QRMatrix::M_FINDER_DARK,
                QRMatrix::M_FINDER_DOT,
                QRMatrix::M_ALIGNMENT_DARK,
            ],
            'addQuietzone' => true,
            'imageTransparent' => isset($_POST['transparent']) && $_POST['transparent'] === 'on',
        ]);
        
        // Génération du QR Code de base (en noir et blanc)
        $qrcode = new QRCode($options);
        $imageData = $qrcode->render($content);
        
        // Charger l'image pour appliquer les couleurs personnalisées
        $qrImage = imagecreatefromstring($imageData);
        $qrWidth = imagesx($qrImage);
        $qrHeight = imagesy($qrImage);
        
        // Activer la transparence dès le début
        imagealphablending($qrImage, false);
        imagesavealpha($qrImage, true);
        
        // Si fond transparent activé, rendre le fond transparent
        if (isset($_POST['transparent']) && $_POST['transparent'] === 'on') {
            $transparent = imagecolorallocatealpha($qrImage, 255, 255, 255, 127);
            
            // Remplacer tous les pixels blancs par des pixels transparents
            for ($y = 0; $y < $qrHeight; $y++) {
                for ($x = 0; $x < $qrWidth; $x++) {
                    $pixelColor = imagecolorat($qrImage, $x, $y);
                    $rgb = imagecolorsforindex($qrImage, $pixelColor);
                    
                    // Si le pixel est blanc (fond), le rendre transparent
                    if ($rgb['red'] > 200 && $rgb['green'] > 200 && $rgb['blue'] > 200) {
                        imagesetpixel($qrImage, $x, $y, $transparent);
                    }
                }
            }
        }
        
        // Réactiver le blending pour les opérations de couleur
        imagealphablending($qrImage, true);
        
        // Vérifier si on utilise un dégradé
        $useGradient = isset($_POST['use_gradient']) && $_POST['use_gradient'] === 'on';
        
        if ($useGradient && !empty($_POST['qr_color']) && !empty($_POST['qr_color2'])) {
            // Dégradé entre deux couleurs
            $color1Hex = ltrim($_POST['qr_color'], '#');
            $color2Hex = ltrim($_POST['qr_color2'], '#');
            
            $r1 = hexdec(substr($color1Hex, 0, 2));
            $g1 = hexdec(substr($color1Hex, 2, 2));
            $b1 = hexdec(substr($color1Hex, 4, 2));
            
            $r2 = hexdec(substr($color2Hex, 0, 2));
            $g2 = hexdec(substr($color2Hex, 2, 2));
            $b2 = hexdec(substr($color2Hex, 4, 2));
            
            // Type de dégradé
            $gradientType = $_POST['gradient_type'] ?? 'linear';
            
            // Parcourir chaque pixel et appliquer le dégradé
            for ($y = 0; $y < $qrHeight; $y++) {
                for ($x = 0; $x < $qrWidth; $x++) {
                    $pixelColor = imagecolorat($qrImage, $x, $y);
                    $rgb = imagecolorsforindex($qrImage, $pixelColor);
                    
                    // Si le pixel est noir (partie du QR code), appliquer la couleur du dégradé
                    if ($rgb['red'] < 128) {
                        $ratio = 0;
                        
                        switch ($gradientType) {
                            case 'linear':
                                // Dégradé de haut en bas
                                $ratio = $y / $qrHeight;
                                break;
                                
                            case 'radial':
                                // Dégradé du centre vers l'extérieur
                                $centerX = $qrWidth / 2;
                                $centerY = $qrHeight / 2;
                                $maxDistance = sqrt($centerX * $centerX + $centerY * $centerY);
                                $distance = sqrt(pow($x - $centerX, 2) + pow($y - $centerY, 2));
                                $ratio = min(1, $distance / $maxDistance);
                                break;
                                
                            case 'conical':
                                // Dégradé conique (circulaire)
                                $centerX = $qrWidth / 2;
                                $centerY = $qrHeight / 2;
                                $angle = atan2($y - $centerY, $x - $centerX);
                                $ratio = ($angle + M_PI) / (2 * M_PI);
                                break;
                        }
                        
                        $r = (int)($r1 + ($r2 - $r1) * $ratio);
                        $g = (int)($g1 + ($g2 - $g1) * $ratio);
                        $b = (int)($b1 + ($b2 - $b1) * $ratio);
                        
                        $gradientColor = imagecolorallocate($qrImage, $r, $g, $b);
                        imagesetpixel($qrImage, $x, $y, $gradientColor);
                    }
                }
            }
        } elseif (!empty($_POST['qr_color'])) {
            // Couleur unie
            $colorHex = ltrim($_POST['qr_color'], '#');
            $r = hexdec(substr($colorHex, 0, 2));
            $g = hexdec(substr($colorHex, 2, 2));
            $b = hexdec(substr($colorHex, 4, 2));
            
            $newColor = imagecolorallocate($qrImage, $r, $g, $b);
            
            // Remplacer tous les pixels noirs par la nouvelle couleur
            for ($y = 0; $y < $qrHeight; $y++) {
                for ($x = 0; $x < $qrWidth; $x++) {
                    $pixelColor = imagecolorat($qrImage, $x, $y);
                    $rgb = imagecolorsforindex($qrImage, $pixelColor);
                    
                    if ($rgb['red'] < 128) {
                        imagesetpixel($qrImage, $x, $y, $newColor);
                    }
                }
            }
        }
        
        // Convertir en PNG avec transparence
        ob_start();
        imagesavealpha($qrImage, true);
        imagepng($qrImage);
        $imageData = ob_get_clean();
        
        // Gestion du logo
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $logoPath = $uploadDir . uniqid() . '_' . basename($_FILES['logo']['name']);
            move_uploaded_file($_FILES['logo']['tmp_name'], $logoPath);
            
            // Recharger l'image si elle a déjà été traitée pour les couleurs
            if (isset($qrImage)) {
                imagedestroy($qrImage);
            }
            $qrImage = imagecreatefromstring($imageData);
            
            // Charger le logo
            $logoInfo = getimagesize($logoPath);
            $logo = null;
            
            switch ($logoInfo[2]) {
                case IMAGETYPE_PNG:
                    $logo = imagecreatefrompng($logoPath);
                    break;
                case IMAGETYPE_JPEG:
                    $logo = imagecreatefromjpeg($logoPath);
                    break;
                case IMAGETYPE_GIF:
                    $logo = imagecreatefromgif($logoPath);
                    break;
            }
            
            if ($logo) {
                // Préserver la transparence du logo
                imagealphablending($logo, false);
                imagesavealpha($logo, true);
                
                $qrWidth = imagesx($qrImage);
                $qrHeight = imagesy($qrImage);
                $logoWidth = imagesx($logo);
                $logoHeight = imagesy($logo);
                
                // Le logo fait 1/5 de la taille du QR Code
                $logoQrWidth = $qrWidth / 5;
                $scale = $logoQrWidth / $logoWidth;
                $logoQrHeight = $logoHeight * $scale;
                
                $fromWidth = ($qrWidth - $logoQrWidth) / 2;
                $fromHeight = ($qrHeight - $logoQrHeight) / 2;
                
                // Créer une nouvelle image pour le logo redimensionné avec transparence
                $logoResized = imagecreatetruecolor($logoQrWidth, $logoQrHeight);
                imagealphablending($logoResized, false);
                imagesavealpha($logoResized, true);
                
                // Remplir avec une couleur transparente
                $transparent = imagecolorallocatealpha($logoResized, 0, 0, 0, 127);
                imagefill($logoResized, 0, 0, $transparent);
                
                // Redimensionner le logo en préservant la transparence
                imagecopyresampled(
                    $logoResized,
                    $logo,
                    0,
                    0,
                    0,
                    0,
                    $logoQrWidth,
                    $logoQrHeight,
                    $logoWidth,
                    $logoHeight
                );
                
                // Préparer l'image QR pour recevoir le logo avec transparence
                imagealphablending($qrImage, true);
                imagesavealpha($qrImage, true);
                
                // Copier le logo redimensionné sur le QR Code
                imagecopy($qrImage, $logoResized, $fromWidth, $fromHeight, 0, 0, $logoQrWidth, $logoQrHeight);
                
                imagedestroy($logo);
                imagedestroy($logoResized);
                
                // Convertir en PNG en préservant la transparence
                ob_start();
                imagesavealpha($qrImage, true);
                imagepng($qrImage);
                $imageData = ob_get_clean();
                imagedestroy($qrImage);
            }
            
            // Nettoyage du logo temporaire
            unlink($logoPath);
        }
        
        // Nettoyage de l'image GD si elle existe encore
        if (isset($qrImage) && is_resource($qrImage)) {
            imagedestroy($qrImage);
        }
        
        $qrCodeImage = base64_encode($imageData);
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CouRCode - Générateur QR Code</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f4e4c1 0%, #e8d4a0 50%, #dcc589 100%);
            min-height: 100vh;
            padding: 20px;
            position: relative;
            overflow-x: hidden;
        }
        
        /* Motif de taches de girafe en arrière-plan */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: 
                radial-gradient(ellipse 80px 100px at 10% 20%, #d4a574 40%, transparent 40%),
                radial-gradient(ellipse 60px 80px at 85% 15%, #c89b66 40%, transparent 40%),
                radial-gradient(ellipse 90px 110px at 20% 70%, #d4a574 40%, transparent 40%),
                radial-gradient(ellipse 70px 90px at 75% 80%, #c89b66 40%, transparent 40%),
                radial-gradient(ellipse 65px 85px at 50% 50%, #d4a574 40%, transparent 40%),
                radial-gradient(ellipse 55px 75px at 30% 40%, #c89b66 40%, transparent 40%),
                radial-gradient(ellipse 75px 95px at 80% 45%, #d4a574 40%, transparent 40%);
            opacity: 0.3;
            pointer-events: none;
            z-index: 0;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 30px;
            box-shadow: 0 20px 60px rgba(212, 165, 116, 0.4);
            overflow: hidden;
            position: relative;
            z-index: 1;
        }
        
        header {
            background: linear-gradient(135deg, #f9d689 0%, #f5c563 100%);
            color: #6b4423;
            padding: 40px 30px;
            text-align: center;
            position: relative;
            border-bottom: 5px solid #d4a574;
        }
        
        /* Girafe décorative */
        .giraffe-decoration {
            position: absolute;
            left: 20px;
            bottom: -10px;
            font-size: 80px;
            animation: wiggle 3s ease-in-out infinite;
        }
        
        @keyframes wiggle {
            0%, 100% { transform: rotate(-2deg); }
            50% { transform: rotate(2deg); }
        }
        
        header h1 {
            font-size: 3em;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(107, 68, 35, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }
        
        header h1::before {
            content: '🦒';
            font-size: 1.2em;
        }
        
        header p {
            font-size: 1.2em;
            color: #8b5a2b;
        }
        
        .content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            padding: 30px;
            background: linear-gradient(to bottom, #fffef8 0%, #fff9e6 100%);
        }
        
        .form-section {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        label {
            font-weight: 600;
            color: #6b4423;
            font-size: 0.95em;
        }
        
        input[type="text"],
        input[type="email"],
        input[type="tel"],
        input[type="url"],
        input[type="number"],
        textarea,
        select {
            padding: 12px;
            border: 3px solid #f5c563;
            border-radius: 12px;
            font-size: 1em;
            transition: all 0.3s;
            background: white;
        }
        
        input:focus,
        textarea:focus,
        select:focus {
            outline: none;
            border-color: #d4a574;
            box-shadow: 0 0 0 3px rgba(212, 165, 116, 0.2);
        }
        
        input[type="color"] {
            width: 100%;
            height: 45px;
            border: 3px solid #f5c563;
            border-radius: 12px;
            cursor: pointer;
        }
        
        input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
            accent-color: #d4a574;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        button {
            background: linear-gradient(135deg, #f9d689 0%, #f5c563 100%);
            color: #6b4423;
            padding: 15px 30px;
            border: 3px solid #d4a574;
            border-radius: 15px;
            font-size: 1.1em;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(212, 165, 116, 0.3);
        }
        
        button:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(212, 165, 116, 0.4);
        }
        
        button:active {
            transform: translateY(-1px);
        }
        
        .preview-section {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #fff9e6 0%, #fffef8 100%);
            border-radius: 20px;
            padding: 30px;
            border: 3px dashed #f5c563;
        }
        
        .qrcode-container {
            background: white;
            padding: 25px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(212, 165, 116, 0.3);
            margin-bottom: 20px;
            border: 4px solid #f9d689;
        }
        
        .qrcode-container img {
            max-width: 100%;
            height: auto;
            display: block;
        }
        
        .download-btn {
            background: linear-gradient(135deg, #8fbc8f 0%, #6b8e23 100%);
            color: white;
            border-color: #556b2f;
            margin-top: 10px;
        }
        
        .download-btn:hover {
            background: linear-gradient(135deg, #9fcf9f 0%, #7ba428 100%);
        }
        
        .error {
            background: #ffe4e1;
            color: #8b3a3a;
            padding: 15px;
            border-radius: 12px;
            border: 3px solid #ffb6c1;
        }
        
        .type-fields {
            display: none;
        }
        
        .type-fields.active {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .info-box {
            background: linear-gradient(135deg, #fff9e6 0%, #fffef8 100%);
            border: 3px solid #f5c563;
            color: #6b4423;
            padding: 15px;
            border-radius: 12px;
            font-size: 0.9em;
        }
        
        hr {
            border: none;
            height: 3px;
            background: linear-gradient(to right, transparent, #f5c563, transparent);
            margin: 20px 0;
        }
        
        /* Animation de la girafe souriante */
        .preview-section::before {
            content: '🦒';
            font-size: 60px;
            animation: bounce 2s ease-in-out infinite;
            margin-bottom: 10px;
        }
        
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        
        @media (max-width: 768px) {
            .content {
                grid-template-columns: 1fr;
            }
            
            header h1 {
                font-size: 2em;
            }
            
            .giraffe-decoration {
                font-size: 50px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="giraffe-decoration">🦒</div>
            <h1>CouRCode</h1>
            <p>Générateur de QR Code avec un looong cou ! 🦒</p>
        </header>
        
        <div class="content">
            <div class="form-section">
                <form method="POST" enctype="multipart/form-data" id="qrForm">
                    
                    <div class="info-box">
                        🦒 <strong>Astuce girafe :</strong> Pour un QR Code bien visible, utilisez des couleurs contrastées (comme les taches de la girafe) !
                    </div>
                    
                    <div class="form-group">
                        <label for="type">Type de QR Code</label>
                        <select name="type" id="type" required>
                            <option value="URL" <?= ($_POST['type'] ?? '') === 'URL' ? 'selected' : '' ?>>URL</option>
                            <option value="SMS" <?= ($_POST['type'] ?? '') === 'SMS' ? 'selected' : '' ?>>SMS</option>
                            <option value="VCARD" <?= ($_POST['type'] ?? '') === 'VCARD' ? 'selected' : '' ?>>VCard (Contact)</option>
                            <option value="GEOLOC" <?= ($_POST['type'] ?? '') === 'GEOLOC' ? 'selected' : '' ?>>Géolocalisation</option>
                        </select>
                    </div>
                    
                    <!-- Champs URL -->
                    <div id="url-fields" class="type-fields <?= ($_POST['type'] ?? 'URL') === 'URL' ? 'active' : '' ?>">
                        <div class="form-group">
                            <label for="url">URL</label>
                            <input type="url" id="url" name="url" placeholder="https://example.com" value="<?= $_POST['url'] ?? '' ?>">
                        </div>
                    </div>
                    
                    <!-- Champs SMS -->
                    <div id="sms-fields" class="type-fields <?= ($_POST['type'] ?? '') === 'SMS' ? 'active' : '' ?>">
                        <div class="form-group">
                            <label for="sms_phone">Numéro de téléphone</label>
                            <input type="tel" id="sms_phone" name="sms_phone" placeholder="+33612345678" value="<?= $_POST['sms_phone'] ?? '' ?>">
                        </div>
                        <div class="form-group">
                            <label for="sms_message">Message</label>
                            <textarea id="sms_message" name="sms_message" rows="3" placeholder="Votre message..."><?= $_POST['sms_message'] ?? '' ?></textarea>
                        </div>
                    </div>
                    
                    <!-- Champs VCard -->
                    <div id="vcard-fields" class="type-fields <?= ($_POST['type'] ?? '') === 'VCARD' ? 'active' : '' ?>">
                        <div class="form-group">
                            <label for="vcard_name">Nom complet</label>
                            <input type="text" id="vcard_name" name="vcard_name" placeholder="Jean Dupont" value="<?= $_POST['vcard_name'] ?? '' ?>">
                        </div>
                        <div class="form-group">
                            <label for="vcard_phone">Téléphone</label>
                            <input type="tel" id="vcard_phone" name="vcard_phone" placeholder="+33612345678" value="<?= $_POST['vcard_phone'] ?? '' ?>">
                        </div>
                        <div class="form-group">
                            <label for="vcard_email">Email</label>
                            <input type="email" id="vcard_email" name="vcard_email" placeholder="email@example.com" value="<?= $_POST['vcard_email'] ?? '' ?>">
                        </div>
                        <div class="form-group">
                            <label for="vcard_org">Entreprise</label>
                            <input type="text" id="vcard_org" name="vcard_org" placeholder="Nom de l'entreprise" value="<?= $_POST['vcard_org'] ?? '' ?>">
                        </div>
                    </div>
                    
                    <!-- Champs Géolocalisation -->
                    <div id="geoloc-fields" class="type-fields <?= ($_POST['type'] ?? '') === 'GEOLOC' ? 'active' : '' ?>">
                        <div class="form-group">
                            <label for="geo_lat">Latitude</label>
                            <input type="number" id="geo_lat" name="geo_lat" step="0.000001" placeholder="48.8566" value="<?= $_POST['geo_lat'] ?? '' ?>">
                        </div>
                        <div class="form-group">
                            <label for="geo_lng">Longitude</label>
                            <input type="number" id="geo_lng" name="geo_lng" step="0.000001" placeholder="2.3522" value="<?= $_POST['geo_lng'] ?? '' ?>">
                        </div>
                    </div>
                    
                    <hr style="border: 1px solid #e0e0e0; margin: 20px 0;">
                    
                    <div class="form-group">
                        <label for="module_style">Style des modules</label>
                        <select name="module_style" id="module_style">
                            <option value="square" <?= ($_POST['module_style'] ?? '') === 'square' ? 'selected' : '' ?>>Carré</option>
                            <option value="circle" <?= ($_POST['module_style'] ?? '') === 'circle' ? 'selected' : '' ?>>Rond</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="transparent" name="transparent" <?= isset($_POST['transparent']) ? 'checked' : '' ?>>
                            <label for="transparent">Fond transparent</label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="use_gradient" name="use_gradient" <?= isset($_POST['use_gradient']) ? 'checked' : '' ?>>
                            <label for="use_gradient">Utiliser un dégradé de couleurs</label>
                        </div>
                    </div>
                    
                    <div class="form-group" id="gradient-type-group" style="<?= isset($_POST['use_gradient']) && $_POST['use_gradient'] === 'on' ? '' : 'display: none;' ?>">
                        <label for="gradient_type">Type de dégradé</label>
                        <select name="gradient_type" id="gradient_type">
                            <option value="linear" <?= ($_POST['gradient_type'] ?? 'linear') === 'linear' ? 'selected' : '' ?>>Linéaire (Haut → Bas)</option>
                            <option value="radial" <?= ($_POST['gradient_type'] ?? '') === 'radial' ? 'selected' : '' ?>>Radial (Centre → Extérieur)</option>
                            <option value="conical" <?= ($_POST['gradient_type'] ?? '') === 'conical' ? 'selected' : '' ?>>Conique (Circulaire)</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Couleur du QR Code</label>
                        <input type="color" name="qr_color" id="qr_color" value="<?= $_POST['qr_color'] ?? '#000000' ?>">
                    </div>
                    
                    <div class="form-group" id="gradient-color-group" style="<?= isset($_POST['use_gradient']) && $_POST['use_gradient'] === 'on' ? '' : 'display: none;' ?>">
                        <label>Deuxième couleur (dégradé)</label>
                        <input type="color" name="qr_color2" id="qr_color2" value="<?= $_POST['qr_color2'] ?? '#FF0000' ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="logo">Logo (optionnel)</label>
                        <input type="file" name="logo" id="logo" accept="image/png,image/jpeg,image/gif">
                        <small style="color: #666;">⚠️ L'ajout d'un logo peut réduire la lisibilité du QR Code</small>
                    </div>
                    
                    <button type="submit">Générer le QR Code</button>
                </form>
            </div>
            
            <div class="preview-section">
                <?php if ($error): ?>
                    <div class="error"><?= htmlspecialchars($error) ?></div>
                <?php elseif ($qrCodeImage): ?>
                    <h2>Votre QR Code</h2>
                    <div class="qrcode-container">
                        <img src="data:image/png;base64,<?= $qrCodeImage ?>" alt="QR Code" id="qrImage">
                    </div>
                    <a href="data:image/png;base64,<?= $qrCodeImage ?>" download="qrcode.png">
                        <button class="download-btn" type="button">📥 Télécharger le QR Code</button>
                    </a>
                <?php else: ?>
                    <p style="color: #666; text-align: center;">Remplissez le formulaire et cliquez sur "Générer" pour créer votre QR Code personnalisé</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        const typeSelect = document.getElementById('type');
        const urlFields = document.getElementById('url-fields');
        const smsFields = document.getElementById('sms-fields');
        const vcardFields = document.getElementById('vcard-fields');
        const geolocFields = document.getElementById('geoloc-fields');
        const useGradientCheck = document.getElementById('use_gradient');
        const gradientColorGroup = document.getElementById('gradient-color-group');
        const gradientTypeGroup = document.getElementById('gradient-type-group');
        
        typeSelect.addEventListener('change', function() {
            [urlFields, smsFields, vcardFields, geolocFields].forEach(el => el.classList.remove('active'));
            
            switch(this.value) {
                case 'URL':
                    urlFields.classList.add('active');
                    break;
                case 'SMS':
                    smsFields.classList.add('active');
                    break;
                case 'VCARD':
                    vcardFields.classList.add('active');
                    break;
                case 'GEOLOC':
                    geolocFields.classList.add('active');
                    break;
            }
        });
        
        useGradientCheck.addEventListener('change', function() {
            gradientColorGroup.style.display = this.checked ? 'flex' : 'none';
            gradientTypeGroup.style.display = this.checked ? 'flex' : 'none';
        });
    </script>
    <div style="text-align:center; margin-top:15px; opacity:0.7;">
    <!-- Icône GPLv3 -->
    <a href="https://www.gnu.org/licenses/gpl-3.0.fr.html" target="_blank" style="margin-right:12px;">
        <img src="https://upload.wikimedia.org/wikipedia/commons/9/93/GPLv3_Logo.svg"
             alt="GNU GPLv3"
             style="height:24px; width:auto; vertical-align:middle;">
    </a>

    <!-- Icône GitHub fond transparent -->
    <a href="https://github.com/sbois" target="_blank">
        <img src="https://raw.githubusercontent.com/simple-icons/simple-icons/develop/icons/github.svg"
             alt="GitHub"
             style="height:24px; width:auto; vertical-align:middle;">
    </a>
</div>

</body>
</html>