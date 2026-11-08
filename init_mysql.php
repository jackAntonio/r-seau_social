<?php
// ============================================================
// Script d'initialisation automatique de la base MySQL (XAMPP)
// Lancez : php init_mysql.php
// ============================================================

$host = '127.0.0.1';
$port = 3306;
$user = 'root';
$pass = '';
$db   = 'reseau_social';

echo "=== Initialisation de la base de données Forgab sur XAMPP ===\n\n";

try {
    // 1. Connexion au serveur MySQL sans spécifier de base
    echo "1. Connexion à MySQL sur {$host}:{$port}... ";
    $pdo = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 3
    ]);
    echo "OK !\n";

    // 2. Création de la base de données
    echo "2. Création de la base '{$db}'... ";
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `{$db}`");
    echo "OK !\n";

    // 3. Exécution du schéma SQL
    echo "3. Création des tables depuis schema_mysql.sql... ";
    $sql_file = __DIR__ . '/schema_mysql.sql';
    if (!file_exists($sql_file)) {
        die("ERREUR : fichier schema_mysql.sql introuvable.\n");
    }

    $sql = file_get_contents($sql_file);
    // Supprimer les commentaires éventuels
    $pdo->exec($sql);
    echo "OK !\n";

    // 4. Création d'un compte de test / admin si la table est vide
    $chk = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    if ($chk == 0) {
        echo "4. Création d'un compte administrateur par défaut... ";
        $admin_pass = password_hash('Admin1234!', PASSWORD_BCRYPT);
        $ins = $pdo->prepare("
            INSERT INTO users (nom, prenom, email, password, is_verified, role, bio)
            VALUES (?, ?, ?, ?, 1, 'admin', ?)
        ");
        $ins->execute([
            'Admin',
            'Forgab',
            'admin@forgab.com',
            $admin_pass,
            'Compte administrateur officiel du réseau social Forgab.'
        ]);
        echo "OK !\n";
        echo "   -> Identifiants de test :\n";
        echo "      Email        : admin@forgab.com\n";
        echo "      Mot de passe : Admin1234!\n";
    }

    echo "\n============================================================\n";
    echo "SUCCÈS : La base de données locale '{$db}' est prête !\n";
    echo "Vous pouvez maintenant lancer le serveur avec setup_php.bat\n";
    echo "============================================================\n";

} catch (PDOException $e) {
    echo "\nERREUR : " . $e->getMessage() . "\n\n";
    echo "Conseils de dépannage :\n";
    echo "1. Ouvrez le panneau de configuration XAMPP (XAMPP Control Panel).\n";
    echo "2. Cliquez sur le bouton 'Start' en face de 'MySQL'.\n";
    echo "3. Vérifiez que le voyant MySQL devient vert (Port 3306).\n";
    echo "4. Relancez ensuite : php init_mysql.php\n";
}
