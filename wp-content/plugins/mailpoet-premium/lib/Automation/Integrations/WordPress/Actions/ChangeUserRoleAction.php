<?php declare(strict_types = 1);

namespace MailPoet\Premium\Automation\Integrations\WordPress\Actions;

if (!defined('ABSPATH')) exit;


use MailPoet\Automation\Engine\Control\StepRunController;
use MailPoet\Automation\Engine\Data\Step;
use MailPoet\Automation\Engine\Data\StepRunArgs;
use MailPoet\Automation\Engine\Data\StepValidationArgs;
use MailPoet\Automation\Engine\Exceptions\RuntimeException;
use MailPoet\Automation\Engine\Integration\Action;
use MailPoet\Automation\Engine\Integration\ValidationException;
use MailPoet\Automation\Integrations\WordPress\Payloads\UserPayload;
use MailPoet\Automation\Integrations\WordPress\Subjects\UserSubject;
use MailPoet\Validator\Builder;
use MailPoet\Validator\Schema\ObjectSchema;
use MailPoet\WP\Functions as WPFunctions;

class ChangeUserRoleAction implements Action {
  public const KEY = 'wordpress:change-user-role';

  private const ELEVATED_CAPABILITIES = [
    'create_users',
    'delete_users',
    'edit_files',
    'edit_users',
    'install_plugins',
    'manage_options',
    'manage_woocommerce',
    'mailpoet_manage_settings',
    'promote_users',
    'unfiltered_html',
  ];

  /** @var WPFunctions */
  private $wp;

  public function __construct(
    WPFunctions $wp
  ) {
    $this->wp = $wp;
  }

  public function getKey(): string {
    return self::KEY;
  }

  public function getName(): string {
    // translators: automation action title
    return __('Change user role', 'mailpoet-premium');
  }

  public function getArgsSchema(): ObjectSchema {
    return Builder::object([
      'target_role' => Builder::string()->required()->minLength(1),
    ]);
  }

  public function getSubjectKeys(): array {
    return [
      UserSubject::KEY,
    ];
  }

  public function validate(StepValidationArgs $args): void {
    $this->validateTargetRole((string)($args->getStep()->getArgs()['target_role'] ?? ''));
  }

  public function run(StepRunArgs $args, StepRunController $controller): void {
    $targetRole = (string)($args->getStep()->getArgs()['target_role'] ?? '');
    try {
      $this->validateTargetRole($targetRole);
    } catch (ValidationException $e) {
      throw RuntimeException::create($e)->withMessage(
        __("User role could not be changed because the target role is no longer available or cannot be granted.", 'mailpoet-premium')
      );
    }

    $user = $args->getSinglePayloadByClass(UserPayload::class)->getUser();
    if (!$user->exists()) {
      throw RuntimeException::create()->withMessage(
        __("User role could not be changed because the user does not exist.", 'mailpoet-premium')
      );
    }

    if ($user->roles === [$targetRole]) {
      return;
    }

    $user->set_role($targetRole);
  }

  public function onDuplicate(Step $step): Step {
    return $step;
  }

  private function validateTargetRole(string $targetRole): void {
    $roles = $this->wp->getEditableRoles();
    if ($targetRole === '' || !$this->wp->getRole($targetRole) || !array_key_exists($targetRole, $roles)) {
      throw ValidationException::create()
        ->withError('target_role', __('Select a valid user role.', 'mailpoet-premium'));
    }

    if ($targetRole === 'administrator' || $this->isElevatedRole($roles[$targetRole])) {
      throw ValidationException::create()
        ->withError('target_role', __('This role cannot be granted by an automation.', 'mailpoet-premium'));
    }
  }

  /**
   * @param array{name?: string, capabilities?: array<string, bool>} $role
   */
  private function isElevatedRole(array $role): bool {
    $capabilities = $role['capabilities'] ?? [];
    foreach (self::ELEVATED_CAPABILITIES as $capability) {
      if (!empty($capabilities[$capability])) {
        return true;
      }
    }
    return false;
  }
}
