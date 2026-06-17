<?php
declare(strict_types=1);

namespace App\Services;

// On importe les classes de la librairie PHPMailer (installée via Composer
// dans app/models/utlisateurs/vendor). L'autoloader est chargé par config.php.
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * MailService — Service d'envoi d'e-mails
 * =======================================
 *
 * Cette classe centralise TOUS les envois d'e-mails de l'application :
 *   1. e-mail de bienvenue (à l'inscription)
 *   2. e-mail de vérification d'adresse (lien à cliquer)
 *   3. e-mail de réinitialisation de mot de passe ("mot de passe oublié")
 *
 * Pourquoi une classe dédiée ?
 *   → Pour ne pas répéter la configuration SMTP dans chaque contrôleur.
 *     On configure PHPMailer une seule fois ici, et les contrôleurs
 *     appellent simplement MailService::sendWelcome(...), etc.
 *
 * Mode "développement" :
 *   Si aucun mot de passe SMTP n'est défini (constante MAIL_PASSWORD vide),
 *   on N'ENVOIE PAS réellement l'e-mail : on l'écrit dans les logs PHP.
 *   Cela évite de bloquer l'inscription quand le SMTP n'est pas encore
 *   configuré, tout en permettant de récupérer le lien dans les logs.
 */
class MailService
{
    /**
     * Construit et pré-configure un objet PHPMailer prêt à envoyer.
     *
     * @return PHPMailer Instance configurée (SMTP + expéditeur + format HTML).
     */
    private static function makeMailer(): PHPMailer
    {
        // Le paramètre "true" active le mode exceptions : en cas d'erreur,
        // PHPMailer lève une Exception (plus facile à attraper qu'un retour false).
        $mail = new PHPMailer(true);

        // ── Paramètres SMTP (serveur d'envoi) ──
        $mail->isSMTP();                                  // On envoie via un serveur SMTP
        $mail->Host       = MAIL_HOST;                    // ex : smtp.gmail.com
        $mail->SMTPAuth   = true;                         // Authentification requise
        $mail->Username   = MAIL_USERNAME;                // Identifiant SMTP
        $mail->Password   = MAIL_PASSWORD;                // Mot de passe / mot de passe d'application
        $mail->Port       = MAIL_PORT;                    // 587 (TLS) ou 465 (SSL)

        // Chiffrement de la connexion : TLS (recommandé) ou SSL.
        $mail->SMTPSecure = MAIL_ENCRYPTION === 'ssl'
            ? PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer::ENCRYPTION_STARTTLS;

        // ── Encodage : indispensable pour les accents français (é, à, ç…) ──
        $mail->CharSet  = 'UTF-8';
        $mail->Encoding = 'base64';

        // ── Expéditeur (From) ──
        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);

        // ── Format HTML (on enverra du HTML, avec une version texte de secours) ──
        $mail->isHTML(true);

        return $mail;
    }

    /**
     * Détermine si l'on est en mode "réel" (SMTP configuré) ou "dev" (logs).
     *
     * @return bool true si un mot de passe SMTP est défini.
     */
    private static function smtpConfigured(): bool
    {
        return defined('MAIL_PASSWORD') && MAIL_PASSWORD !== '';
    }

    /**
     * Envoi générique bas niveau, utilisé par les méthodes publiques.
     *
     * @param string $toEmail Adresse du destinataire.
     * @param string $toName  Nom du destinataire (affiché dans le client mail).
     * @param string $subject Objet de l'e-mail.
     * @param string $htmlBody Corps HTML.
     * @param string $textBody Corps texte (affiché si le client refuse le HTML).
     * @return bool  true si "envoyé" (ou loggé en mode dev), false sinon.
     */
    private static function send(string $toEmail, string $toName, string $subject, string $htmlBody, string $textBody): bool
    {
        // ── Mode développement : pas de SMTP → on logge au lieu d'envoyer ──
        if (!self::smtpConfigured()) {
            error_log("[MailService:DEV] À: {$toEmail} | Objet: {$subject}\n{$textBody}");
            return true; // On considère "réussi" pour ne pas bloquer le flux applicatif.
        }

        // ── Mode réel : envoi via PHPMailer/SMTP ──
        try {
            $mail = self::makeMailer();
            $mail->addAddress($toEmail, $toName);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;   // Version HTML
            $mail->AltBody = $textBody;   // Version texte (fallback)
            $mail->send();
            return true;
        } catch (Exception $e) {
            // En cas d'échec (SMTP refusé, réseau, etc.), on logge l'erreur
            // sans interrompre l'application : l'utilisateur reste créé.
            error_log('[MailService] Échec envoi à ' . $toEmail . ' : ' . $mail->ErrorInfo);
            return false;
        }
    }

    /**
     * Enveloppe HTML commune à tous les e-mails (en-tête + pied de page).
     * On garde un style simple et "inline" car les clients mail (Gmail,
     * Outlook…) ne supportent pas les feuilles CSS externes.
     *
     * @param string $title   Titre affiché en gros dans l'e-mail.
     * @param string $content Contenu HTML interne (paragraphes, bouton…).
     * @return string HTML complet de l'e-mail.
     */
    private static function layout(string $title, string $content): string
    {
        return '
        <div style="font-family:Arial,sans-serif;max-width:560px;margin:0 auto;background:#F3E7D9;border-radius:12px;overflow:hidden;border:1px solid #d6c4a8;">
            <div style="background:#544349;padding:24px;text-align:center;">
                <h1 style="color:#F3E7D9;margin:0;font-size:20px;">🍽️ Planificateur de Repas</h1>
            </div>
            <div style="padding:28px 24px;color:#2A191F;">
                <h2 style="color:#576238;margin-top:0;font-size:18px;">' . $title . '</h2>
                ' . $content . '
            </div>
            <div style="background:#544349;padding:16px;text-align:center;">
                <p style="color:rgba(243,231,217,0.6);font-size:12px;margin:0;">
                    © 2026 Planificateur de Repas — Cet e-mail vous a été envoyé automatiquement.
                </p>
            </div>
        </div>';
    }

    /**
     * Génère un bouton HTML cliquable (lien stylé) pour les e-mails.
     *
     * @param string $url   Destination du lien.
     * @param string $label Texte du bouton.
     * @return string HTML du bouton.
     */
    private static function button(string $url, string $label): string
    {
        return '<p style="text-align:center;margin:24px 0;">
            <a href="' . htmlspecialchars($url, ENT_QUOTES) . '"
               style="background:#CE2A2A;color:#fff;text-decoration:none;padding:12px 28px;border-radius:8px;font-weight:bold;display:inline-block;">'
            . $label . '</a></p>';
    }

    // ════════════════════════════════════════════════════════════════
    //  MÉTHODES PUBLIQUES — appelées par les contrôleurs
    // ════════════════════════════════════════════════════════════════

    /**
     * E-mail de bienvenue, envoyé juste après l'inscription.
     *
     * @param string $email     Adresse du nouvel utilisateur.
     * @param string $firstname Prénom (pour personnaliser le message).
     * @return bool
     */
    public static function sendWelcome(string $email, string $firstname): bool
    {
        $content = '
            <p>Bonjour <strong>' . htmlspecialchars($firstname, ENT_QUOTES) . '</strong>,</p>
            <p>Bienvenue sur le <strong>Planificateur de Repas</strong> ! Votre compte a bien été créé.</p>
            <p>Vous pouvez dès maintenant :</p>
            <ul>
                <li>Gérer vos ingrédients et recettes</li>
                <li>Générer des menus hebdomadaires équilibrés</li>
                <li>Suivre votre budget et vos apports nutritionnels</li>
            </ul>
            <p>Bonne planification&nbsp;! 🥗</p>'
            . self::button(rtrim(APP_URL, '/') . '/login.php', 'Accéder à mon compte');

        $text = "Bonjour {$firstname},\n\nBienvenue sur le Planificateur de Repas ! "
              . "Votre compte a bien été créé.\n\nConnexion : " . rtrim(APP_URL, '/') . '/login.php';

        return self::send($email, $firstname, 'Bienvenue sur le Planificateur de Repas 🎉',
            self::layout('Bienvenue à bord ! 🎉', $content), $text);
    }

    /**
     * E-mail de vérification d'adresse : contient un lien unique à cliquer.
     *
     * @param string $email     Adresse à vérifier.
     * @param string $firstname Prénom.
     * @param string $token     Jeton en clair (sera dans l'URL).
     * @return bool
     */
    public static function sendVerification(string $email, string $firstname, string $token): bool
    {
        // Lien que l'utilisateur cliquera : il pointe vers verify-email.php
        // avec le token en paramètre d'URL.
        $url = rtrim(APP_URL, '/') . '/verify-email.php?token=' . urlencode($token);

        $content = '
            <p>Bonjour <strong>' . htmlspecialchars($firstname, ENT_QUOTES) . '</strong>,</p>
            <p>Merci de votre inscription. Pour activer votre compte, veuillez confirmer
               votre adresse e-mail en cliquant sur le bouton ci-dessous.</p>'
            . self::button($url, 'Confirmer mon adresse e-mail')
            . '<p style="font-size:13px;color:#666;">Ce lien est valable <strong>24 heures</strong>.
               Si vous n\'êtes pas à l\'origine de cette inscription, ignorez cet e-mail.</p>';

        $text = "Bonjour {$firstname},\n\nConfirmez votre adresse e-mail en ouvrant ce lien "
              . "(valable 24h) :\n{$url}";

        return self::send($email, $firstname, 'Confirmez votre adresse e-mail',
            self::layout('Confirmez votre adresse ✉️', $content), $text);
    }

    /**
     * E-mail de réinitialisation de mot de passe ("mot de passe oublié").
     *
     * @param string $email     Adresse du compte concerné.
     * @param string $firstname Prénom.
     * @param string $token     Jeton en clair (sera dans l'URL).
     * @return bool
     */
    public static function sendPasswordReset(string $email, string $firstname, string $token): bool
    {
        // Lien vers le formulaire de nouveau mot de passe, avec le token.
        $url = rtrim(APP_URL, '/') . '/reset-password.php?token=' . urlencode($token);

        $content = '
            <p>Bonjour <strong>' . htmlspecialchars($firstname, ENT_QUOTES) . '</strong>,</p>
            <p>Vous avez demandé à réinitialiser votre mot de passe. Cliquez sur le bouton
               ci-dessous pour en définir un nouveau.</p>'
            . self::button($url, 'Réinitialiser mon mot de passe')
            . '<p style="font-size:13px;color:#666;">Ce lien est valable <strong>1 heure</strong>.
               Si vous n\'avez pas fait cette demande, ignorez cet e-mail : votre mot de passe
               restera inchangé.</p>';

        $text = "Bonjour {$firstname},\n\nRéinitialisez votre mot de passe (lien valable 1h) :\n{$url}";

        return self::send($email, $firstname, 'Réinitialisation de votre mot de passe',
            self::layout('Mot de passe oublié ? 🔑', $content), $text);
    }
}
