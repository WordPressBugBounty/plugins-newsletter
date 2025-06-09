<?php

/**
 * @property string $to
 * @property string $to_name
 * @property string $subject
 * @property string $body
 * @property array $headers
 * @property string $from
 * @property string $from_name
 */
class TNP_Mailer_Message {

    var $ch; // Transient variable for mailers with turbo send option
    var $to = '';
    var $to_name = '';
    var $headers = [];
    var $user_id = 0;
    var $email_id = 0;
    var $error = '';
    var $subject = '';
    var $body = '';
    var $body_text = '';
    var $from = '';
    var $from_name = '';
}



