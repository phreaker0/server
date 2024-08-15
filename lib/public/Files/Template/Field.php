<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCP\Files\Template;

/**
 * @since 30.0.0
 */
class Field implements \JsonSerializable {
	public ?string $content = null;
	public ?string $alias = null;
	public ?string $tag = null;
	public ?int $id = null;
	public ?bool $checked = null;

	/**
	 * @since 30.0.0
	 */
	public function __construct(
		private string $index,
		private FieldType $type
	) {
	}

	/**
	 * @since 30.0.0
	 */
	public function jsonSerialize(): array {
		$jsonProperties = [];

		foreach (get_object_vars($this) as $propertyName => $propertyValue) {
			if (is_null($propertyValue)) {
				continue;
			}

			if ($propertyValue instanceof FieldType) {
				$propertyValue = $propertyValue->value;
			}

			array_push($jsonProperties, [$propertyName => $propertyValue]);
		}
		
		return array_merge([], ...$jsonProperties);
	}
}
