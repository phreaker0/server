<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\AdminAudit\Listener;

use OCA\AdminAudit\Actions\Action;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\User\Events\UserChangedEvent;

/** @template-implements IEventListener<UserChangedEvent> */
class UserChangedEventListener extends Action implements IEventListener {
	public function handle(Event $event): void {
		if (!($event instanceof UserChangedEvent)) {
			return;
		}

		switch ($event->getFeature()) {
			case 'enabled':
				$this->log(
					$event->getValue()
						? 'User enabled: "%s"'
						: 'User disabled: "%s"',
					['user' => $event->getUser()->getUID()],
					[
						'user',
					]
				);
				break;
			case 'eMailAddress':
				$this->log(
					'Email address changed for user %s',
					['user' => $event->getUser()->getUID()],
					[
						'user',
					]
				);
				break;
		}
	}
}
