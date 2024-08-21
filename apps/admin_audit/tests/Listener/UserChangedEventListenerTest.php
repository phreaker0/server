<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\AdminAudit\Tests\Actions;

use OCA\AdminAudit\AuditLogger;
use OCA\AdminAudit\Listener\UserChangedEventListener;
use OCP\IUser;
use OCP\User\Events\UserChangedEvent;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class UserChangedEventListenerTest extends TestCase {
	private AuditLogger|MockObject $logger;

	private UserChangedEventListener $listener;

	private MockObject|IUser $user;

	protected function setUp(): void {
		parent::setUp();

		$this->logger = $this->createMock(AuditLogger::class);
		$this->listener = new UserChangedEventListener($this->logger);

		$this->user = $this->createMock(IUser::class);
		$this->user->method('getUID')->willReturn('alice');
		$this->user->method('getDisplayName')->willReturn('Alice');
	}

	public function testSkipUnsupported(): void {
		$this->logger->expects($this->never())
			->method('info');

		$event = new UserChangedEvent(
			$this->user,
			'unsupported',
			'value',
		);

		$this->listener->handle($event);
	}

	public function testUserEnabled(): void {
		$this->logger->expects($this->once())
			->method('info')
			->with('User enabled: "alice"', ['app' => 'admin_audit']);

		$event = new UserChangedEvent(
			$this->user,
			'enabled',
			true,
			false,
		);

		$this->listener->handle($event);
	}

	public function testUserDisabled(): void {
		$this->logger->expects($this->once())
			->method('info')
			->with('User disabled: "alice"', ['app' => 'admin_audit']);

		$event = new UserChangedEvent(
			$this->user,
			'enabled',
			false,
			true,
		);

		$this->listener->handle($event);
	}

	public function testEmailChanged(): void {
		$this->logger->expects($this->once())
			->method('info')
			->with('Email address changed for user alice', ['app' => 'admin_audit']);

		$event = new UserChangedEvent(
			$this->user,
			'eMailAddress',
			'alice@alice.com',
			'',
		);

		$this->listener->handle($event);
	}
}
