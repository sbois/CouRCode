# 🦒 CouRCode - Générateur de QR Code Avancé

**CouRCode** est un générateur de QR Code PHP moderne et personnalisable avec un design girafe unique ! Le nom est un jeu de mots entre "Cou" (le long cou de la girafe) et "QR Code".

## ✨ Fonctionnalités

- 🔗 **4 types de QR Code** : URL, SMS, VCard (contact), Géolocalisation
- 🎨 **Personnalisation des couleurs** : Couleur unie ou dégradé (linéaire, radial, conique)
- 🖼️ **Ajout de logo** : Insertion de logo PNG/JPG/GIF avec transparence préservée
- 🌈 **Transparence** : Fond transparent pour le QR Code
- 🔲 **Styles de modules** : Carré ou rond
- 📥 **Téléchargement** : Export en PNG haute qualité
- 🦒 **Design unique** : Interface thématique girafe

## 📋 Prérequis

- PHP 7.4 ou supérieur
- Extension PHP GD activée
- Composer (gestionnaire de dépendances PHP)
- Serveur web (Apache, Nginx, ou XAMPP/WAMP/MAMP)

## 🚀 Installation

### 1. Cloner le dépôt

```bash
git clone https://github.com/sbois/CouRCode.git
cd CouRCode
```

### 2. Installer les dépendances

```bash
composer require chillerlan/php-qrcode
```

### 3. Vérifier l'extension GD

Assurez-vous que l'extension GD est activée dans votre `php.ini` :

```ini
extension=gd
```

Pour vérifier si GD est installé :

```bash
php -m | grep gd
```

### 4. Configuration du serveur

#### Avec XAMPP (Windows)

1. Placez le dossier dans `C:\xampp\htdocs\CouRCode`
2. Accédez à `http://localhost/CouRCode`

#### Avec Apache/Nginx

1. Configurez votre VirtualHost ou bloc serveur
2. Pointez vers le dossier du projet
3. Redémarrez votre serveur web

#### Serveur PHP intégré (développement)

```bash
php -S localhost:8000
```

Puis accédez à `http://localhost:8000`

## 📁 Structure du projet

```
CouRCode/
├── index.php           # Fichier principal
├── composer.json       # Dépendances Composer
├── composer.lock       # Versions verrouillées
├── vendor/            # Bibliothèques (généré par Composer)
├── uploads/           # Logos temporaires (créé automatiquement)
└── README.md          # Ce fichier
```

## 🎯 Utilisation

1. **Choisir le type de QR Code** : URL, SMS, VCard ou Géolocalisation
2. **Remplir les champs** correspondants au type sélectionné
3. **Personnaliser le style** :
   - Style des modules (carré/rond)
   - Fond transparent (optionnel)
   - Couleur unie ou dégradé (linéaire/radial/conique)
4. **Ajouter un logo** (optionnel, PNG recommandé pour la transparence)
5. **Cliquer sur "Générer le QR Code"**
6. **Télécharger** en cliquant sur le bouton de téléchargement

## 🎨 Exemples de QR Code

### URL Simple
```
Type : URL
URL : https://example.com
Couleur : Noir (unie)
```

### VCard avec dégradé
```
Type : VCard
Couleur : Dégradé radial (bleu → violet)
Logo : Oui
```

### SMS avec transparence
```
Type : SMS
Téléphone : +33612345678
Message : Bonjour !
Fond : Transparent
```

## 🛠️ Technologies utilisées

- **PHP** : Backend et génération d'images
- **chillerlan/php-qrcode** : Bibliothèque de génération de QR Code
- **GD Library** : Manipulation d'images
- **HTML5/CSS3** : Interface utilisateur
- **JavaScript** : Interactions dynamiques

## ⚠️ Limitations connues

- Les logos volumineux peuvent réduire la lisibilité du QR Code
- Le dégradé conique peut être moins lisible avec certaines combinaisons de couleurs
- Les fichiers logo sont temporairement stockés puis supprimés après génération

## 🤝 Contribution

Les contributions sont les bienvenues ! N'hésitez pas à :

1. Fork le projet
2. Créer une branche pour votre fonctionnalité (`git checkout -b feature/AmazingFeature`)
3. Commit vos changements (`git commit -m 'Add some AmazingFeature'`)
4. Push vers la branche (`git push origin feature/AmazingFeature`)
5. Ouvrir une Pull Request

## 📝 Idées d'améliorations futures 

- [ ] Support de types supplémentaires (WiFi, Email, Calendrier)
- [ ] Historique des QR Codes générés
- [ ] Export en SVG
- [ ] Mode sombre
- [ ] Plus d'options de personnalisation (bordures, coins arrondis)

## 🐛 Signaler un bug

Si vous rencontrez un problème, veuillez ouvrir une [issue](https://github.com/votre-username/CouRCode/issues) avec :
- Description détaillée du problème
- Configuration PHP/serveur
- Captures d'écran si pertinent

## 📄 Licence

Ce projet est sous licence GPLv3

## 👨‍💻 Auteur

Créé avec Claude par Steeve BOIS

## 🙏 Remerciements

- [chillerlan/php-qrcode](https://github.com/chillerlan/php-qrcode) pour l'excellente bibliothèque de QR Code
- La communauté PHP pour les ressources et le support
- Tous les contributeurs du projet

---

⭐ N'oubliez pas de laisser une étoile si ce projet vous a aidé ! 🦒
