<?php

namespace Drupal\social_group_invite;

use Drupal\Component\Utility\EmailValidatorInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryOverrideInterface;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Database\Connection;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides an overridden block for Settings Tray testing.
 *
 * @package Drupal\social_group_invite
 */
class SocialGroupInviteConfigOverride implements ConfigFactoryOverrideInterface {

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Email validator.
   *
   * @var \Drupal\Component\Utility\EmailValidatorInterface
   */
  protected $emailValidator;

  /**
   * The current active database's master connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs the configuration override.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Component\Utility\EmailValidatorInterface $email_validator
   *   The email validator.
   * @param \Drupal\Core\Database\Connection $database
   *   The current active database's master connection.
   */
  public function __construct(
    RequestStack $request_stack,
    EmailValidatorInterface $email_validator,
    Connection $database
  ) {
    $this->requestStack = $request_stack;
    $this->emailValidator = $email_validator;
    $this->database = $database;
  }

  /**
   * Load overrides.
   */
  public function loadOverrides($names) {
    $overrides = [];

    $config_name = 'user.settings';

    if (in_array($config_name, $names, TRUE)) {
      $request = $this->requestStack->getCurrentRequest();

      $invitee_mail = $request->query->get('invitee_mail', '');
      $destination = $request->query->get('destination', '');

      $is_valid = $this->validateInviteData($invitee_mail, $destination);

      if ($is_valid) {
        $overrides[$config_name]['verify_mail'] = FALSE;
      }
    }

    return $overrides;
  }

  /**
   * Validate invite data.
   *
   * @param string $invitee_mail
   *   Encoded email address of invited user.
   * @param string $destination
   *   The url of invited group.
   *
   * @return bool
   *   TRUE if invited data is valid.
   */
  public function validateInviteData($invitee_mail, $destination) {

    if (empty($invitee_mail) || empty($destination)) {
      return FALSE;
    }

    $invitee_mail = base64_decode(str_replace(['-', '_'], [
      '+',
      '/',
    ], $invitee_mail));

    if (!$this->emailValidator->isValid($invitee_mail)) {
      return FALSE;
    }

    $destination = explode('/', $destination);
    $gid = array_pop($destination);

    if (empty($gid) || !is_numeric($gid)) {
      return FALSE;
    }

    // Verify is it really was requested invite and it still is actual.
    $query = $this->database->select('group_content__invitee_mail', 'gcim');

    $query->fields('gcim', ['entity_id']);
    $query->condition('invitee_mail_value', $invitee_mail);

    $query->join('group_content__invitation_status', 'gcis', 'gcim.entity_id = gcis.entity_id');
    $query->condition('gcis.invitation_status_value', '0');

    $query->join('group_content_field_data', 'gcfd', 'gcis.entity_id = gcfd.id');
    $query->condition('gcfd.gid', $gid);

    $invitations = $query->execute()->fetchField();

    if (empty($invitations)) {
      return FALSE;
    }
    else {
      return TRUE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheSuffix() {
    return 'SocialGroupInviteConfigOverride';
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata($name) {
    return new CacheableMetadata();
  }

  /**
   * {@inheritdoc}
   */
  public function createConfigObject($name, $collection = StorageInterface::DEFAULT_COLLECTION) {
    // @phpstan-ignore-next-line
    return NULL;
  }

}
