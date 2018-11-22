<?php
class Mailer {
  // TODO: support HTML mail (i.e. MIME messages)

  private $last_error = "Unable to send mail: check local configuration.";

  function mail($params) {

    $to = $params["to"];
    $subject = $params["subject"];
    $message = $params["message"];
    $message_html = $params["message_html"];
    $from = $params["from"] ? $params["from"] : SMTP_FROM_NAME . " <" . SMTP_FROM_ADDRESS . ">";
    $additional_headers = $params["headers"] ? $params["headers"] : [];

    $headers[] = "From: $from";

    Logger::get()->log("Sending mail from $from to $to [$subject]: $message");

    // HOOK_SEND_MAIL plugin instructions:
    // 1. return 1 or true if mail is handled
    // 2. return -1 if there's been a fatal error and no further action is allowed
    // 3. any other return value will allow cycling to the next handler and, eventually, to default mail() function
    // 4. set error message if needed via passed Mailer instance function set_error()

    foreach (PluginHost::getInstance()->get_hooks(PluginHost::HOOK_SEND_MAIL) as $p) {
      $rc = $p->hook_send_mail($this, $params);

      if ($rc == 1 || $rc == -1)
        return $rc;
    }

    return mail($to, $subject, $message, implode("\r\n", array_merge($headers, $additional_headers)));
  }

  function set_error($message) {
    $this->last_error = $message;
  }

  function error() {
    return $this->last_error;
  }
}
