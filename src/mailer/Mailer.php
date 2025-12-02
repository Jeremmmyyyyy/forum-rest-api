<?php
/*
 * Copyright (c) 2023. Hadrien Sevel
 * Project: forum-rest-api
 * File: Mailer.php
 */

namespace Mailer;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mailer
{
    private const SUBJECT_NEW_ANSWER_NOTIFICATION = 'Nouvelle réponse à votre question';
    private PHPMailer $mailer;

    /**
     * Mailer constructor.
     * @throws Exception
     */
    public function __construct()
    {
        // Set the default timezone
        date_default_timezone_set('Europe/Zurich');

        // Create a new PHPMailer instance
        $this->mailer = new PHPMailer(true);

        // Server settings
        $this->mailer->SMTPDebug = 1;                               //Debug output: 0 = off (for production use), 1 = client messages, 2 = client and server messages
        $this->mailer->isSMTP();                                    //Send using SMTP
        $this->mailer->Host = MAILER_HOST;                          //Set the SMTP server to send through
        $this->mailer->SMTPAuth = true;                             //Enable SMTP authentication
        $this->mailer->Username = MAILER_USERNAME;                  //SMTP username
        $this->mailer->Password = MAILER_PASSWORD;                  //SMTP password
        $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; //Enable implicit TLS encryption
        $this->mailer->Port = MAILER_PORT;                          //TCP port to connect to; use 587 if you have set `SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS`
        $this->mailer->Timeout = 10;                                //SMTP timeout in seconds

        // Configure TLS 1.3 as per university requirements
        $this->mailer->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
                'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT,
            )
        );

        // Sender
        $this->mailer->setFrom(MAILER_FROM, MAILER_FROM_NAME);

        $this->mailer->isHTML();
        $this->mailer->CharSet = 'UTF-8';
    }

    /**
     * Send a new answer notification.
     * @param string|null $section
     * @param int $questionId
     * @param string $email The email address of the user who asked the question.
     * @return void
     * @throws Exception
     */
    public function sendNewAnswerNotification(?string $section, int $questionId, string $email): void
    {
        $linkGm = 'https://botafogo.epfl.ch/analyse-1-GM/?page=forum_toutes_les_questions&question=' . $questionId;
        $linkOnline = 'https://botafogo.epfl.ch/analyse-1-online/?page=forum_toutes_les_questions&question=' . $questionId;

        if ($section === null) {
            $section = 'Analyse 1';
        }

        $body = file_get_contents(__DIR__ . '/templates/new_answer_notification.html');
        $body = str_replace('{{section}}', $section, $body);
        $body = str_replace('{{link_gm}}', $linkGm, $body);
        $body = str_replace('{{link_online}}', $linkOnline, $body);
        $this->sendEmail([$email], self::SUBJECT_NEW_ANSWER_NOTIFICATION, $body);
    }

    /**
     * Email admins when an error occurs.
     * @param string $errorId The ID of the error.
     * @param string $error The error message.
     * @return void
     * @throws Exception
     */
    public function sendErrorEmail(string $errorId, string $error): void
    {
        $error = str_replace('\n', '<br />', $error);
        $body = '<h1>' . $errorId . '</h1><pre style="white-space: pre-wrap">' . $error . '</pre>';
        $this->sendEmail(API_ADMIN_EMAILS, '[' . API_NAME . '] ERROR: ' . $errorId, $body);
    }

    /**
     * Send an email.
     * @param array $recipients An array of email addresses.
     * @param string $subject The subject of the email.
     * @param string $body The body of the email.
     * @param string|null $attachment (optional) The path to the attachment.
     * @return void
     * @throws Exception
     */
    public function sendEmail(array $recipients, string $subject, string $body, ?string $attachment = null): void
    {
        try {
            // Recipients
            foreach ($recipients as $recipient) {
                $this->mailer->addAddress($recipient);
            }

            // Reply to
            $this->mailer->addReplyTo('support-technique.analyse@groupes.epfl.ch');

            // Content of the email
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $body;

            if ($attachment) {
                $this->mailer->addAttachment($attachment);
            }

            // Send the email
            $this->mailer->send();

            // Save the email in the "Sent" folder (best-effort, non-blocking)
            try {
                $this->saveEmail();
            } catch (Exception $saveError) {
                // Log but don't fail - saving to sent folder is not critical
                logMessage('Failed to save email to Sent folder: ' . $saveError->getMessage(), 'WARNING');
            }

            // Catch errors
        } catch (Exception $e) {
            throw new Exception('Failed to send email: ' . $e->getMessage());
        }
    }

    /**
     * Save the email in the "Sent" folder and set it as "Seen".
     * @return void
     * @throws Exception
     */
    private function saveEmail(): void
    {
        $path = MAILER_IMAP_SENT_FOLDER;
        
        // Set a timeout for IMAP operations to prevent hanging
        $timeout = 5; // 5 seconds timeout
        $originalTimeout = ini_get('default_socket_timeout');
        ini_set('default_socket_timeout', $timeout);
        
        try {
            $imapStream = @imap_open(
                $path, 
                $this->mailer->Username, 
                $this->mailer->Password, 
                0, 
                1, 
                array('DISABLE_AUTHENTICATOR' => 'GSSAPI')
            );
            
            if ($imapStream === false) {
                throw new Exception('Failed to connect to IMAP: ' . imap_last_error());
            }
            
            if (!imap_append($imapStream, $path, $this->mailer->getSentMIMEMessage())) {
                throw new Exception('Failed to append email to Sent folder: ' . imap_last_error());
            }
            
            $check = imap_check($imapStream);
            if ($check !== false) {
                imap_setflag_full($imapStream, $check->Nmsgs, "\\Seen");
            }
            
            imap_close($imapStream);
        } finally {
            // Restore original timeout
            ini_set('default_socket_timeout', $originalTimeout);
        }
    }
}