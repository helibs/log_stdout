<?php

namespace Drupal\log_stdout\Logger;

use Drupal\user\Entity\User;
use Psr\Log\LoggerInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Logger\RfcLoggerTrait;
use Drupal\Core\Logger\LogMessageParserInterface;

/**
 * Logger.
 */
class Stdout implements LoggerInterface
{
  use RfcLoggerTrait;

  /**
   * The message's placeholders parser.
   *
   * @var \Drupal\Core\Logger\LogMessageParserInterface
   */
  protected $parser;

  /**
   * Constructs a Stdout object.
   *
   * @param \Drupal\Core\Logger\LogMessageParserInterface $parser
   *   The parser to use when extracting message variables.
   */
  public function __construct(LogMessageParserInterface $parser)
  {
    $this->parser = $parser;
  }

  /**
   * Get user name from user ID.
   *
   * @param int $uid
   *
   *   The uid.
   *
   * @return mixed
   *
   *   The user name.
   */
  public function getUsername($uid)
  {
    $account = User::load($uid);
    $name = $account->getDisplayName();
    return $name;
  }

  /**
   * Get user role from user ID.
   *
   * @param int $uid
   *
   *   The uid.
   *
   * @return mixed
   *
   *   The user role.
   */
  public function getUserRole($uid)
  {
    $account = User::load($uid);
    $role = $account->getRoles();
    return $role;
  }

  /**
   * {@inheritdoc}
   */
  public function log($level, $message, array $context = [])
  {
    $type = $context['channel'];
    $output = fopen('php://stdout', 'w');
    if ($level <= RfcLogLevel::WARNING) {
      $output = fopen('php://stderr', 'w');
    }

    // If message is JSON formatted.
    if (json_decode($message) != NULL) {
      $message = json_decode($message);
      $message->json_format = 'true';
      $message->type = $type;
      $message = json_encode($message, JSON_UNESCAPED_SLASHES);
    }
    // Else.
    else {
      $variables = $this->parser->parseMessagePlaceholders($message, $context);
      $input_message = $message;
      if (!empty($message) && !empty($variables)) {
        $input_message = strip_tags(t($message, $variables));
      }
      $severity = strtoupper(RfcLogLevel::getLevels()[$level]);
      $username = '';

      if (isset($context['uid']) && !empty($context['uid'])) {
        $uid = $context['uid'];
        $username = $this->getUsername($uid);
      }
      $userrole = 'none';

      if (isset($context['uid']) && !empty($context['uid'])) {
        $uid = $context['uid'];
        $userrole = !empty($this->getUserRole($uid)) ? $this->getUserRole($uid) : 'anonymous';
      }

      if (empty($username)) {
        $username = 'anonymous';
      }

      $request_uri = $context['request_uri'];
      $json_format = 'true';

      $elements = [
        'severity' => $severity,
        'type' => $type,
        'message' => $input_message,
        'user role(s)' => $userrole,
        'request_uri' => $request_uri,
        'json_format' => $json_format,
      ];
      $message = json_encode($elements, JSON_UNESCAPED_SLASHES);
    }
    fwrite($output, $message . "\r\n");
    fclose($output);
  }
}
