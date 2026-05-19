<?php

// Importation des classes nécessaires de la bibliothèque PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Classe de gestion des utilisateurs
 * Gère l'inscription, la validation par email, la connexion (Session/Cookie) et la déconnexion.
 */
class Utilisateur {
    /**
     * @var PDO Instance de connexion à la base de données
     */
    private PDO $db;

    /**
     * Constructeur de la classe
     * @param PDO $db Instance PDO injectée depuis l'application principale
     */
    public function __construct(PDO $db) {
        $this->db = $db;
        
        // Initialisation ou récupération de la session si elle n'est pas encore active sur la page
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Inscription d'un nouvel utilisateur
     * 
     * @param string $email Adresse email saisie par l'utilisateur
     * @param string $motDePasse Mot de passe en clair saisi par l'utilisateur
     * @return bool True si l'inscription et l'envoi du mail réussissent, False sinon
     */
    public function inscrire(string $email, string $motDePasse): bool {
        // Hachage du mot de passe avec l'algorithme par défaut actuel de PHP (bcrypt ou Argon2id)
        // password_hash génère automatiquement un sel unique et sécurisé imbriqué dans la chaîne de sortie
        $hash = password_hash($motDePasse, PASSWORD_DEFAULT);
        
        // Génération d'un jeton cryptographique aléatoire de 32 octets convertis en hexadécimal (64 caractères)
        $cleVerification = bin2hex(random_bytes(32));

        // Préparation de la requête d'insertion avec des marqueurs nommés pour contrer les injections SQL
        $sql = "INSERT INTO utilisateurs (email, mot_de_passe, cle_verification) VALUES (:email, :password, :cle)";
        $stmt = $this->db->prepare($sql);
        
        try {
            // Début d'une transaction SQL : garantit que l'insertion en BDD et l'envoi du mail forment un bloc atomique
            $this->db->beginTransaction();

            // Exécution de la requête avec assignation des valeurs nettoyées
            $stmt->execute([
                ':email' => $email,
                ':password' => $hash,
                ':cle' => $cleVerification
            ]);
            
            // Appel de la méthode interne d'envoi de l'email via PHPMailer
            // Si cette méthode lève une Exception (ex: serveur SMTP en panne), le script bascule directement dans le catch
            $this->envoyerEmailVerification($email, $cleVerification);
            
            // Si tout s'est bien passé (BDD + Mail), on valide définitivement la transaction SQL
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            // En cas d'erreur à l'insertion ou à l'envoi du mail, on annule l'écriture en BDD (Rollback)
            // Évite d'avoir un utilisateur créé en BDD qui ne recevra jamais son mail d'activation
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            
            // Journalisation de l'erreur dans les logs du serveur pour le débogage
            error_log("Échec de l'inscription pour {$email} : " . $e->getMessage());
            return false;
        }
    }

    /**
     * Vérification de l'adresse email de l'utilisateur
     * 
     * @param string $cle La clé de vérification transmise via l'URL du mail
     * @return bool True si un utilisateur a bien été activé, False si la clé est invalide/expirée
     */
    public function verifierEmail(string $cle): bool {
        // Met à jour le statut, et vide la clé pour qu'elle ne soit plus réutilisable
        $sql = "UPDATE utilisateurs SET est_verifie = 1, cle_verification = NULL WHERE cle_verification = :cle";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':cle' => $cle]);

        // rowCount() retourne le nombre de lignes affectées. Si > 0, la clé existait et le compte est actif.
        return $stmt->rowCount() > 0;
    }

    /**
     * Connexion manuelle de l'utilisateur
     * 
     * @param string $email
     * @param string $motDePasse
     * @param bool $seSouvenirDeMoi Si True, un cookie de connexion automatique sera généré
     * @return bool True si les identifiants sont corrects et le compte actif
     * @throws Exception Si le compte n'a pas encore validé son adresse email
     */
    public function connecter(string $email, string $motDePasse, bool $seSouvenirDeMoi = false): bool {
        // Recherche de l'utilisateur par son email
        $sql = "SELECT * FROM utilisateurs WHERE email = :email LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // password_verify() extrait le sel du hash stocké et vérifie la correspondance du mot de passe en clair
        if ($user && password_verify($motDePasse, $user['mot_de_passe'])) {
            
            // Sécurité : On bloque l'accès si l'email n'a pas été validé au préalable
            if ((int)$user['est_verifie'] !== 1) {
                throw new Exception("Veuillez vérifier votre adresse email avant de vous connecter.");
            }

            // Régénération de l'identifiant de session (Session ID) à chaque connexion réussie
            // Mesure critique contre les attaques par fixation de session (Session Fixation)
            session_regenerate_id(true);
            
            // Stockage des informations d'identification minimales dans la session (côté serveur)
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];

            // Si la case "Se souvenir de moi" est cochée, on initialise le dispositif de cookie longue durée
            if ($seSouvenirDeMoi) {
                $this->creerCookieConnexion($user['id']);
            }

            return true;
        }

        // Retourne false si l'utilisateur n'existe pas ou si le mot de passe est invalide
        return false;
    }

    /**
     * Vérification et reconnexion automatique via le cookie de session persistant
     * À appeler systématiquement au chargement de votre application globale
     * 
     * @return bool True si l'utilisateur a été reconnecté automatiquement, False sinon
     */
    public function connexionAutomatiqueParCookie(): bool {
        // Si le cookie est absent OU si l'utilisateur possède déjà une session active, on ne fait rien
        if (!isset($_COOKIE['remember_me']) || isset($_SESSION['user_id'])) {
            return false;
        }

        // Récupération de la valeur brute du cookie envoyé par le navigateur
        $token = $_COOKIE['remember_me'];

        // Recherche du jeton correspondant en base de données
        $sql = "SELECT * FROM utilisateurs WHERE remember_token = :token LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':token' => $token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Si le jeton match en BDD, on récrée les variables de session à la volée
        if ($user) {
            session_regenerate_id(true); // Sécurisation de la nouvelle session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            return true;
        }

        // Si un cookie invalide ou expiré est présenté (potentiel vol de cookie ou falsification), 
        // on force sa suppression du navigateur en lui attribuant une date d'expiration passée
        setcookie('remember_me', '', time() - 3600, '/', '', false, true);
        return false;
    }

    /**
     * Déconnexion complète de l'utilisateur
     */
    public function deconnecter(): void {
        // 1. Révocation du token en BDD s'il y a une session active
        if (isset($_SESSION['user_id'])) {
            $sql = "UPDATE utilisateurs SET remember_token = NULL WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id' => $_SESSION['user_id']]);
        }

        // 2. Nettoyage et destruction complète du tableau de session PHP
        $_SESSION = [];
        session_destroy();

        // 3. Suppression du cookie physique sur le poste client (navigateur)
        if (isset($_COOKIE['remember_me'])) {
            setcookie('remember_me', '', time() - 3600, '/', '', false, true);
        }
    }

    /**
     * Génère un token persistant, le stocke en BDD et configure le cookie utilisateur
     * 
     * @param int $userId ID de l'utilisateur concerné
     */
    private function creerCookieConnexion(int $userId): void {
        // Génération d'un sélecteur/token cryptographiquement sûr pour le cookie
        $token = bin2hex(random_bytes(32));

        // Enregistrement de ce jeton unique lié à l'utilisateur en BDD
        $sql = "UPDATE utilisateurs SET remember_token = :token WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':token' => $token, ':id' => $userId]);

        // Configuration stricte des options de sécurité du cookie
        setcookie(
            'remember_me',
            $token,
            [
                'expires' => time() + (86400 * 30), // Validité fixée à 30 jours (86400 secondes * 30)
                'path' => '/',                       // Accessible sur l'ensemble de l'application
                'secure' => false,                   // /!\ À passer à true impérativement en production (HTTPS requis)
                'httponly' => true,                  // Bloque l'accès au cookie via document.cookie en JavaScript (Protection XSS)
                'samesite' => 'Strict'               // Interdit l'envoi du cookie lors de requêtes cross-site (Protection CSRF)
            ]
        );
    }

    /**
     * Envoi de l'email contenant le lien d'activation avec PHPMailer
     * 
     * @param string $email Adresse du destinataire
     * @param string $cle Jeton d'activation unique
     * @throws Exception Si PHPMailer rencontre une erreur de configuration ou de transport
     */
    private function envoyerEmailVerification(string $email, string $cle): void {
        // Instanciation de PHPMailer (Le paramètre 'true' active la levée d'exceptions internes)
        $mail = new PHPMailer(true);

        // --- Configuration Technique du Serveur SMTP ---
        $mail->isSMTP();                                      // Activation du protocole de transport SMTP
        $mail->Host       = 'smtp.votre-serveur.com';        // Hôte du serveur d'envoi de mail (ex: smtp.mailtrap.io ou votre hébergeur)
        $mail->SMTPAuth   = true;                             // Activation de l'authentification obligatoire sur le serveur SMTP
        $mail->Username   = 'votre-adresse@domaine.com';      // Nom d'utilisateur de la boîte d'envoi
        $mail->Password   = 'votre_mot_de_passe_secret';     // Mot de passe associé
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;   // Chiffrement TLS recommandé (Port 587)
        $mail->Port       = 587;                              // Port TCP standardisé pour l'envoi TLS
        $mail->CharSet    = 'UTF-8';                          // Forçage de l'encodage pour préserver les caractères accentués français

        // --- Définition des Acteurs de l'Échange ---
        $mail->setFrom('no-reply@monsite.com', 'Mon Application'); // Identité de l'expéditeur
        $mail->addAddress($email);                                   // Adresse mail cible de l'utilisateur

        // --- Préparation du Contenu du Message ---
        $lien = "https://votresite.com/verifier.php?cle=" . $cle; // URL absolue de traitement de la clé
        
        $mail->isHTML(true); // Indique que le corps principal accepte des balises HTML
        $mail->Subject = "Activez votre compte"; // Objet du courriel
        
        // Corps enrichi (HTML) : Rendu graphique pour la majorité des messageries modernes
        $mail->Body    = "
            <h2>Bienvenue sur notre plateforme !</h2>
            <p>Pour finaliser votre inscription et activer votre compte, merci de cliquer sur le bouton ci-dessous :</p>
            <p style='margin: 20px 0;'>
                <a href='{$lien}' style='background-color: #007BFF; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Valider mon adresse email</a>
            </p>
            <small>Si le bouton ne fonctionne pas, copiez-collez ce lien dans votre navigateur : {$lien}</small>
        ";
        
        // Corps alternatif (Texte brut) : Utilisé si le client mail du destinataire rejette l'affichage HTML (anti-spam, terminaux légers)
        $mail->AltBody = "Bonjour,\n\nMerci de cliquer sur le lien suivant pour valider votre compte :\n" . $lien;

        // Déclenchement de la séquence d'envoi réseau vers le serveur SMTP
        $mail->send();
    }
}
?>